<?php
$servidor = "localhost"; 
$usuario = "SIte";
$senha = "Rede@4584"; 
$banco = "Acelera2025"; 

try {
    // Inicializa a conexão PDO (PHP Data Objects)
    $pdo = new PDO("mysql:host=$servidor;dbname=$banco;charset=utf8", $usuario, $senha);
    
    // Configura o modo de erro para exceções, que facilita o debug
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // Interrompe a execução e exibe o erro se a conexão falhar
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// A partir daqui, a variável $pdo está disponível para qualquer script que incluir este arquivo.
?>