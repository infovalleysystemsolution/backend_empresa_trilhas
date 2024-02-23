<?php

include_once "config_db.php";

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
        if ($conn != null) {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':cep', $cep, PDO::PARAM_STR);
            $stmt->execute();
    
            // Obter resultados
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            // retorna os resultados
            if (count($result) > 0) {
                return $result;
            } else {
                return [];
            }
        } else {
            return "Conexão falhou.";
        }


    } catch (PDOException $e) {
        return "Erro na consulta: " . $e->getMessage();
    }

}


