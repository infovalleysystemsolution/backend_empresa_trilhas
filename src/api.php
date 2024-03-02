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

            $response_data = [
                'error' => true, 'message' => "Falhou o processamento da requisição.", 'data' => []
            ];              
            // var_dump($data);
            if ($responseInsert['error'] == false) {
                $response_data = [
                    'error' => false, 'message' => "Sucesso ao executar a requisição.", 'data' => $responseInsert['data']
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
            $stmt->bindParam(':pais', $dados['nome_pais'], PDO::PARAM_STR);
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

echo json_encode([
    'error' => false, 'message' => "API local encontrou informações Estado no BD.", 
    'record_found' => $countFound, 'id' => $result['id'], 'nome' => $result['nome'], 
    'sigla' => $result['sigla'], 'country_id' => $result['country_id']
]);
exit; 

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
        
                        // Preparar a consulta para inserir na tabela 'pais'
                        $stmt = $conn->prepare("INSERT INTO pais (nome, sigla, uf_id) VALUES (:nome, :sigla, :uf_id)");
                        $stmt->bindParam(':nome', $dados['nome']);
                        $stmt->bindParam(':sigla', $dados['sigla']);
                        $stmt->bindParam(':uf_id', $dados['uf_id']);
                        $stmt->execute();
                    
                        // Recuperar o ID inserido na tabela 'pais'
                        $estadoId = $conn->lastInsertId();
        
                        return [
                            'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                            'record_found' => $countFound, 'id' => $estadoId, 'nome' => $dados['nome'], 'sigla' => $dados['sigla'], 'uf_id' => $dados['uf_id']
                        ];
                    }
                } else {
                    return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
                }
        
            } catch (PDOException $e) {
                return "Erro na consulta: " . $e->getMessage();
            }
}

function findCidade($dados) {
    /*
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
    
                    // Preparar a consulta para inserir na tabela 'pais'
                    $stmt = $conn->prepare("INSERT INTO bairro (nome, sigla, uf_id) VALUES (:nome, :sigla, :cidade_id)");
                    $stmt->bindParam(':nome', $dados['nome']);
                    $stmt->bindParam(':sigla', $dados['sigla']);
                    $stmt->bindParam(':cidade_id', $dados['cidade_id']);
                    $stmt->execute();
                
                    // Recuperar o ID inserido na tabela 'pais'
                    $estadoId = $conn->lastInsertId();
    
                    return [
                        'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                        'record_found' => $countFound, 'id' => $estadoId, 'nome' => $dados['nome'], 'sigla' => $dados['sigla'], 'uf_id' => $dados['uf_id']
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
        
                        // Preparar a consulta para inserir na tabela 'pais'
                        $stmt = $conn->prepare("INSERT INTO logradouro (logradouro, cep, uf_id) VALUES (:logradouro, :cep, :cidade_id)");
                        $stmt->bindParam(':logradouro', $dados['logradouro']);
                        $stmt->bindParam(':cep', $dados['cep']);
                        $stmt->bindParam(':bairro_id', $dados['bairro_id']);
                        $stmt->execute();
                    
                        // Recuperar o ID inserido na tabela 'pais'
                        $estadoId = $conn->lastInsertId();
        
                        return [
                            'error' => true, 'message' => "API local não encontrou informações Estado no BD.", 
                            'record_found' => $countFound, 'id' => $estadoId, 'nome' => $dados['nome'], 'cep' => $dados['cep'], 'bairro_id' => $dados['bairro_id']
                        ];
                    }
                } else {
                    return ['error' => true, 'message' => "Conexão falhou.", 'record_found' => $countFound,  'data' => null];
                }
        
            } catch (PDOException $e) {
                return "Erro na consulta: " . $e->getMessage();
            }
}

function findLogradouro($dados) {
    /*
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

function findBairro($dados) {
    /*
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
        $data['nome_pais'] = 'Brasil';    
        $reponse_pais = findPais($data);
        $data['pais_id'] = $paisId = $reponse_pais['id'];

        // Pegar id Estado function findPais($dados) {
        $reponse_estado = findEstado($data);
        // $data['estado_id'] = $estadoId = $reponse_estado['id'];
        // $data['estado_nome'] = $estadoId = $reponse_estado['nome'];
        $data['estado_id'] = $estadoId = 100;
        $data['estado_nome'] = $estadoId =  'Minas Gerais';

        echo json_encode($data);

exit;               
        



        // Pegar id Cidade ou cadastrar Cidade
        $dadosCidade['nome'] = $data['localidade'];
        $data['uf_id'] = $dadosCidade['uf_id'] = $estadoId;
        $reponse_cidade = findInsertCidade($dadosCidade);
        $cidadeId = $reponse_cidade['id'];
        // Pegar id Bairro ou cadastrar Bairro
        $dadosBairro['nome'] = $data['bairro'];
        $data['cidade_id'] = $dadosBairro['cidade_id'] = $cidadeId;
        $reponse_bairro = findInsertBairro($dadosBairro);   
        $bairroId = $reponse_bairro['id'];
        // Pegar id Logradouro ou cadastrar Logradouro
        $dadosLogradouro['logradouro'] = $data['logradouro'];    
        $data['bairro_id'] = $dadosLogradouro['bairro_id'] = $bairroId;
        $reponse_logradouro = findInsertLogradouro($dadosCidade);
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