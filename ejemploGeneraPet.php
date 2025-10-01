<?php

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/signature.php';

//Datos de configuración
$version = "HMAC_SHA512_V2";
//$kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; //Clave recuperada de CANALES. JATC: EJEMPLO
$kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

// Valores de entrada que no hemos cmbiado para ningun ejemplo
//$fuc = "999008881"; //JATC: EJEMPLO
$fuc = "005847462";
$terminal = "1";
$moneda = "978";
$transactionType = "0";
$url = ""; // URL para recibir notificaciones del pago
$order = "251001071352";//time();
$amount = "2378";//JATC: 145

$currentUrl = Utils::getCurrentUrl();
$pos = strpos($currentUrl, basename(__FILE__));
//$urlOKKO = substr($currentUrl, 0, $pos) . "ejemploRecepcionaPet.php";
$urlOKKO1 = "https://laliga--uat.sandbox.my.site.com/services/apexrest/RedsysNotification"; 
$urlOKKO2 = "https://laliga--uat.sandbox.my.site.com/latiendadelequipo/s/pago-exitoso";
// Se Rellenan los campos
$data = array(
	"DS_MERCHANT_AMOUNT" => $amount,
	"DS_MERCHANT_ORDER" => $order,
	"DS_MERCHANT_MERCHANTCODE" => $fuc,
	"DS_MERCHANT_CURRENCY" => $moneda,
	"DS_MERCHANT_TRANSACTIONTYPE" => $transactionType,
	"DS_MERCHANT_TERMINAL" => $terminal,
	"DS_MERCHANT_MERCHANTURL" => $url,
	"DS_MERCHANT_URLOK" => $urlOKKO1,
	"DS_MERCHANT_URLKO" => $urlOKKO2
);

// Se generan los parámetros de la petición
$params = Utils::base64_url_encode_safe(json_encode($data));
$signature = Signature::createMerchantSignature($kc, $params, $order);

echo phpversion();
?>
<html lang="es">
	<body>
		<form name="frm" action="https://sis-t.redsys.es:25443/sis/realizarPago" method="POST" target="_blank">
			Ds_Merchant_SignatureVersion <input type="text" name="Ds_SignatureVersion" value="<?php echo $version; ?>"/></br>
			Ds_Merchant_MerchantParameters <input type="text" name="Ds_MerchantParameters" value="<?php echo $params; ?>"/></br>
			Ds_Merchant_Signature <input type="text" name="Ds_Signature" value="<?php echo $signature; ?>"/></br>
			<input type="submit" value="Enviar" >
		</form>
	</body>
</html>
