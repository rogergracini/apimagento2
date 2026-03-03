<?php

require __DIR__ . '/vendor/autoload.php';

use App\Utils\TokenHelper;

$novoToken = TokenHelper::gerarTokenAdmin();

if ($novoToken) {
    echo "✅ Token gerado com sucesso: $novoToken\n";
} else {
    echo "❌ Falha ao gerar novo token.\n";
}
