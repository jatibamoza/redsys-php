<?php
// bridge_redsys.php
// Recibe un JSON y reenvía por POST (auto-submit) a generarPet.php

declare(strict_types=1);

/* Solo aceptar formulario POST originado desde nuestras instancias Salesforce */
if (!headers_sent()) {
    // Allow-list explícita
    $ALLOWED_BASES = [
        // UAT
        'https://laliga--uat.sandbox.my.salesforce.com',
        'https://laliga--uat.lightning.force.com',
        'https://laliga--uat.sandbox.my.site.com',
        'https://laliga--uat.force.com', // por si se usa site legacy
        // Producción
        'https://laliga.my.salesforce.com',
        'https://laliga.lightning.force.com',
        'https://laliga.my.site.com',
        'https://laliga.force.com',      // por si se usa site legacy
    ];

    // Patrones para cubrir variantes equivalentes
    $ALLOWED_REGEX = [
        '#^https://laliga(?:--[a-z0-9-]+)?(?:\.sandbox)?\.my\.salesforce\.com$#i',
        '#^https://laliga(?:--[a-z0-9-]+)?\.lightning\.force\.com$#i',
        '#^https://laliga(?:--[a-z0-9-]+)?(?:\.sandbox)?\.my\.site\.com$#i',
        '#^https://laliga(?:--[a-z0-9-]+)?\.force\.com$#i',
    ];

    $origin   = $_SERVER['HTTP_ORIGIN']           ?? '';
    $referer  = $_SERVER['HTTP_REFERER']          ?? '';
    $method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $httpsOn  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $xfpHttps = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // TLS requerido (directo o detrás de proxy)
    if (!$httpsOn && !$xfpHttps) {
        http_response_code(400);
        exit('400 - HTTPS requerido.');
    }

    // Exigir POST
    if ($method !== 'POST') {
        http_response_code(405);
        exit('405 - Método no permitido.');
    }

    // Helpers
    $toBase = function(string $url): string {
        if ($url === '') return '';
        $sch = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $hst = strtolower((string)parse_url($url, PHP_URL_HOST));
        return ($sch && $hst) ? ($sch.'://'.$hst) : '';
    };
    $isAllowed = function(string $base) use ($ALLOWED_BASES, $ALLOWED_REGEX): bool {
        if ($base === '') return false;
        if (in_array($base, $ALLOWED_BASES, true)) return true;
        foreach ($ALLOWED_REGEX as $rx) { if (@preg_match($rx, $base)) return true; }
        return false;
    };

    $oBase = $toBase($origin);
    $rBase = $toBase($referer);

    // Si viene Origin (los navegadores lo envían incluso en forms), permitir si Origin o Referer están en allow-list
    if ($origin !== '') {
        if (!$isAllowed($oBase) && !$isAllowed($rBase)) {
            http_response_code(403);
            exit('403 - Origin/Referer no permitido.');
        }
        // No devolvemos cabeceras CORS
    } else {
        // Sin Origin: validar por Referer
        if (!$isAllowed($rBase)) {
            http_response_code(403);
            exit('403 - Referer no permitido.');
        }
    }
}

/* Entrada */
function read_json_payload(): array {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
    }
    if (isset($_POST['payload'])) {
        $decoded = json_decode((string)$_POST['payload'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
    }
    if (!empty($_POST)) return $_POST;
    return [];
}

$data = read_json_payload();
if (!$data) {
    http_response_code(400);
    echo '<h3>400 - Payload vacío o JSON inválido</h3>';
    exit;
}

$fields = [
    'DS_MERCHANT_AMOUNT',
    'DS_MERCHANT_ORDER',
    'DS_MERCHANT_MERCHANTCODE',
    'DS_MERCHANT_CURRENCY',
    'DS_MERCHANT_TRANSACTIONTYPE',
    'DS_MERCHANT_TERMINAL',
    'DS_MERCHANT_MERCHANTURL',
    'DS_MERCHANT_URLOK',
    'DS_MERCHANT_URLKO',
    'DS_URL_REDSYS',
    'DS_KC',
];

$mapped = [];
$hasDsKeys = false;
foreach ($fields as $f) { if (array_key_exists($f, $data)) { $hasDsKeys = true; break; } }

if ($hasDsKeys) {
    foreach ($fields as $f) { if (isset($data[$f])) $mapped[$f] = (string)$data[$f]; }
} else {
    $mapped['DS_MERCHANT_AMOUNT']          = isset($data['amount']) ? (string)$data['amount'] : '';
    $mapped['DS_MERCHANT_ORDER']           = isset($data['order']) ? (string)$data['order'] : '';
    $mapped['DS_MERCHANT_MERCHANTCODE']    = isset($data['merchantCode']) ? (string)$data['merchantCode'] : '';
    $mapped['DS_MERCHANT_CURRENCY']        = isset($data['currency']) ? (string)$data['currency'] : '978';
    $mapped['DS_MERCHANT_TRANSACTIONTYPE'] = isset($data['transactionType']) ? (string)$data['transactionType'] : '0';
    $mapped['DS_MERCHANT_TERMINAL']        = isset($data['terminal']) ? (string)$data['terminal'] : '1';
    $mapped['DS_MERCHANT_MERCHANTURL']     = isset($data['merchantUrl']) ? (string)$data['merchantUrl'] : '';
    $mapped['DS_MERCHANT_URLOK']           = isset($data['urlOk']) ? (string)$data['urlOk'] : '';
    $mapped['DS_MERCHANT_URLKO']           = isset($data['urlKo']) ? (string)$data['urlKo'] : '';
    $mapped['DS_URL_REDSYS']               = isset($data['urlRedsys']) ? (string)$data['urlRedsys'] : '';
    $mapped['DS_KC']                       = isset($data['kc']) ? (string)$data['kc'] : '';
}

/* Forzar KC desde servidor (no aceptar KC del cliente) */
$kcFromEnv = getenv('DS_KC');
if ($kcFromEnv) {
    $mapped['DS_KC'] = $kcFromEnv;
} else {
    http_response_code(500);
    exit('500 - Configuración de clave ausente.');
}

/* Requeridos mínimos */
$required = ['DS_MERCHANT_AMOUNT','DS_MERCHANT_ORDER','DS_MERCHANT_MERCHANTCODE','DS_URL_REDSYS','DS_KC'];
foreach ($required as $req) {
    if (empty($mapped[$req])) {
        http_response_code(400);
        echo '<h3>400 - Falta el campo requerido: '.$req.'</h3>';
        exit;
    }
}

/* Redirección por formulario a generarPet.php */
$target = 'generarPet.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Redirigiendo…</title>
</head>
<body>
  <form id="fwd" action="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" method="post" accept-charset="UTF-8">
    <?php foreach ($mapped as $k => $v):
      $name = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');
      $value = htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>
      <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
    <?php endforeach; ?>
    <noscript>
      <p>Haga clic en Continuar para seguir con el pago.</p>
      <button type="submit">Continuar</button>
    </noscript>
  </form>
  <script>
    (function(){ try { document.getElementById('fwd').submit(); } catch(e) {} })();
  </script>
</body>
</html>