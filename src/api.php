<?php

include_once "config_db.php";

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

// Se o método for OPTIONS, é uma solicitação de preflight CORS, então não há necessidade de processar mais nada
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Processa a solicitação de acordo com o método
if ($method === 'GET') {


    // Lógica para lidar com solicitações GET
    echo json_encode(['message' => 'GET request processed']);

    // Verifica se o parâmetro 'nome' foi passado na URL
    if (isset($_GET['nome'])) {
        $nome = $_GET['nome'];
        echo "Nome: $nome<br>";
    } else {
        echo "Parâmetro 'nome' não encontrado na URL<br>";
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

?>

