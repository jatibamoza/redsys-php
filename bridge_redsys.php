<?php
/**
 * bridge_redsys.php
 *
 * Puente sencillo para recibir parámetros desde Salesforce (o cualquier cliente)
 * y reenviarlos a la página existente ejemploGeneratePet.php.
 *
 * Modo de uso (POST o JSON):
 *  - POST application/x-www-form-urlencoded con las claves:
 *      DS_MERCHANT_AMOUNT, DS_MERCHANT_ORDER, DS_MERCHANT_MERCHANTCODE,
 *      DS_MERCHANT_CURRENCY, DS_MERCHANT_TRANSACTIONTYPE, DS_MERCHANT_TERMINAL,
 *      DS_MERCHANT_MERCHANTURL, DS_MERCHANT_URLOK, DS_MERCHANT_URLKO
 *
 *  - POST application/json con un body que contenga esas mismas claves.
 *
 * Esta página:
 *  1) Valida y normaliza valores mínimos (p. ej., amount a céntimos).
 *  2) Construye un array $data con las claves esperadas.
 *  3) Reenvía por POST (auto-submit) a ejemploGeneratePet.php con las mismas claves,
 *     para que allí se haga el firmado/llamada a Redsys con tu código actual.
 *
 * NOTA: Más adelante, cuando quieras eliminar la redirección HTML, puedes
 *       `require` el archivo y pasarle $data directamente si ese script lo soporta.
 */

declare(strict_types=1);

// -------- Utilidades locales --------

/**
 * Detecta si el request es JSON.
 */
function is_json_request(): bool {
    $ctype = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    return stripos($ctype, 'application/json') !== false;
}

/**
 * Extrae parámetros del request (POST form o JSON).
 */
function get_input_params(): array {
    if (is_json_request()) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(400);
            echo 'Body JSON inválido';
            exit;
        }
        return $json;
    }
    // fallback: POST y luego GET
    return array_merge($_GET ?? [], $_POST ?? []);
}

/**
 * Normaliza el importe:
 * - Acepta "12.34" (euros) y lo transforma a "1234" (céntimos) si detecta decimales.
 * - Si ya viene como entero en céntimos, lo deja tal cual.
 */
function normalize_amount($amount): string {
    if ($amount === null || $amount === '') {
        return '';
    }
    // Quitar espacios y comas
    $a = str_replace([' ', ','], ['', '.'], (string)$amount);
    if (preg_match('/^\d+$/', $a)) {
        // Entero: asumimos que ya está en céntimos
        return $a;
    }
    if (preg_match('/^\d+(\.\d{1,2})?$/', $a)) {
        // Decimal en euros: pasamos a céntimos
        $parts = explode('.', $a);
        $euros = $parts[0];
        $cents = $parts[1] ?? '00';
        $cents = str_pad(substr($cents, 0, 2), 2, '0');
        return ltrim($euros, '0') . $cents;
    }
    // Formato no reconocido
    return '';
}

/**
 * Valida y normaliza el número de pedido (Redsys pide 4 a 12, primeros 4 numéricos).
 * Permitimos alfanumérico y reforzamos los 4 primeros dígitos; si no, rellenamos con 0.
 */
function normalize_order($order): string {
    $order = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$order));
    if ($order === '') return '';
    // Asegurar 4 primeros dígitos
    if (!preg_match('/^\d{4}/', $order)) {
        $order = str_pad($order, 4, '0', STR_PAD_LEFT);
        if (!preg_match('/^\d{4}/', $order)) {
            // Si aún no cumple, forzamos prefijo '0000'
            $order = '0000' . $order;
        }
    }
    // Limitar a 12 máx
    return substr($order, 0, 12);
}

/**
 * Valida URL básica.
 */
