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

// teste de comunicação
echo json_encode(['message' => 'in index.php root', 'data' => ['method' => $method, 'request' => $request] ]);

// $response_data = ['error' => false, 'message' => '' , 'data' => null];

// // Obtém a URL da requisição
// // $request = $_SERVER['REQUEST_URI'];

// $file_get_contents = json_decode(file_get_contents("php://input"), true);

// $array_data = ['file_get_contents' => $data /*, 'request' => $request */];

// $response_data = ['error' => false, 'message' => 'file_get_contents' , 'data' =>  $array_data ];

// echo json_encode(['message' => 'PUT request processed', 'data' => $response_data]);

// $request = $_REQUEST['procurarcep'];

// echo json_encode(['message' => 'PUT request processed', 'data' => $request]);