<?php

// Habilita o CORS para permitir solicitações de qualquer origem
header("Access-Control-Allow-Origin: *");

// Habilita os métodos HTTP permitidos
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

// Define os cabeçalhos permitidos
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Define o tipo de conteúdo como JSON
header("Content-Type: application/json");

// Verifica o método da solicitação
$method = $_SERVER['REQUEST_METHOD'];

// Obtém a URL da requisição
$request = $_SERVER['REQUEST_URI'];

// Se o método for OPTIONS, é uma solicitação de preflight CORS, então não há necessidade de processar mais nada
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Processa a solicitação de acordo com o método
if ($method === 'GET') {

    // Lógica para lidar com solicitações GET
    // echo json_encode(['message' => 'GET request processed']);

    // Verifica se o parâmetro 'nome' foi passado na URL
    if (isset($_GET['procurarcep'])) {
        $cep = $_GET['procurarcep'];
        // remover todos os caracteres na variável $cep que não forem numéricos
        $cep = preg_replace("/[^0-9]/", "", $cep);

        if (!is_numeric($cep) || $cep == "" || strlen($cep) != 8) {
            echo "Digite um CEP válido (somente números e com 8 dígitos)";
            return false;
        }
        
        $url = 'https://viacep.com.br/ws/' . $cep . '/json/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Accept-Charset: utf-8'));        
        $response = curl_exec($ch);
        
        if($response === false){
            $response_data = ['error' => true, 'message' => "Ocorreu um erro ao executar a requisição: " . curl_error($ch), 'data' => null];
        } else {
            $data = json_decode($response, true);
            // var_dump($data);
            $response_data = ['error' => false, 'message' => "Sucesso ao executar a requisição.", 'data' => $data];            
        }
        
        curl_close($ch);

        $response_data = ['error' => false, 'message' => '' , 'data' => $response_data] ;

        echo json_encode($response_data);
    } else {
        echo "Parâmetro 'procurarcep' não encontrado na URL";
    }


} elseif ($method === 'POST') {
    // Lógica para lidar com solicitações POST
    $data = json_decode(file_get_contents("php://input"), true);
    echo json_encode(['message' => 'POST request processed', 'data' => $data]);
} elseif ($method === 'PUT') {
    // Lógica para lidar com solicitações PUT
    $data = json_decode(file_get_contents("php://input"), true);
    echo json_encode(['message' => 'PUT request processed', 'data' => $data]);
} elseif ($method === 'DELETE') {
    // Lógica para lidar com solicitações DELETE
    echo json_encode(['message' => 'DELETE request processed']);
} else {
    // Método não suportado
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}






// $response_data = ['error' => false, 'message' => '' , 'data' => null];

// // Obtém a URL da requisição
// // $request = $_SERVER['REQUEST_URI'];

// $file_get_contents = json_decode(file_get_contents("php://input"), true);

// $array_data = ['file_get_contents' => $data /*, 'request' => $request */];

// $response_data = ['error' => false, 'message' => 'file_get_contents' , 'data' =>  $array_data ];

// echo json_encode(['message' => 'PUT request processed', 'data' => $response_data]);

// $request = $_REQUEST['procurarcep'];

// echo json_encode(['message' => 'PUT request processed', 'data' => $request]);