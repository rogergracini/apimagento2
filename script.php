<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "✅ Iniciando Script CRG Representacao...\n";

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Api\Data\AssetInterfaceFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Gallery\Processor;

// Inicializa o Magento 2

$dir = '/home3/pratas31/crgr.com.br';
require_once $dir . '/vendor/autoload.php';  // Carrega o autoload do Composer
require $dir . '/app/bootstrap.php';  // Carrega o bootstrap

$params = $_SERVER;
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);

$objectManager = $bootstrap->getObjectManager();

// Serviços necessários
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$productFactory = $objectManager->get(ProductFactory::class);
$galleryProcessor = $objectManager->get(Processor::class);
$mediaGalleryEntryFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory::class);
$imageContentFactory = $objectManager->get(\Magento\Framework\Api\Data\ImageContentInterfaceFactory::class);


// Defina a área como 'adminhtml' para acessar o admin
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

echo "✅ Iniciando execução do script...\n";

// Remove arquivos antigos
@unlink('Preco.xml');
@unlink('Produto.xml');

$file = 'arq.zip';
if (!file_exists($file) || filesize($file) === 0) {
    echo "O arquivo arq.zip não existe ou está vazio.\n";
    exit;
}

// Extrai SOMENTE os arquivos necessários do ZIP
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    $ok1 = $ok2 = false;
    if ($zip->locateName('Preco.xml') !== false) {
        $ok1 = $zip->extractTo('./', ['Preco.xml']);
    }
    if ($zip->locateName('Produto.xml') !== false) {
        $ok2 = $zip->extractTo('./', ['Produto.xml']);
    }
    $zip->close();
    if ($ok1 || $ok2) {

        echo "✅ Arquivos XML extraídos com sucesso.\n";
    } else {
        echo "❌ Nenhum dos arquivos desejados foi extraído.\n";
        exit;
    }
} else {
    echo "❌ Erro ao abrir o arquivo ZIP.\n";
    exit;
}

///////////////////////////////////////////

// Conexão com o banco de dados (opcional, se necessário)
$conn = new mysqli('localhost', 'pratas31_crgr', '#Ewdfh1k7', 'pratas31_user_crgr');
$conn->query("SET NAMES 'utf8'");
$conn->query("SET character_set_connection=utf8");

if (file_exists('crgr_processeds.arr')) {
    $processeds = file_get_contents('crgr_processeds.arr');
    $processeds = explode(',', trim($processeds));
} else {
    $processeds = [];
}

// Lê os arquivos XML
$precoFile = fopen($dir . '/Preco.xml', 'r');
$produtoFile = fopen($dir . '/Produto.xml', 'r');

$lines = [];
$start = false;
$linesAux = [];
$sku = '';
$key = '';

// Processa Preco.xml
while (!feof($precoFile)) {
    $line = trim(fgets($precoFile));
    if ($line == '<Row>') {
        $linesAux = [];
        $start = true;
        $sku = '';
        continue;
    } elseif ($line == '</Row>') {
        if (!isset($lines[$key])) {
            $lines[$key] = [];
        }
        $lines[$key][$sku] = $linesAux[$key];
        $linesAux = [];
        $start = false;
        continue;
    }
    if ($start === true) {
        $tmp = explode('<', $line, 2);
        $tmp = explode('>', $tmp[1], 2);
        $fieldName = $tmp[0];
        $tmp = explode('<', $tmp[1], 2);
        $fieldValue = $tmp[0];
        if ($fieldName == 'ProdutoID_Int') {
            $sku = $fieldValue;
        }
        if ($fieldName == 'TabelaID_Int') {
            $key = $fieldValue;
        }
        if ((strlen(trim($key)) > 0) && !isset($linesAux[$key])) {
            $linesAux[$key] = [];
        }
        if (isset($linesAux[$key])) {
            $linesAux[$key][$fieldName] = $fieldValue;
        }
    }
}
fclose($precoFile);

