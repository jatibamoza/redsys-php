<?php
// bridge_redsys.php
// Recibe un JSON y reenvía por POST (auto-submit) a generaPet.php

declare(strict_types=1);

// Permite CORS básico si lo necesitas (opcional):
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Headers: Content-Type');
// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

function read_json_payload(): array {
    // 1) Primero intenta leer JSON del body (application/json)
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    // 2) Alternativa: payload en form-url-encoded (POST) como 'payload'
    if (isset($_POST['payload'])) {
        $decoded = json_decode((string)$_POST['payload'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    // 3) Alternativa: enviar cada campo como POST normal
    //    Si llegan DS_* directos, los reenviamos tal cual sin transformar
    if (!empty($_POST)) {
        return $_POST;
    }

    return [];
}

$data = read_json_payload();
if (!$data) {
    http_response_code(400);
    echo '<h3>400 - Payload vacío o JSON inválido</h3>';
    exit;
}

/**
 * SOPORTAMOS DOS FORMAS:
 * A) JSON con los DS_MERCHANT_* (nombres Redsys)
 *    {
 *      "DS_MERCHANT_AMOUNT": "2378",
 *      "DS_MERCHANT_ORDER": "250923200533",
 *      "DS_MERCHANT_MERCHANTCODE": "005847462",
 *      "DS_MERCHANT_CURRENCY": "978",
 *      "DS_MERCHANT_TRANSACTIONTYPE": "0",
 *      "DS_MERCHANT_TERMINAL": "1",
 *      "DS_MERCHANT_MERCHANTURL": "https://.../notificacion",
 *      "DS_MERCHANT_URLOK": "https://.../ok",
 *      "DS_MERCHANT_URLKO": "https://.../ko"
 *    }
 *
 * B) JSON con claves “cortas” que mapeamos a DS_MERCHANT_*:
 *    {
 *      "amount": 2378,              // en céntimos (string o int)
 *      "order": "250923200533",
 *      "merchantCode": "005847462",
 *      "currency": "978",
 *      "transactionType": "0",
 *      "terminal": "1",
 *      "merchantUrl": "https://.../notificacion",
 *      "urlOk": "https://.../ok",
 *      "urlKo": "https://.../ko"
 *    }
 */

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

// Caso A: ya vienen como DS_MERCHANT_*
$hasDsKeys = false;
foreach ($fields as $f) {
    if (array_key_exists($f, $data)) {
        $hasDsKeys = true;
        break;
    }
}

if ($hasDsKeys) {
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            $mapped[$f] = (string)$data[$f];
        }
    }
} else {
    // Caso B: mapear claves “cortas” a DS_MERCHANT_*
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

// Validación mínima
$required = ['DS_MERCHANT_AMOUNT','DS_MERCHANT_ORDER','DS_MERCHANT_MERCHANTCODE','DS_URL_REDSYS','DS_KC'];
foreach ($required as $req) {
    if (empty($mapped[$req])) {
        http_response_code(400);
        echo '<h3>400 - Falta el campo requerido: '.$req.'</h3>';
        exit;
    }
}

// Construye un POST auto-enviado hacia generaPet.php
$target = 'generarPet.php'; // ajusta el path si va en subcarpeta

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Redirigiendo…</title>
</head>
<body>
  <form id="fwd" action="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" method="post" accept-charset="UTF-8">
    <?php
      foreach ($mapped as $k => $v) {
        $name = htmlspecialchars($k, ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        echo '<input type="hidden" name="'.$name.'" value="'.$value.'">', PHP_EOL;
      }
    ?>
    <noscript>
      <p>Haga clic en Continuar para seguir con el pago.</p>
      <button type="submit">Continuar</button>
    </noscript>
  </form>
  <script>
    (function(){
      try {
        document.getElementById('fwd').submit();
      } catch (e) {
        // Si algo falla, deja el botón del <noscript>
        console.warn('Auto-submit falló:', e);
      }
    })();
  </script>
</body>
</html>