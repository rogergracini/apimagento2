<?php

namespace App\Services;

use App\MagentoApiClient;
use App\Utils\DownloadHelper;

class ImagemService
{
    protected MagentoApiClient $client;

    public function __construct()
    {
        $this->client = new MagentoApiClient();
    }

    /**
     * Gerencia o upload de uma imagem para um produto, comparando timestamps.
     */
    public function gerenciarImagemProduto(string $sku, ?string $imagemId, bool $isBaseImage, ?string $timestampXml): array
    {
        // Padrão de retorno
        $resultado = [
            'status' => 'ignorado',
            'mensagem' => 'Nenhuma ação de imagem foi necessária.',
            'foto_valida' => false // << NOVO CAMPO: padrão é 'não tem foto'
        ];

        // Se não há ID de imagem no XML, não há o que fazer.
        if (empty($imagemId)) {
            $resultado ['mensagem'] = 'ImagemID nulo ou vazio no XML.';
            return $resultado;
        }

        // Pega o timestamp salvo no Magento para este produto
        $produtoMagento = $this->client->getProductBySku($sku, ['galle_image_timestamp']);
        $timestampMagento = null;
        if (isset($produtoMagento['custom_attributes'])) {
            foreach ($produtoMagento['custom_attributes'] as $attr) {
                if ($attr['attribute_code'] === 'galle_image_timestamp') {
                    $timestampMagento = $attr['value'];
                    break;
                }
            }
        }
        
        // CONDIÇÃO 1: A imagem já existe e está atualizada
        if ($timestampXml && $timestampMagento && $timestampXml <= $timestampMagento) {
            $resultado ['status'] = 'sucesso';
            $resultado ['mensagem'] = 'Imagem já está atualizada no Magento.';
            $resultado ['foto_valida'] = true; // << a foto é válida.
            return $resultado;
        }

        // Se chegou aqui, a imagem precisa ser atualizada
        $imageName = basename($imagemId) . '.jpg';
        $imageUrl = 'http://app.galle.com.br/images/grandes/' . str_replace('.jpg', '', $imagemId) . '.jpg';
        
        error_log("ImagemService: Atualizando imagem para SKU {$sku}. Motivo: Timestamp do XML ({$timestampXml}) é mais novo que o do Magento ({$timestampMagento}).");

        $tempFile = tempnam(sys_get_temp_dir(), 'magento_img_');
        try {
            // Usa cURL para um download mais confiável
            $ch = curl_init($imageUrl);
            $fp = fopen($tempFile, 'wb');
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($httpCode !== 200) throw new \Exception("Falha no download. HTTP {$httpCode}. URL: {$imageUrl}");
            if (filesize($tempFile) === 0) throw new \Exception("Imagem baixada está vazia. URL: {$imageUrl}");

            // Se o download deu certo, a foto é válida, mesmo que o upload falhe.
            $resultado['foto_valida'] = true; // << A foto é válida!

            $mimeType = mime_content_type($tempFile);
            $fileContent = base64_encode(file_get_contents($tempFile));

            // Remove a imagem antiga antes de subir a nova para evitar duplicatas
            $imagensAtuais = $this->client->getProductImages($sku);
            if (is_array($imagensAtuais)) {
                foreach ($imagensAtuais as $img) {
                    if (isset($img['label']) && $img['label'] === $imageName) {
                        $this->client->deleteProductImage($sku, $img['id']);
                        break;
                    }
                }
            }
            
            $payload = [
                'media_type' => 'image', 'label' => $imageName, 'position' => 1, 'disabled' => false,
                'types' => $isBaseImage ? ['image', 'small_image', 'thumbnail'] : [],
                'content' => ['base64_encoded_data' => $fileContent, 'type' => $mimeType, 'name' => $imageName]
            ];







            $respostaUpload = $this->client->uploadImageToProduct($sku, $payload);
            if (isset($respostaUpload['error']) && $respostaUpload['error']) {
                throw new \Exception($respostaUpload['message'] ?? 'Erro da API de mídia do Magento.');
            }

            // ----- NOVA CHAMADA PARA FORÇAR AS MARCAÇÕES -----
            // Se for a imagem principal (Base), chamamos a função que corrige o bug do painel
            if ($isBaseImage) {
                $this->client->forceGlobalImageRoles($sku);
            }
            // --------------------------------------------------

            $resultado['status'] = 'sucesso';
            $resultado['mensagem'] = 'Imagem atualizada com sucesso.';
            $resultado['timestamp_atualizado'] = $timestampXml;







        } catch (\Throwable $e) {
            $resultado['status'] = 'erro_processamento';
            $resultado['mensagem'] = $e->getMessage();
            // Se houve um erro no download, a flag 'foto_valida' já foi definida como false (ou não foi alterada do padrão).
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        return $resultado;
    }
}