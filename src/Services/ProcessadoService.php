<?php

namespace App\Services;

class ProcessadoService
{
    private string $caminhoArquivo;
    private array $dadosProcessados = [];

    /**
     * NOTA DE PERFORMANCE: Esta implementação lê e reescreve o arquivo de processados
     * a cada produto. Para importações muito grandes, considere otimizar
     * instanciando este serviço uma vez fora do loop de produtos e usando um método
     * para salvar os dados apenas no final do script.
     */
    public function __construct()
    {
        // O caminho para o arquivo que armazena o status dos SKUs processados.
        // O formato agora será: SKU|PRECO|STATUS
        $this->caminhoArquivo = __DIR__ . '/../../crgr_processeds.arr';
    }

    /**
     * Verifica se um SKU já foi processado e se seu preço e status não mudaram.
     *
     * @param string $sku O SKU a ser verificado.
     * @param float $precoAtual O preço atual do produto vindo do XML.
     * @param int $statusAtual O status atual do produto (1 para ativo, 2 para inativo).
     * @return bool Retorna `true` se o SKU foi processado e NÃO teve alterações.
     * Retorna `false` se o SKU é novo ou se teve alterações de preço/status.
     */
    public function jaProcessado(string $sku, float $precoAtual, int $statusAtual): bool
    {
        // Garante que o cache de status do arquivo seja limpo para ler a versão mais recente
        clearstatcache();
        if (!file_exists($this->caminhoArquivo)) {
            return false; // O arquivo não existe, então nada foi processado ainda.
        }

        $dadosSalvos = $this->carregarDados();

        // Verifica se o SKU existe nos dados salvos
        if (!isset($dadosSalvos[$sku])) {
            return false; // SKU novo, precisa processar.
        }

        $dadosAnteriores = $dadosSalvos[$sku];

        // Compara o preço e o status. Usa uma margem pequena para comparação de floats.
        $precoIgual = abs($dadosAnteriores['preco'] - $precoAtual) < 0.001;
        $statusIgual = $dadosAnteriores['status'] === $statusAtual;

        // Se o preço e o status são os mesmos, o produto não precisa ser atualizado.
        if ($precoIgual && $statusIgual) {
            return true; // Já processado e sem alterações.
        }

        // Se chegou aqui, o SKU existe mas teve alteração de preço ou status.
        return false; // Precisa processar para atualizar.
    }

    /**
     * Marca um SKU como processado, salvando seu estado atual (preço e status).
     * Se o SKU já existir no arquivo, sua linha será atualizada.
     *
     * @param string $sku O SKU a ser marcado/atualizado.
     * @param float $preco O preço a ser salvo.
     * @param int $status O status a ser salvo.
     */
    public function marcarComoProcessado(string $sku, float $preco, int $status): void
    {
        $dadosSalvos = $this->carregarDados();
        
        // Atualiza ou adiciona o SKU com os novos dados
        $dadosSalvos[$sku] = [
            'preco' => round($preco, 2),
            'status' => $status
        ];

        $this->salvarDados($dadosSalvos);
    }

    /**
     * Carrega os dados do arquivo de processados para um array associativo.
     * @return array
     */
    private function carregarDados(): array
    {
        $dados = [];
        if (!file_exists($this->caminhoArquivo)) {
            return $dados;
        }

        $linhas = file($this->caminhoArquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linhas as $linha) {
            $partes = explode('|', $linha);
            if (count($partes) === 3) {
                // Armazena como: $dados['SKU'] = ['preco' => X, 'status' => Y]
                $dados[$partes[0]] = ['preco' => (float)$partes[1], 'status' => (int)$partes[2]];
            }
        }
        return $dados;
    }

    /**
     * Salva o array de dados completo de volta no arquivo, sobrescrevendo o conteúdo.
     * @param array $dadosParaSalvar
     */
    private function salvarDados(array $dadosParaSalvar): void
    {
        $linhasParaSalvar = [];
        foreach ($dadosParaSalvar as $sku => $dados) {
            $linhasParaSalvar[] = "{$sku}|{$dados['preco']}|{$dados['status']}";
        }
        file_put_contents($this->caminhoArquivo, implode("\n", $linhasParaSalvar));
    }
}