function is_valid_url($url): bool {
    if (!$url) return false;
    return (bool)filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Sanitiza un valor string simple.
 */
function clean($v): string {
    return trim((string)$v);
}

// -------- Lógica principal --------

$input = get_input_params();

$required_keys = [
    'DS_MERCHANT_AMOUNT',
    'DS_MERCHANT_ORDER',
    'DS_MERCHANT_MERCHANTCODE',
    'DS_MERCHANT_CURRENCY',
    'DS_MERCHANT_TRANSACTIONTYPE',
    'DS_MERCHANT_TERMINAL',
    'DS_MERCHANT_MERCHANTURL',
    'DS_MERCHANT_URLOK',
    'DS_MERCHANT_URLKO',
];

// Tomar valores y normalizar
$amount          = normalize_amount($input['DS_MERCHANT_AMOUNT'] ?? '');
$order           = normalize_order($input['DS_MERCHANT_ORDER'] ?? '');
$merchantCode    = clean($input['DS_MERCHANT_MERCHANTCODE'] ?? '');
$currency        = clean($input['DS_MERCHANT_CURRENCY'] ?? '978'); // EUR por defecto
$txType          = clean($input['DS_MERCHANT_TRANSACTIONTYPE'] ?? '0');
$terminal        = clean($input['DS_MERCHANT_TERMINAL'] ?? '1');
$merchantUrl     = clean($input['DS_MERCHANT_MERCHANTURL'] ?? '');
$urlOk           = clean($input['DS_MERCHANT_URLOK'] ?? '');
$urlKo           = clean($input['DS_MERCHANT_URLKO'] ?? '');

// Validaciones mínimas
$errors = [];
if ($amount === '' || !preg_match('/^\d+$/', $amount)) {
    $errors[] = 'DS_MERCHANT_AMOUNT inválido (usa euros con 2 decimales o céntimos enteros).';
}
if ($order === '' || strlen($order) < 4) {
    $errors[] = 'DS_MERCHANT_ORDER inválido (4-12, primeros 4 dígitos).';
}
if ($merchantCode === '') {
    $errors[] = 'DS_MERCHANT_MERCHANTCODE es requerido.';
}
if (!preg_match('/^\d+$/', $currency)) {
    $errors[] = 'DS_MERCHANT_CURRENCY inválido (ej. 978 para EUR).';
}
if ($txType === '') {
    $errors[] = 'DS_MERCHANT_TRANSACTIONTYPE es requerido (ej. 0).';
}
if ($terminal === '' || !preg_match('/^\d+$/', $terminal)) {
    $errors[] = 'DS_MERCHANT_TERMINAL inválido (ej. 1).';
}
if (!is_valid_url($merchantUrl)) {
    $errors[] = 'DS_MERCHANT_MERCHANTURL inválido (URL absoluta).';
}
if (!is_valid_url($urlOk)) {
    $errors[] = 'DS_MERCHANT_URLOK inválido (URL absoluta).';
}
if (!is_valid_url($urlKo)) {
    $errors[] = 'DS_MERCHANT_URLKO inválido (URL absoluta).';
}

if (!empty($errors)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Construimos el payload que espera tu ejemplo actual
$data = [
    'DS_MERCHANT_AMOUNT'          => $amount,
    'DS_MERCHANT_ORDER'           => $order,
    'DS_MERCHANT_MERCHANTCODE'    => $merchantCode,
    'DS_MERCHANT_CURRENCY'        => $currency,
    'DS_MERCHANT_TRANSACTIONTYPE' => $txType,
    'DS_MERCHANT_TERMINAL'        => $terminal,
    'DS_MERCHANT_MERCHANTURL'     => $merchantUrl,
    'DS_MERCHANT_URLOK'           => $urlOk,
    'DS_MERCHANT_URLKO'           => $urlKo,
];

// === Opción 1 (recomendada, neutra): reenviar por POST (auto-submit) a ejemploGeneratePet.php ===
// Mantiene los nombres de campos para que el script ejemplo los trate como hasta ahora.
$target = 'ejemploGeneratePet.php';

// Renderizamos formulario auto-submit
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Puente Redsys</title>
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; form-action 'self'">
</head>
<body>
  <p>Redirigiendo al procesador de pago...</p>
  <form id="relay" action="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" method="post">
    <?php foreach ($data as $k => $v): ?>
      <input type="hidden" name="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"
             value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <!-- Si tu ejemplo espera otros campos, puedes añadirlos aquí -->
  </form>
  <script>
    // Auto-submit inmediato
    document.getElementById('relay').submit();
  </script>
</body>
</html>
<?php
// === Fin Opción 1 ===

// === Opción 2 (alternativa, comentada): incluir el script directamente y pasarle $data ===
// require __DIR__ . '/ejemploGeneratePet.php';
// (Asegúrate de que ejemploGeneratePet.php lea $data en vez de $_POST si eliges esta ruta.)