// Processa Produto.xml
$start = false;
$linesAux = [];
$sku = '';
$tmpKeys = array_keys($lines);
foreach ($tmpKeys as $key) {
    fseek($produtoFile, 0);
    while (!feof($produtoFile)) {
        $line = trim(fgets($produtoFile));
        if ($line == '<Row>') {
            $linesAux = [];
            $start = true;
            $sku = '';
            continue;
        } elseif ($line == '</Row>') {
            if (isset($lines[$key][$sku])) {
                $lines[$key][$sku] = array_merge($lines[$key][$sku], $linesAux[$key]);
            }
            $linesAux = [];
            $start = false;
            continue;
        }
        if ($start === true) {
            $tmp = explode('<', $line, 2);
            $tmp = explode('>', $tmp[1], 2);
            $fieldName = $tmp[0];
            $tmp = explode('<', $tmp[1], 2);
            $fieldValue = $tmp[0];
            if ($fieldName == 'ProdutoID_Int') {
                $sku = $fieldValue;
            }
            if ($fieldName == 'TabelaID_Int') {
                $key = $fieldValue;
            }
            if ((strlen(trim($key)) > 0) && !isset($linesAux[$key])) {
                $linesAux[$key] = [];
            }
            if (isset($linesAux[$key])) {
                $linesAux[$key][$fieldName] = $fieldValue;
            }
        }
    }
}
fclose($produtoFile);


/////////////////////////////////////////////////////////////

// Define tabelas e referências
$tables = [];
if (!isset($lines['666'])) $lines['666'] = []; //TABELA 2025/001 PRATA - O
if (!isset($lines['667'])) $lines['667'] = []; //TABELA 2025/002 PRATA - R
if (!isset($lines['668'])) $lines['668'] = []; //TABELA 2025/003 PRATA - RC

if (!isset($lines['670'])) $lines['670'] = []; //TABELA 2025/005 FOLHEADO
if (!isset($lines['671'])) $lines['671'] = []; //TABELA 2025/006 FOLHEADO(braRC)

$tables['4'] = array_merge($lines['666'], $lines['670']);
$tables['6'] = array_merge($lines['666'], $lines['671']);
$tables['8'] = array_merge($lines['667'], $lines['670']);
$tables['10'] = array_merge($lines['667'], $lines['671']);
$tables['12'] = array_merge($lines['668'], $lines['670']);
$tables['14'] = array_merge($lines['668'], $lines['671']);
$tables['16'] = array_merge($lines['668'], $lines['671']);

$magentoRefs = [];
$magentoRefs['4'] = 5; //ID do Magento 2025/001(x4) + 2025/005(x4) and 2025/001(x4) + 2025/005(x5)
$magentoRefs['6'] = 7; //ID do Magento 2025/001(x4) + 2025/006(x4) and 2025/001(x4) + 2025/006(x5)
$magentoRefs['8'] = 9; //ID do Magento 2025/002(x4) + 2025/005(x4) and 2025/002(x4) + 2025/005(x5)
$magentoRefs['10'] = 11; //ID do Magento 2025/002(x4) + 2025/006(x4) and 2025/002(x4) + 2025/006(x5)
$magentoRefs['12'] = 13; //ID do Magento 2025/003(x4) + 2025/005(x4) and 2025/003(x4) + 2025/005(x5)
$magentoRefs['14'] = 15; //ID do Magento 2025/003(x4) + 2025/006(x4) and 2025/003(x4) + 2025/006(x5)
$magentoRefs['16'] = 17; //ID do Magento 2025/003(x5) + 2025/006(x4) and 2025/003(x5) + 2025/006(x5)

$products = [];
foreach ($tables as $keyTables => $valueTables) {
    foreach ($valueTables as $key => $values) {
        if (!isset($products[$key])) {
            $products[$key] = $values;
        }
        if (!isset($products[$key]['groups'])) {            
            $products[$key]['groups'] = [];
        }
        array_push($products[$key]['groups'], [$keyTables, $values['Preco']]);
        array_push($products[$key]['groups'], [$magentoRefs[$keyTables], $values['Preco']]);
    }
}


