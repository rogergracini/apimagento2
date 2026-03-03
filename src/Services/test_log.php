<?php
// test_log.php
ini_set('display_errors', 1); // Tenta forçar a exibição de erros na tela também
error_reporting(E_ALL);     // Reporta todos os erros PHP

// Esta é a mensagem que vamos procurar no log
error_log("===== TESTE DE LOG PHP FUNCIONANDO - " . date("Y-m-d H:i:s") . " =====");

echo "<h1>Teste de Log Iniciado!</h1>";
echo "<p>Uma mensagem de teste foi enviada para o arquivo de log de erros do PHP.</p>";
echo "<p>Agora, vamos usar phpinfo() para descobrir onde esse arquivo de log está e outras configurações.</p>";
echo "<hr>";

phpinfo(); // Esta função mostra todas as informações de configuração do PHP
?>