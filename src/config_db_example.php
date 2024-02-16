<?php

$servername = "hostofbd";
$username = "userbd";
$password = "passbd";
$database = "bd";

try {

    // Criar uma nova conexão usando PDO
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);

    // Configurar o PDO para lançar exceções em erros
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // echo "Conexão com o banco de dados  bem-sucedida ...";

    // Aqui você pode realizar operações no banco de dados

    // Fechar a conexão quando terminar
    $conn = null;
} catch (PDOException $e) {

    // Capturar exceções em caso de erro na conexão
    echo "Conexão falhou: " . $e->getMessage();

}

?>