///////////////////////////////////////////////////////////





// Função para baixar a imagem
function downloadImage($url, $path) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //Ignora erros de SSL
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos
    $imageContent = curl_exec($ch);
    curl_close($ch);
    if ($imageContent === false || strlen(trim($imageContent)) === 0) {
        echo "❌ Erro ao baixar imagem: $url\n";
        return false;
    }
    file_put_contents($path, $imageContent);
    return true;
}




////////////////////////////////////////////////////////////////



// Processa os produtos
foreach ($products as $sku => $value) {
    echo "🔍 Processando SKU: $sku\n";
    if (($value['Ativo'] == 'False') || ($value['Ativo'] === false)) {
        echo "❌ Produto inativo (Ativo = False): $sku - Ignorando\n";
        continue;
    }
    if (in_array($sku, $processeds)) {
        echo "⚠️ Produto já processado: $sku - Ignorando\n";
        continue;
    }
    if (strpos($sku, 'BR') !== false) {
        echo "⚠️ Produto ignorado (contém 'BR'): $sku\n";
        continue;
    }
    echo "✅ Produto válido: $sku\n";


    //TESTAR PRODUTO AQUI
    
    if (strpos($sku, '01-2017AG') === false) {
        continue;
    }

    $name = $value['Descricao'] . ' - ' . $value['TipoID_Int'] . ' - ' . $value['Largura_MM'] . 'mm x ' . $value['Altura_MM'] . 'mm - ' . $value['Peso'] . 'gr';
    $image = 'http://app.galle.com.br/images/grandes/' . $value['ImagemID'] . '.jpg';
    if (empty($value['ImagemID']) || !strpos($value['ImagemID'], '/')) {
        echo "❌ ImagemID inválido: " . $value['ImagemID'] . " - SKU: $sku\n";
        continue;
    }
    $image_name = explode('/', $value['ImagemID'])[1];
    if (!preg_match('/\.(jpg|jpeg|png|gif)$/i', $image_name)) {
        $image_name .= '.jpg'; //Assume JPG como padrão
    }
    echo "🖼️ ImagemID válido: $image_name - SKU: $sku\n";

    $weight = floatval($value['Peso']);
    $originalPrice = floatval($value['Preco']);
    $ver = explode('-', $sku)[0];
    $tipoProduto = (strpos($sku, 'AG') !== false) ? 'AG' : 'OURO';
    $weightValue = ($value['PrecoGrama'] === '1') ? $weight : 1;


    echo "Iniciando SKU do Produto: $sku\n";
    try {
        //Verifica se o produto já existe
        try {
            $product = $productRepository->get($sku);
            echo "Atualizando produto: $sku\n";
        } catch (\Exception $e) {
            $product = $productFactory->create();
            echo "Criando novo produto: $sku\n";
        }

        //Define os atributos do produto
        $urlKey = slug($name . ' ' . $sku);
        $todayDate = date("m/d/Y"); //Data de criação
        $newDate = date('m/d/Y', strtotime("+15 day")); // Dias que o produto fica com status novo
        $product->setUrlKey($urlKey);
        $product->setWebsiteIds([1]);
        $product->setAttributeSetId(4);
        $product->setTypeId('simple');
        $product->setName($name);
        $product->setDescription($name);
        $product->setShortDescription($name);
        $product->setWeight($weight);
        $product->setCountryOfManufacture('BR');
        $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
        $product->setTaxClassId(0);
        $product->setManufacturer(28);
        $product->setSku($sku);
        $product->setNewsFromDate($todayDate);
        $product->setNewsToDate($newDate);




//////////////////////////////////////////////////////////////////////




// 🚀 INÍCIO REGRAS DE CATEGORIAS //

// Default Category ID: 2 é obrigatória para todos
$categoryIds = [2];

// Verifica se é lançamento e adiciona a categoria 41
if (isset($value['Lancamento']) && $value['Lancamento'] == '1') {
    $categoryIds[] = 41; // Categoria 41
    // Se for MaterialID 0 (Folheado a Ouro), adiciona a categoria 42
    if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 0) {
        $categoryIds[] = 42; // Categoria 42
    }
    // Se for MaterialID 2 (Prata Pura), adiciona a categoria 43
    if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 2) {
        $categoryIds[] = 43; // Categoria 43
    }
}

