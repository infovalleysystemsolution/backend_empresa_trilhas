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
            $data['api'] = 'viacep';                
            $data['pais'] = 'Brasil';

            // Inserir dados da CEP na base de dados  local            
            $responseInsert = insertCEP($data);

            /*
                    // Extrair informações relevantes
                    $cep = $data['cep'];
                    $logradouro = $data['logradouro'];
                    $complemento = $data['complemento'];
                    $bairro = $data['bairro'];
                    $localidade = $data['localidade'];
                    $uf = $data['uf'];
                    $ibge = $data['ibge'];
                    $ddd = $data['ddd'];

                    // Pegar id País function findPais($dados) {
                    $data['pais_nome'] = 'Brasil';    
                    $response_pais = findPais($data);
                    $data['pais_id'] = $response_pais['id'];
                    $data['pais_sigla'] = $response_pais['sigla'];
                    
                    // Pegar id Estado function findPais($dados) {
                    $response_estado = findEstado($data);
                    $data['estado_id'] = $response_estado['id'];
                    // $data['estado_nome'] = $response_estado['nome'];
                    // $data['estado_sigla'] = $response_estado['sigla'];

                    // Pegar id Cidade ou cadastrar Cidade
                    $response_cidade = findInsertCidade($data);
                    $data['cidade_id'] = $response_cidade['id'];    
                    // $data['cidade_nome'] = $response_cidade['nome'];
                    
                    // Pegar id Bairro ou cadastrar Bairro
                    $reponse_bairro = findInsertBairro($data);   
                    $data['bairro_id'] = $reponse_bairro['id'];
                    
                    // Pegar id Logradouro ou cadastrar Logradouro  
                    $reponse_logradouro = findInsertLogradouro($data);
                    $data['logradouro_id'] = $reponse_logradouro['id'];
            */            

            // var_dump($data);
            if ($responseInsert['error'] == false) {
                $response_data = [
                    'error' => false,  'data' => $responseInsert['data']
                ];                
            } else {
                $response_data = [
                    'error' => true, 'message' => "Sucesso ao executar a requisição. Não foi possível inserir ou recuperar os dados."
                ];                
            }
        }
        curl_close($ch);
    }
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
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
    
            // contando os registros retornados
            $countFound = $stmt->rowCount();

            if ($countFound > 0) {
                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações CEP no BD.", 
                    'record_found' => $countFound, 'data' => $result
                ];
            } else {
                $countFound = 0;
                return [
                    'error' => true, 'message' => "API local não encontrou informações CEP no BD.", 
                    'record_found' => $countFound, 'data' => []
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => []];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }

}

