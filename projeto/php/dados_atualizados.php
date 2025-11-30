<?php
header("Content-Type: application/json; charset=utf-8");
require_once "db_conection.php";

// Garante que sempre teremos um número inteiro
$codE = isset($_GET['Cod_E']) ? intval($_GET['Cod_E']) : 1;

try {
    $sql = "SELECT * 
            FROM view_estacao 
            WHERE Cod_E = :cod 
            ORDER BY DataHora DESC 
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":cod", $codE, PDO::PARAM_INT);
    $stmt->execute();
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        echo json_encode([
            "sucesso" => false,
            "erro" => "Nenhum dado encontrado para Cod_E = $codE"
        ]);
        exit;
    }

    echo json_encode([
        "sucesso" => true,
        "dados" => $dados
    ]);

} catch (Exception $e) {
    echo json_encode([
        "sucesso" => false,
        "erro" => "Erro no servidor",
        "detalhes" => $e->getMessage()
    ]);
}