// 🚀 Categorias para o material Folheado a Ouro (MaterialID 0)
if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 0) {
    // Categoria 44 (Folheado a Ouro)
    $categoryIds[] = 44;

    // Se for do grupo 03, adiciona a categoria 45
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '03') {
        $categoryIds[] = 45; // Categoria 45
    }

    // Se tiver no descrição "Bracelete", adiciona a categoria 46
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Bracelete') !== false) {
        $categoryIds[] = 46; // Categoria 46
    }

    // Se for do grupo 02, adiciona a categoria 47
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '02') {
        $categoryIds[] = 47; // Categoria 47
    }

    // Se tiver no descrição "Choker", adiciona a categoria 48
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Choker') !== false) {
        $categoryIds[] = 48; // Categoria 48
    }

    // Se for do grupo 04, adiciona a categoria 49
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '04') {
        $categoryIds[] = 49; // Categoria 49
        // Adiciona categorias de tamanho baseado em TamanhoID
        if (isset($value['TamanhoID_Int'])) {
            switch ($value['TamanhoID_Int']) {
                case '4P': $categoryIds[] = 50; break;
                case '4F': $categoryIds[] = 51; break;
                case '4G': $categoryIds[] = 52; break;
                case '4H': $categoryIds[] = 53; break;
                case '4I': $categoryIds[] = 54; break;
                case '4T': $categoryIds[] = 55; break;
            }
        }
    }

    // Se tiver no descrição "Escapulario", adiciona a categoria 56
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Escapulario') !== false) {
        $categoryIds[] = 56; // Categoria 56
    }

    // Se tiver no descrição "Gargantilha", adiciona a categoria 57
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Gargantilha') !== false) {
        $categoryIds[] = 57; // Categoria 57
    }

    // Se tiver no descrição "Piercing", adiciona a categoria 58
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Piercing') !== false) {
        $categoryIds[] = 58; // Categoria 58
    }

    // Se for do grupo 01, adiciona a categoria 59
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '01') {
        $categoryIds[] = 59; // Categoria 59
        // Se tiver no descrição "Pulseira", adiciona a categoria 60
        if (isset($value['Descricao']) && strpos($value['Descricao'], 'Pulseira') !== false) {
            $categoryIds[] = 60; // Categoria 60
            // Adiciona categorias de tamanho baseado em TamanhoID
            if (isset($value['TamanhoID_Int'])) {
                switch ($value['TamanhoID_Int']) {
                    case '4A': $categoryIds[] = 61; break;
                    case '4B': $categoryIds[] = 62; break;
                    case '4C': $categoryIds[] = 63; break;
                    case '4J': $categoryIds[] = 64; break;
                    case '4Q': $categoryIds[] = 65; break;
                }
            }
        }
    }

    // Se tiver no descrição "Tornozeleira", adiciona a categoria 66
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Tornozeleira') !== false) {
        $categoryIds[] = 66; // Categoria 66
    }
}