function findInsertPais($dados) {

    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT id, nome, sigla FROM pais WHERE nome = :pais    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nome', $dados['nome'], PDO::PARAM_STR);
            $stmt->execute();
    
            // contando os registros retornados
            $countFound = $stmt->rowCount();

            if ($countFound > 0) {
                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informacoes do Pais no BD.", 
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome'], 'sigla' => $result['sigla']
                ];
            } else {
                $countFound = 0;

                // Preparar a consulta para inserir na tabela 'pais'
                $stmt = $conn->prepare("INSERT INTO pais (nome, sigla, continente) VALUES (:nome, :sigla, :continente)");
                $stmt->bindParam(':nome', $dados['nome']);
                $stmt->bindParam(':sigla', $dados['sigla']);
                $stmt->bindParam(':continente', $dados['continente']);
                $stmt->execute();
            
                // Recuperar o ID inserido na tabela 'pais'
                $paisId = $conn->lastInsertId();

                return [
                    'error' => true, 'message' => "API local não encontrou informações CEP no BD.", 
                    'record_found' => $countFound, 'id' => $paisId, 'nome' => $dados['nome'], 'sigla' => $dados['sigla']
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}


function findPais($dados) {

    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT id, nome_pt, sigla FROM pais WHERE nome_pt = :pais    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':pais', $dados['pais_nome'], PDO::PARAM_STR);
            $stmt->execute();
    
            // contando os registros retornados
            $countFound = $stmt->rowCount();

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações País no BD.", 
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome_pt'], 'sigla' => $result['sigla']
                ];
            } else {
                $countFound = 0;

                return [
                    'error' => true, 'message' => "API local não encontrou informações CEP no BD.", 
                    'record_found' => $countFound
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findInsertEstado($dados) {
/*
CREATE TABLE `uf` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_uf` bigint(20) DEFAULT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `sigla` varchar(5) DEFAULT NULL,
  `ibge` int(2) DEFAULT NULL,
  `country_id` bigint(20) DEFAULT NULL,
  `ddd` varchar(50) DEFAULT NULL,
  `capital` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `uf_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `pais` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4;
*/
    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT id, nome, sigla, country_id FROM uf WHERE nome = :nome    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nome', $dados['nome'], PDO::PARAM_STR);
            $stmt->execute();

            // contando os registros retornados
            $countFound = $stmt->rowCount();
    
            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações Estado no BD.", 
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome'], 'sigla' => $result['sigla'], 'country_id' => $result['country_id']
                ];
            } else {
                $countFound = 0;

                // Preparar a consulta para inserir na tabela 'pais'
                $stmt = $conn->prepare("INSERT INTO uf (nome, sigla, country_id) VALUES (:nome, :sigla, :country_id)");
                $stmt->bindParam(':nome', $dados['nome']);
                $stmt->bindParam(':sigla', $dados['sigla']);
                $stmt->bindParam(':country_id', $dados['country_id']);
                $stmt->execute();
            
                // Recuperar o ID inserido na tabela 'pais'
                $estadoId = $conn->lastInsertId();

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_found' => $countFound, 'id' => $estadoId, 'nome' => $dados['nome'], 'sigla' => $dados['sigla'], 
                    'country_id' => $dados['country_id']
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findEstado($dados) {
        /*
        Schema:
        CREATE TABLE `uf` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `id_uf` bigint(20) DEFAULT NULL,
        `nome` varchar(255) DEFAULT NULL,
        `sigla` varchar(5) DEFAULT NULL,
        `ibge` int(2) DEFAULT NULL,
        `country_id` bigint(20) DEFAULT NULL,
        `ddd` varchar(50) DEFAULT NULL,
        `capital` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `country_id` (`country_id`),
        CONSTRAINT `uf_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `pais` (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4;
        */
        // Consulta SQL com joins para obter os dados desejados
        $sql = "SELECT id, nome, sigla, country_id FROM uf WHERE sigla = :sigla1";
       
        try {
    
            $conn = conectBD();
            $countFound = 0;
    
            if ($conn != null) {
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':sigla1', $dados['uf'], PDO::PARAM_STR);
                $stmt->execute();

                // contando os registros retornados
                $countFound = $stmt->rowCount();

                // retorna os resultados
                if ($countFound > 0) {

                    // Obter resultados
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    return [
                        'error' => false, 'message' => "API local encontrou informações Estado no BD.", 
                        'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome'], 
                        'sigla' => $result['sigla'], 'country_id' => $result['country_id']
                    ];
                } else {
                    $countFound = 0;
    
                    return [
                        'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                        'record_found' => $countFound
                    ];
                }
            } else {
                return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
            }
    
        } catch (PDOException $e) {
            return "Erro na consulta: " . $e->getMessage();
        }
}

