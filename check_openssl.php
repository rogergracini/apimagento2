<?php
echo "Versão do OpenSSL (PHP): " . OPENSSL_VERSION_TEXT . "\n";
echo "OPENSSL_VERSION_NUMBER: " . OPENSSL_VERSION_NUMBER . "\n";

$locations = openssl_get_cert_locations();
echo "openssl_get_cert_locations(): \n";
print_r($locations);

echo "\nTentando verificar o caminho de curl.cainfo do php.ini:\n";
$curl_cainfo = ini_get('curl.cainfo');
if ($curl_cainfo) {
    echo "curl.cainfo: " . $curl_cainfo . "\n";
    echo "Arquivo existe? " . (file_exists($curl_cainfo) ? "Sim" : "Não") . "\n";
    echo "É legível? " . (is_readable($curl_cainfo) ? "Sim" : "Não") . "\n";
} else {
    echo "curl.cainfo não está definido no php.ini ou não foi encontrado.\n";
}

echo "\nTentando verificar o caminho de openssl.cafile do php.ini:\n";
$openssl_cafile = ini_get('openssl.cafile');
if ($openssl_cafile) {
    echo "openssl.cafile: " . $openssl_cafile . "\n";
    echo "Arquivo existe? " . (file_exists($openssl_cafile) ? "Sim" : "Não") . "\n";
    echo "É legível? " . (is_readable($openssl_cafile) ? "Sim" : "Não") . "\n";
} else {
    echo "openssl.cafile não está definido no php.ini ou não foi encontrado.\n";
}
?>