// 🚀 Categorias para o material Prata Pura (MaterialID 2)
if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 2) {
    // Categoria 67 (Prata Pura)
    $categoryIds[] = 67;

    // Se for do grupo 03, adiciona a categoria 68
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '03') {
        $categoryIds[] = 68; // Categoria 68
    }

    // Se tiver no descrição "Berloque", adiciona a categoria 69
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Berloque') !== false) {
        $categoryIds[] = 69; // Categoria 69
    }

    // Se tiver no descrição "Bracelete", adiciona a categoria 70
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Bracelete') !== false) {
        $categoryIds[] = 70; // Categoria 70
    }

    // Se for do grupo 02, adiciona a categoria 71
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '02') {
        $categoryIds[] = 71; // Categoria 71
    }

    // Se tiver no descrição "Choker", adiciona a categoria 72
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Choker') !== false) {
        $categoryIds[] = 72; // Categoria 72
    }

    // Se tiver no descrição "Escapulario", adiciona a categoria 73
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Escapulario') !== false) {
        $categoryIds[] = 73; // Categoria 73
    }

    // Se tiver no descrição "Gargantilha", adiciona a categoria 74
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Gargantilha') !== false) {
        $categoryIds[] = 74; // Categoria 74
    }

    // Se tiver no descrição "Piercing", adiciona a categoria 75
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Piercing') !== false) {
        $categoryIds[] = 75; // Categoria 75
    }

    // Se for do grupo 01, adiciona a categoria 76
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '01') {
        $categoryIds[] = 76; // Categoria 76
    }

    // Se tiver no descrição "Pulseira", adiciona a categoria 77
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Pulseira') !== false) {
        $categoryIds[] = 77; // Categoria 77
    }

    // Se tiver no descrição "Terco", adiciona a categoria 78
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Terco') !== false) {
        $categoryIds[] = 78; // Categoria 78
    }

    // Se tiver no descrição "Tornozeleira", adiciona a categoria 79
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Tornozeleira') !== false) {
        $categoryIds[] = 79; // Categoria 79
    }
}

// 🚀 Associa as categorias ao produto
$product->setCategoryIds($categoryIds);

// 🚀 FIM REGRAS DE CATEGORIAS //




////////////////////////////////////////////////////////////////////



        //Define estoque
        $product->setStockData([
            'use_config_manage_stock' => 0,
            'use_config_min_qty' => 1,
            'use_config_min_sale_qty' => 1,
            'use_config_max_sale_qty' => 1,
            'use_config_backorders' => 1,
            'use_config_notify_stock_qty' => 1,
            'manage_stock' => 1,
            'min_sale_qty' => 0,
            'min_qty' => 0,
            'max_sale_qty' => 100000,
            'is_qty_decimal' => 0,
            'backorders' => 0,
            'notify_stock_qty' => 5000,
            'is_in_stock' => 1,
            'qty' => 3150,
        ]);


        //Define preços
        // Define preços e preços de grupo
        $groupPrice = [];
        $hiValue = 0;
        foreach ($value['groups'] as $keyGr => $valueGr) {
            $priceX4 = floatval($valueGr[1]) * 4 * $weightValue;
            $priceX5 = floatval($valueGr[1]) * 5 * $weightValue;
            if ($hiValue < $priceX4) {
                $hiValue = $priceX4;
            }
            if ($hiValue < $priceX5) {
                $hiValue = $priceX5;
            }
            if ((in_array($valueGr[0], $magentoRefs)) && ($tipoProduto != 'AG')) {
                $priceGr = $priceX5;
            } else {
                $priceGr = $priceX4;
            }

            $groupPrice[] = [
                'website_id' => 0, //Pode ser 1 se quiser travar no website 1
                'cust_group' => (int)$valueGr[0],
                'price' => floatval($priceGr),
                'all_groups' => false,
                'price_qty' => 1, // 👈 Adiciona esse campo explicitamente
                //'qty' => 1, //🚨 AGORA adicionando Quantidade obrigatória!
            ];
        }

        //Atribui corretamente
        $product->setData('tier_price', $groupPrice); // 🛠️ No Magento 2 é "tier_price" se tiver qty
        $product->setPrice($hiValue);
        $product->setCost(null);
        $product->setSpecialPrice($hiValue);

        //Salva o produto

        try {
            $productRepository->save($product);
            echo "✅ Produto salvo com sucesso: $sku\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao salvar produto: $sku\n";
            echo $e->getMessage() . "\n";
        }



