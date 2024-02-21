<?php

$response = ['error' => false, 'message' => '' , 'data' => null];

$data = file_get_contents('php://input', true);

$response = ['error' => false, 'message' => 'file_get_contents' , 'data' => $data ];

echo json_encode(['message' => 'PUT request processed', 'data' => $data]);
exit;
