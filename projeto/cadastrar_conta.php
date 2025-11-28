<?php
require_once 'db_conection.php'; // usa o $pdo que você já criou

// Garante que veio via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html'); // ajusta o caminho se for outro arquivo
    exit;
}

// Pega os dados do formulário
$email           = trim($_POST['email'] ?? '');
$usuario         = trim($_POST['usuario'] ?? '');
$senha           = $_POST['senha'] ?? '';
$confirmarSenha  = $_POST['confirmar_senha'] ?? '';

// ===== Validações básicas =====
if ($email === '' || $usuario === '' || $senha === '' || $confirmarSenha === '') {
    die('Preencha todos os campos.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('E-mail inválido.');
}

if ($senha !== $confirmarSenha) {
    die('As senhas não conferem.');
}

// Gera o hash da senha (nunca salva a senha "pura")
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

try {
    // 1) Verificar se já existe conta com esse e-mail ou usuário
    $sqlVerifica = "SELECT ID_Conta FROM Conta 
                    WHERE Email = :email OR Usuario = :usuario
                    LIMIT 1";

    $stmt = $pdo->prepare($sqlVerifica);
    $stmt->execute([
        ':email'   => $email,
        ':usuario' => $usuario
    ]);

    if ($stmt->fetch()) {
        // Já existe uma conta com esse e-mail ou usuário
        die('E-mail ou usuário já cadastrado.');
    }

    // 2) Inserir nova conta
    $sqlInsert = "INSERT INTO Conta (Email, Usuario, Senha)
                  VALUES (:email, :usuario, :senha)";

    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute([
        ':email'   => $email,
        ':usuario' => $usuario,
        ':senha'   => $senhaHash
    ]);

    // Se chegou até aqui, deu certo
    // Você pode redirecionar para a tela de login, por exemplo
    header('Location: ../login.html'); // ajusta o caminho/arquivo
    exit;

} catch (PDOException $e) {
    // Erro no banco
    die('Erro ao cadastrar: ' . $e->getMessage());
}