// Função auxiliar para gerar caminho correto no Magento
function getMagentoImagePath($imageName) {
    $imageName = strtolower($imageName);
    $imageName = str_replace(' ', '_', $imageName);
    $subfolder1 = substr($imageName, 0, 1);
    $subfolder2 = substr($imageName, 1, 1);
    return $subfolder1 . '/' . $subfolder2 . '/' . $imageName;
}

// Diretório base via DirectoryList
$directoryList = $objectManager->get(\Magento\Framework\App\Filesystem\DirectoryList::class);
$mediaPath = $directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
$imageDir = $mediaPath . '/catalog/product/'; // Caminho para o diretório de imagens de produto

// Gera caminho da imagem
$relativeImagePath = getMagentoImagePath($image_name); // ex: 0/1/01-1847ag.jpg
$imagePath = $imageDir . $relativeImagePath; // Caminho completo no servidor

// Cria subpastas se necessário
$dirToCreate = dirname($imagePath);
if (!is_dir($dirToCreate)) {
    mkdir($dirToCreate, 0777, true);
}

// Baixa a imagem se não existir localmente
if (!file_exists($imagePath)) {
    echo "⬇️ Baixando imagem para o diretório correto...\n";
    if (!downloadImage($image, $imagePath)) { // $image contém a URL
        echo "❌ Erro ao baixar a imagem: $image\n";
        continue; // Pula para o próximo produto no loop principal
    } else {
        echo "✅ Imagem baixada com sucesso: $imagePath\n";
    }
} else {
    echo "ℹ️ Imagem já existe localmente: $imagePath\n";
}

// Verifica e associa imagem ao produto
try {
    // Confirma novamente se o arquivo existe após a tentativa de download
    if (!file_exists($imagePath)) {
        throw new Exception("Imagem não encontrada no caminho final: $imagePath");
    }
    
    $product = $productRepository->get($sku, true); // O 'true' força a recarga

    // Verifica se o produto NÃO tem imagem principal ou se está como 'no_selection'
    $existingImage = $product->getImage();
    if (!$existingImage || $existingImage == 'no_selection') {
        echo "🔗 Produto sem imagem principal definida, associando imagem: $sku\n";
        
        $galleryProcessor->addImage(
            $product,
            $imagePath, // Caminho completo para a imagem baixada/existente
            ['image', 'small_image', 'thumbnail'], // Papéis a serem atribuídos
            false, // false = copia a imagem (não move)
            true   // true = remove imagens existentes com os mesmos papéis antes de adicionar
        );

        $product->setMediaGalleryEntries($product->getMediaGalleryEntries());

        // Salva o produto COM a nova imagem associada e galeria atualizada
        $productRepository->save($product);

        echo "🖼️ Imagem associada e produto salvo com sucesso: $sku\n";

    } else {        
        echo "⚠️ Produto já possui imagem principal ('{$existingImage}'), ignorando associação de papéis principais: $sku\n";         
    }
} catch (\Exception $e) {
    // Captura exceções do file_exists, get, addImage ou save
    echo "❌ Erro crítico ao processar/associar imagem para o produto: $sku\n";
    echo $e->getMessage() . "\n";
    // Considerar logar o erro completo ($e) para depuração detalhada
}

        //Marca como processado
        file_put_contents($dir . 'crgr_processeds.arr', ',' . $sku, FILE_APPEND);
        echo "Produto salvo: $sku\n";
    } catch (\Exception $e) {
        echo "Erro ao processar SKU: $sku\n";
        echo $e->getMessage() . "\n";
    }
}

//Limpa o arquivo de processados
if (file_exists($dir . 'crgr_processeds.arr')) {
    unlink($dir . 'crgr_processeds.arr');
}
echo "✅ Script concluído com sucesso.\n";

// Gera um slug a partir de uma string
function slug($string)
{
    $string = strtr(mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string)), 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    $string = strip_tags($string);
    $string = preg_replace('/[^A-Za-z0-9-]+/', ' ', $string);
    $string = trim($string);
    $string = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
    return strtolower($string);
}