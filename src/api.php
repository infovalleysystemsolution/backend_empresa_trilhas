<?php

$conn = null;

include_once "config_db.php";


function searchLocalZipcodeExternalApi($cep) {
    // remover todos os caracteres na variável $cep que não forem numéricos
    $cep = preg_replace("/[^0-9]/", "", $cep);

    if (!is_numeric($cep) || $cep == "" || strlen($cep) != 8) {
        echo "Digite um CEP válido (somente números e com 8 dígitos)";
        return false;
    }

    $result = findCEP($cep);

    // se informações existirem no banco de dados local, faz uso dos dados locais,
    // caso não exista fzz a consulta na API viacep
    if ($result['record_found'] > 0) {
        // $reponse = [];
        // $reponse['api'] = 'local';
        // $reponse['cep'] = $result['cep'];
        // $reponse['logradouro'] = $result['logradouro'];
        // $reponse['bairro'] = $result['nome_bairro'];
        // $reponse['localidade'] = $result['nome_cidade'];
        // $reponse['uf'] = $result['nome_uf'];
        // $reponse['pais'] = $result['nome_pais'];
        $response_data = $result;
    } else {
        $url = 'https://viacep.com.br/ws/' . $cep . '/json/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));       
        $response = curl_exec($ch);
        
        if($response === false){
            $response_data = ['error' => true, 'message' => "Ocorreu um erro ao executar a requisição: " . curl_error($ch), 'data' => null];
        } else {

            $data = json_decode($response, true);
            // Inserir dados da CEP na base de dados  local            
            $responseInsert = insertCEP($data);

            $data['api'] = 'viacep';                
            $data['pais'] = 'Brasil';

            // var_dump($data);
            $response_data = ['error' => false, 'message' => "Sucesso ao executar a requisição.", 'data' => json_decode($response, true)];            
        }
        
        curl_close($ch);
    }

    echo json_encode($response_data);

}

function findCEP($cep) {

    // Consulta SQL com joins para obter os dados desejados
    $sql = "
        SELECT
            logradouro.cep,
            logradouro.logradouro,
            bairro.nome AS nome_bairro,
            cidade.nome AS nome_cidade,
            uf.nome AS nome_uf,
            uf.sigla AS sigla_uf,
            pais.nome AS nome_pais
        FROM
            logradouro
        LEFT JOIN bairro ON logradouro.bairro_id = bairro.id
        LEFT JOIN cidade ON bairro.cidade_id = cidade.id
        LEFT JOIN uf ON cidade.uf_id = uf.id
        LEFT JOIN pais ON uf.country_id = pais.id
        WHERE
            logradouro.cep = :cep
    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':cep', $cep, PDO::PARAM_STR);
            $stmt->execute();
    
            // Obter resultados
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            // retorna os resultados
            if ($countFound = count($result) > 0) {
                return [
                    'error' => false, 'message' => "API local encontrou informações CEP no BD.", 
                    'record_found' => $countFound, 'data' => $result
                ];
            } else {
                $countFound = 0;
                return [
                    'error' => true, 'message' => "API local não encontrou informações CEP no BD.", 
                    'record_found' => $countFound, 'data' => null
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }

}


function insertCEP($data) {


    // Dados do JSON
    /* $jsonData = '{"error":false,"message":"","data":
        {"error":false,"message":"Sucesso ao executar a requisi\u00e7\u00e3o.",
            "data":{"cep":"35162-289","logradouro":"Avenida Orqu\u00eddea","complemento":"at\u00e9 328\/329","bairro":"Esperan\u00e7a","localidade":"Ipatinga","uf":"MG","ibge":"3131307","gia":"","ddd":"31","siafi":"4625"}
    }
    }';
*/    
$data[ "insertCEP"] = true;


    // Extrair informações relevantes
    $cep = $data['cep'];
    $logradouro = $data['logradouro'];
    $complemento = $data['complemento'];
    $bairro = $data['bairro'];
    $localidade = $data['localidade'];
    $uf = $data['uf'];
    $ibge = $data['ibge'];
    $ddd = $data['ddd'];


echo json_encode($localidade);

exit;
return false;
    

    
    try {
        // Preparar a consulta para inserir na tabela 'pais'
        $stmt = $conn->prepare("INSERT INTO pais (nome, sigla) VALUES (:nome, :sigla)");
        $stmt->bindParam(':nome', $data['data']['data']['localidade']);
        $stmt->bindParam(':sigla', $data['data']['data']['uf']);
        $stmt->execute();
    
        // Recuperar o ID inserido na tabela 'pais'
        $paisId = $conn->lastInsertId();
    
        // Preparar a consulta para inserir na tabela 'uf'
        $stmt = $conn->prepare("INSERT INTO uf (nome, sigla, country_id) VALUES (:nome, :sigla, :country_id)");
        $stmt->bindParam(':nome', $data['data']['data']['uf']);
        $stmt->bindParam(':sigla', $data['data']['data']['uf']);
        $stmt->bindParam(':country_id', $paisId);
        $stmt->execute();
    
        // Recuperar o ID inserido na tabela 'uf'
        $ufId = $conn->lastInsertId();
    
        // Preparar a consulta para inserir na tabela 'cidade'
        $stmt = $conn->prepare("INSERT INTO cidade (nome, uf_id) VALUES (:nome, :uf_id)");
        $stmt->bindParam(':nome', $data['data']['data']['localidade']);
        $stmt->bindParam(':uf_id', $ufId);
        $stmt->execute();
    
        // Recuperar o ID inserido na tabela 'cidade'
        $cidadeId = $conn->lastInsertId();
    
        // Preparar a consulta para inserir na tabela 'bairro'
        $stmt = $conn->prepare("INSERT INTO bairro (nome, cidade_id) VALUES (:nome, :cidade_id)");
        $stmt->bindParam(':nome', $data['data']['data']['bairro']);
        $stmt->bindParam(':cidade_id', $cidadeId);
        $stmt->execute();
    
        // Recuperar o ID inserido na tabela 'bairro'
        $bairroId = $conn->lastInsertId();
    
        // Preparar a consulta para inserir na tabela 'logradouro'
        $stmt = $conn->prepare("INSERT INTO logradouro (cep, logradouro, bairro_id) VALUES (:cep, :logradouro, :bairro_id)");
        $stmt->bindParam(':cep', $cep);
        $stmt->bindParam(':logradouro', $logradouro);
        $stmt->bindParam(':bairro_id', $bairroId);
        $stmt->execute();
    
        echo "Dados inseridos com sucesso.";
    
    } catch (PDOException $e) {
        echo "Erro: " . $e->getMessage();
    }   

}