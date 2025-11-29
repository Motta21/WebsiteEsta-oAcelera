<?php
require_once "php/db_conection.php";

// Parâmetros recebidos via GET
$periodo = $_GET["periodo"] ?? "diario";
$topico  = $_GET["topico"] ?? "Temperatura";

// Colunas válidas exatamente como estão no banco
$colunasValidas = [
    "Temperatura",
    "Umidade",
    "Pressao",
    "Pressao_nivel_mar",
    "PTO_Orvalho"
];

// Validação
if (!in_array($topico, $colunasValidas)) {
    echo json_encode(["erro" => "Tópico inválido"]);
    exit;
}

// SQL conforme período solicitado
switch ($periodo) {
    case "diario":
        $sql = "SELECT DATE_FORMAT(DataHora,'%H:00') AS label, AVG($topico) AS valor
                FROM view_estacao
                WHERE DataHora >= NOW() - INTERVAL 1 DAY
                GROUP BY HOUR(DataHora)";
        break;

    case "semanal":
        $sql = "SELECT DATE_FORMAT(DataHora,'%d/%m') AS label, AVG($topico) AS valor
                FROM view_estacao
                WHERE DataHora >= NOW() - INTERVAL 7 DAY
                GROUP BY DATE(DataHora)";
        break;

    case "quinzenal":
        $sql = "SELECT DATE_FORMAT(DataHora,'%d/%m') AS label, AVG($topico) AS valor
                FROM view_estacao
                WHERE DataHora >= NOW() - INTERVAL 15 DAY
                GROUP BY DATE(DataHora)";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute();

$labels = [];
$dados = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $labels[] = $row["label"];
    $dados[]  = round($row["valor"], 2);
}

echo json_encode([
    "labels" => $labels,
    "dados"  => $dados
]);
?>


<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Amana — Gráficos</title>
    <script>
    (function() {
      const saved = sessionStorage.getItem('theme');
      const html = document.documentElement;
      html.setAttribute('data-theme', saved || 'light');
    })();
  </script>

  <link rel="stylesheet" href="estilo.css">
  <link rel="stylesheet" href="graficos.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="shortcut icon" href="/img/favicon_io/favicon.ico" type="image/x-icon">
</head>
<body>
  <aside class="sidebar collapsed" id="sidebar">
    <div class="brand">
      <button class="btn-icon toggle-sidebar" id="toggleSidebar"><i class="fa-solid fa-bars"></i></button>
    </div>

    <nav class="nav">
      <a class="nav-link" href="../index.html"><i class="fa-solid fa-cloud-sun-rain"></i><span>Início</span></a>
      <a class="nav-link" href="dashboard.html"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
      <a class="nav-link active" href="graficos.html"><i class="fa-solid fa-chart-line"></i><span>Gráficos</span></a>
      <a class="nav-link" href="historico.php"><i class="fa-solid fa-clock-rotate-left"></i><span>Histórico</span></a>
      <a class="nav-link" href="contato.html"><i class="fa-solid fa-address-book"></i><span>Contato</span></a>
      <a class="nav-link" href="patrocinadores.html"><i class="fa-solid fa-handshake"></i><span>Patrocinadores</span></a>
    </nav>
    <div class="sidebar-footer">
      <a class="nav-link small" href="https://thingspeak.com/" target="_blank" rel="noopener">
        <i class="fa-brands fa-think-peaks"></i><span>ThingSpeak</span>
      </a>
    </div>
  </aside>

  <main class="content">
    <header class="topbar">
      <div class="topbar-left">
        <h1>Estação Meteorológica</h1>
        <p class="subtitle">Análise detalhada de leituras meteorológicas</p>
      </div>
      <div class="topbar-right">
    <!-- Botão usuário -->
    <button class="btn-icon" id="userLogin" onclick="window.location.href='login.html'">
      <i class="fa-solid fa-user"></i>
    </button>

    <!-- Botão tema -->
    <button class="btn-icon" id="toggleTheme"><i class="fa-solid fa-moon"></i></button>

    <!-- Relógio -->
    <div class="clock" id="hora">--:--:--</div>
  </div>
    </header>

    <section id="graficos" class="section">
      <div class="section-head">
        <h2><i class="fa-solid fa-chart-line"></i> Análise Detalhada</h2>
        <div class="section-actions">
             <label class="select-wrap">
            <i class="fa-solid fa-calendar"></i>
        <select id="periodo">
            <option value="diario">Diário</option>
            <option value="semanal">Semanal</option>
            <option value="quinzenal">Quinzenal</option>
        </select>
  </label>

        <button class="btn" id="btnRefresh">
        <i class="fa-solid fa-rotate"></i> Atualizar
    </button>
</div>

      <div class="grid-charts">
        <div class="card chart-card">
          <div class="chart-title"><i class="fa-solid fa-temperature-half"></i> Temperatura (°C)</div>
          <div class="chart-wrap"><canvas id="graficoTemp"></canvas></div>
        </div>
        <div class="card chart-card">
          <div class="chart-title"><i class="fa-solid fa-droplet"></i> Umidade (%)</div>
          <div class="chart-wrap"><canvas id="graficoUmidade"></canvas></div>
        </div>
        <div class="card chart-card">
          <div class="chart-title"><i class="fa-solid fa-gauge"></i> Pressão (hPa)</div>
          <div class="chart-wrap"><canvas id="graficoPressao"></canvas></div>
        </div>
        <div class="card chart-card">
          <div class="chart-title"><i class="fa-solid fa-water"></i> Pressão Nível do Mar (hPa)</div>
          <div class="chart-wrap"><canvas id="graficoPressaoNM"></canvas></div>
        </div>
        <div class="card chart-card">
          <div class="chart-title"><i class="fa-solid fa-snowflake"></i> Ponto de Orvalho (°C)</div>
          <div class="chart-wrap"><canvas id="graficoOrvalho"></canvas></div>
        </div>
      </div>
    </section>
    

    <footer class="footer">
      <span>&copy; 2025 Amana — Central de Coleta de Dados Ambientais</span>
    </footer>
  </main>

  <div id="loader" class="loader hidden"><div class="spinner"></div><span>Carregando dados...</span></div>
  <div id="toast" class="toast" role="status" aria-live="polite"></div>
  <script src="js/script.js"></script>
  <script src="js/graficos.js"></script>
</body>
</html>