function findInsertCidade($dados) {
    /*
    Schema:
        CREATE TABLE `cidade` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `nome` varchar(255) DEFAULT NULL,
        `ddd` varchar(5) DEFAULT NULL,
        `uf_id` bigint(20) DEFAULT NULL,
        `ibge` int(7) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `uf_id` (`uf_id`),
        CONSTRAINT `cidade_ibfk_1` FOREIGN KEY (`uf_id`) REFERENCES `uf` (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=5610 DEFAULT CHARSET=utf8mb4;
    */
    // Consulta SQL com joins para obter os dados desejados  
    $sql = "SELECT c.id As cidade_id, c.nome As cidade_nome, c.uf_id As estado_id, u.nome As estado_nome, 
                u.sigla As estado_sigla, u.country_id As pais_id  
            FROM cidade As c 
            INNER JOIN uf As u on c.uf_id = u.id 
            WHERE 
            c.nome = :localidade";

    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':localidade', $dados['localidade'], PDO::PARAM_STR);
            $stmt->execute();

            // contando os registros retornados
            $countFound = $stmt->rowCount(); 

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações da Cidade no BD.", 
                    'record_insert' => false,
                    'record_found' => $countFound, 'id' => $result['cidade_id'], 'nome' => $result['cidade_nome']
                ];
            } else {
                $countFound = 0;

                // Preparar a consulta para inserir na tabela 'pais'
                $stmt = $conn->prepare("INSERT INTO cidade (nome, uf_id) VALUES (:nome, :uf_id)");
                $stmt->bindParam(':nome', $dados['localidade']);
                $stmt->bindParam(':uf_id', $dados['estado_id']);
                $stmt->execute();
            
                // Recuperar o ID inserido na tabela 'pais'
                $cidadeId = $conn->lastInsertId();

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_insert' => true, 'id' => $cidadeId, 'nome' => $dados['localidade'], 'uf_id' => $dados['estado_id']
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findCidade($dados) { // NÃO TESTADO 
    /*
    Schema:
    CREATE TABLE `cidade` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `nome` varchar(255) DEFAULT NULL,
    `ddd` varchar(5) DEFAULT NULL,
    `uf_id` bigint(20) DEFAULT NULL,
    `ibge` int(7) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `uf_id` (`uf_id`),
    CONSTRAINT `cidade_ibfk_1` FOREIGN KEY (`uf_id`) REFERENCES `uf` (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=5610 DEFAULT CHARSET=utf8mb4;
    */
    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT id, nome, uf_id FROM cidade WHERE nome = :nome    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nome', $dados['nome'], PDO::PARAM_STR);
            $stmt->execute();

            // contando os registros retornados
            $countFound = $stmt->rowCount(); 

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações da Cidade no BD.", 
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome'], 'sigla' => $result['sigla'], 
                    'uf_id' => $result['uf_id']
                ];
            } else {
                $countFound = 0;

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_found' => $countFound
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findInsertBairro($dados) {
    /*
    Schema:
    CREATE TABLE `bairro` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `nome` varchar(255) DEFAULT NULL,
    `cidade_id` bigint(20) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `cidade_id` (`cidade_id`),
    CONSTRAINT `bairro_ibfk_1` FOREIGN KEY (`cidade_id`) REFERENCES `cidade` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    */
    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT b.id, b.nome, b.cidade_id, c.nome As cidade_nome
            FROM bairro As b 
            INNER JOIN cidade As c on b.cidade_id = c.id 
            WHERE b.nome = :bairro";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':bairro', $dados['bairro'], PDO::PARAM_STR);
            $stmt->execute();
    
            // contando os registros retornados
            $countFound = $stmt->rowCount(); 

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações da Cidade no BD.", 
                    'record_insert' => false,
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome']
                ];
            } else {
                $countFound = 0;

                // Preparar a consulta para inserir na tabela 'pais'
                $stmt = $conn->prepare("INSERT INTO bairro (nome, cidade_id) VALUES (:nome, :cidade_id)");
                $stmt->bindParam(':nome', $dados['bairro']);
                $stmt->bindParam(':cidade_id', $dados['cidade_id']);
                $stmt->execute();
            
                // Recuperar o ID inserido na tabela 'pais'
                $bairroId = $conn->lastInsertId();

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_insert' => true,
                    'record_found' => $countFound, 'id' => $bairroId, 'nome' => $dados['nome']
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findInsertLogradouro($dados) {
    /*
    Schema:
    CREATE TABLE `logradouro` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `cep` varchar(10) DEFAULT NULL,
    `logradouro` varchar(255) DEFAULT NULL,
    `bairro_id` bigint(20) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cep` (`cep`),
    KEY `bairro_id` (`bairro_id`),
    CONSTRAINT `logradouro_ibfk_1` FOREIGN KEY (`bairro_id`) REFERENCES `bairro` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    */
    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT l.id, l.logradouro, l.cep  
            FROM logradouro As l
            INNER JOIN bairro As b on l.bairro_id = b.id 
            WHERE l.logradouro = :nome";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nome', $dados['logradouro'], PDO::PARAM_STR);
            $stmt->execute();

            // contando os registros retornados
            $countFound = $stmt->rowCount();

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações da Cidade no BD.", 
                    'record_insert' => false,
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['logradouro'], 
                    'cep' => $result['cep']
                ];
            } else {
                $countFound = 0;

                // Preparar a consulta para inserir na tabela 'pais'
                $stmt = $conn->prepare("INSERT INTO logradouro (logradouro, cep, bairro_id) VALUES (:logradouro, :cep, :bairro_id)");
                $stmt->bindParam(':logradouro', $dados['logradouro']);
                $stmt->bindParam(':cep', $dados['cep']);
                $stmt->bindParam(':bairro_id', $dados['bairro_id']);
                $stmt->execute();
            
                // Recuperar o ID inserido na tabela 'pais'
                $logradouroId = $conn->lastInsertId();

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_insert' => true,
                    'record_found' => $countFound, 'id' => $logradouroId, 'nome' => $dados['logradouro'], 
                    'cep' => $dados['cep']
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findLogradouro($dados) {  // NÃO TESTADO
    /*
    Schema:
    CREATE TABLE `logradouro` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `cep` varchar(10) DEFAULT NULL,
      `logradouro` varchar(255) DEFAULT NULL,
      `bairro_id` bigint(20) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_cep` (`cep`),
      KEY `bairro_id` (`bairro_id`),
      CONSTRAINT `logradouro_ibfk_1` FOREIGN KEY (`bairro_id`) REFERENCES `bairro` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    */
    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT id, logradouro, cep, bairro_id FROM logradouro WHERE nome = :nome    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nome', $dados['nome'], PDO::PARAM_STR);
            $stmt->execute();

            // contando os registros retornados
            $countFound = $stmt->rowCount();

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações da Cidade no BD.", 
                    'record_found' => $countFound, 'id' => $result['id'], 'logradouro' => $result['logradouro'], 'cep' => $result['cep'], 
                    'bairro_id' => $result['bairro_id']
                ];
            } else {
                $countFound = 0;

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_found' => $countFound
                ];
            }
        } else {
            return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
        }

    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }
}

