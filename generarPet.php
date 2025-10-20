<?php
declare(strict_types=1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/signature.php';

$ds = [
    'DS_MERCHANT_AMOUNT'          => $_POST['DS_MERCHANT_AMOUNT']          ?? null,
    'DS_MERCHANT_ORDER'           => $_POST['DS_MERCHANT_ORDER']           ?? null,
    'DS_MERCHANT_MERCHANTCODE'    => $_POST['DS_MERCHANT_MERCHANTCODE']    ?? null,
    'DS_MERCHANT_CURRENCY'        => $_POST['DS_MERCHANT_CURRENCY']        ?? null,
    'DS_MERCHANT_TRANSACTIONTYPE' => $_POST['DS_MERCHANT_TRANSACTIONTYPE'] ?? null,
    'DS_MERCHANT_TERMINAL'        => $_POST['DS_MERCHANT_TERMINAL']        ?? null,
    'DS_MERCHANT_MERCHANTURL'     => $_POST['DS_MERCHANT_MERCHANTURL']     ?? null,
    'DS_MERCHANT_URLOK'           => $_POST['DS_MERCHANT_URLOK']           ?? null,
    'DS_MERCHANT_URLKO'           => $_POST['DS_MERCHANT_URLKO']           ?? null,
    'DS_URL_REDSYS'               => $_POST['DS_URL_REDSYS']                  ?? null,
    'DS_KC'                       => $_POST['DS_KC']                       ?? null,
];
//Datos de configuración
$order = $ds['DS_MERCHANT_ORDER'];
$version = "HMAC_SHA512_V2";
$kc = $ds['DS_KC'];
$redsysActionUrl = $ds['DS_URL_REDSYS'];//'https://sis-t.redsys.es:25443/sis/realizarPago';
// Se Rellenan los campos
$data = array(
	"DS_MERCHANT_AMOUNT"          => $ds['DS_MERCHANT_AMOUNT'],
    "DS_MERCHANT_ORDER"           => $ds['DS_MERCHANT_ORDER'],
    "DS_MERCHANT_MERCHANTCODE"    => $ds['DS_MERCHANT_MERCHANTCODE'],
    "DS_MERCHANT_CURRENCY"        => $ds['DS_MERCHANT_CURRENCY'],
    "DS_MERCHANT_TRANSACTIONTYPE" => $ds['DS_MERCHANT_TRANSACTIONTYPE'],
    "DS_MERCHANT_TERMINAL"        => $ds['DS_MERCHANT_TERMINAL'],
    "DS_MERCHANT_MERCHANTURL"     => $ds['DS_MERCHANT_MERCHANTURL'],
    "DS_MERCHANT_URLOK"           => $ds['DS_MERCHANT_URLOK'],
    "DS_MERCHANT_URLKO"           => $ds['DS_MERCHANT_URLKO'],
);

// Se generan los parámetros de la petición
$params = Utils::base64_url_encode_safe(json_encode($data));
$signature = Signature::createMerchantSignature($kc, $params, $order);

echo phpversion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Redirigiendo al TPV...</title>
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
</head>
<body onload="document.getElementById('redsys-form').submit();" style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; padding:24px;">
  <h1 style="margin:0 0 8px;">Conectando con el TPV</h1>
  <p style="margin:0 0 24px;">Por favor, espera un momento…</p>

  <form id="redsys-form" method="POST" action="<?php echo htmlspecialchars($redsysActionUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="Ds_SignatureVersion" value="<?php echo htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="Ds_MerchantParameters" value="<?php echo htmlspecialchars($params, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="Ds_Signature" value="<?php echo htmlspecialchars($signature, ENT_QUOTES, 'UTF-8'); ?>">

    <noscript>
      <p>Pulsa el botón para continuar al pago.</p>
      <button type="submit">Continuar al pago</button>
    </noscript>
  </form>

  <!-- Depuración opcional en consola, no visible al usuario -->
  <script>
    console.log('POST a RedSys preparado', {
      action: '<?php echo addslashes($redsysActionUrl); ?>',
      Ds_SignatureVersion: '<?php echo addslashes($version); ?>',
      Ds_MerchantParameters_len: <?php echo strlen($params); ?>,
      Ds_Signature_len: <?php echo strlen($signature); ?>
    });
  </script>
</body>
</html>
