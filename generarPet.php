<?php

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/signature.php';

//Datos de configuración
$version = "HMAC_SHA512_V2";
//$kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; //Clave recuperada de CANALES. JATC: EJEMPLO
$kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
// Valores de entrada que no hemos cmbiado para ningun ejemplo
//$fuc = "999008881"; //JATC: EJEMPLO

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
];
$order = $ds['DS_MERCHANT_ORDER'];
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
$target = 'https://sis-t.redsys.es:25443/sis/realizarPago';
?>
<html lang="es">
	<body>
        <!-- JATC: se elimina el tag target="_blank" -->
		<form name="frm" action="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>" method="POST" accept-charset="UTF-8">
		<!--
        Ds_Merchant_SignatureVersion <input type="text" name="Ds_SignatureVersion" value="<?php echo $version; ?>"/></br>
		Ds_Merchant_MerchantParameters <input type="text" name="Ds_MerchantParameters" value="<?php echo $params; ?>"/></br>
		Ds_Merchant_Signature <input type="text" name="Ds_Signature" value="<?php echo $signature; ?>"/></br>
		<input type="submit" value="Enviar" >
        -->
        <?php
            foreach ($data as $k => $v) {
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
                document.getElementById('frm').submit();
            } catch (e) {
                // Si algo falla, deja el botón del <noscript>
                console.warn('Auto-submit falló:', e);
            }
            })();
        </script>
        </form>
	</body>
</html>