function findBairro($dados) {   // NÃO TESTADO
    /*
    Schema:
    CREATE TABLE `bairro` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `nome` varchar(255) DEFAULT NULL,
      `cidade_id` bigint(20) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `cidade_id` (`cidade_id`),
      CONSTRAINT `bairro_ibfk_1` FOREIGN KEY (`cidade_id`) REFERENCES `cidade` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    */
    // Consulta SQL com joins para obter os dados desejados
    $sql = "SELECT id, nome, cidade_id FROM bairro WHERE nome = :nome    ";
    
    try {

        $conn = conectBD();
        $countFound = 0;

        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nome', $dados['nome'], PDO::PARAM_STR);
            $stmt->execute();

            // contando os registros retornados
            $countFound = $stmt->rowCount();

            // retorna os resultados
            if ($countFound > 0) {

                // Obter resultados
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'error' => false, 'message' => "API local encontrou informações da Cidade no BD.", 
                    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome'], 'sigla' => $result['sigla'], 
                    'cidade_id' => $result['cidade_id']
                ];
            } else {
                $countFound = 0;

                return [
                    'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                    'record_found' => $countFound
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
    
    try {

        // Pegar id País function findPais($dados) {
        $data['pais_nome'] = 'Brasil';    
        $response_pais = findPais($data);
        $data['pais_id'] = $response_pais['id'];
        $data['pais_sigla'] = $response_pais['sigla'];
        
        // Pegar id Estado function findPais($dados) {
        $response_estado = findEstado($data);
        $data['estado_id'] = $response_estado['id'];
        // $data['estado_nome'] = $response_estado['nome'];
        // $data['estado_sigla'] = $response_estado['sigla'];

        // Pegar id Cidade ou cadastrar Cidade
        $response_cidade = findInsertCidade($data);
        $data['cidade_id'] = $response_cidade['id'];    
        // $data['cidade_nome'] = $response_cidade['nome'];
        
        // Pegar id Bairro ou cadastrar Bairro
        $reponse_bairro = findInsertBairro($data);   
        $data['bairro_id'] = $reponse_bairro['id'];
        
        // Pegar id Logradouro ou cadastrar Logradouro  
        $reponse_logradouro = findInsertLogradouro($data);
        $data['logradouro_id'] = $reponse_logradouro['id'];

        return ["error" => false, "data" => $data ];
    
    } catch (PDOException $e) {
        return [ "error" => true, "message" => "Erro: " . $e->getMessage() ];
    }
}


/*

        $stmt->execute();    
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
*/