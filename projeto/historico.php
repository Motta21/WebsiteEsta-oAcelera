<?php
// 1. INCLUSÃO DA CONEXÃO
// O 'require_once' busca o arquivo e torna a variável $pdo disponível
require_once 'db_conexao.php'; 

// 2. CONFIGURAÇÃO E CONSULTA À VIEW
$view_dados = "view_estacao";
// É altamente recomendado ordenar e limitar a consulta, especialmente em histórico
$sql = "SELECT * FROM $view_dados ORDER BY data_hora DESC LIMIT 100"; 

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Em caso de falha na consulta (ex: nome da view errado)
    $erro_consulta = "Erro ao consultar a View: " . $e->getMessage();
    $registros = []; // Define $registros como vazio para não quebrar a exibição
}

?>

<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WeatherMonitor — Histórico</title>
  <script>
    (function() {
      const saved = sessionStorage.getItem('theme');
      const html = document.documentElement;
      html.setAttribute('data-theme', saved || 'light');
    })();
  </script>
  
  <link rel="stylesheet" href="estilo.css">
  <link rel="stylesheet" href="historico.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <aside class="sidebar collapsed" id="sidebar">
    <div class="brand">
      <button class="btn-icon toggle-sidebar" id="toggleSidebar"><i class="fa-solid fa-bars"></i></button>
    </div>
    
    <nav class="nav">
      <a class="nav-link" href="../index.html"><i class="fa-solid fa-cloud-sun-rain"></i><span>Início</span></a>
      <a class="nav-link" href="dashboard.html"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
      <a class="nav-link" href="graficos.html"><i class="fa-solid fa-chart-line"></i><span>Gráficos</span></a>
      <a class="nav-link active" href="historico.php"><i class="fa-solid fa-clock-rotate-left"></i><span>Histórico</span></a>
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
        <p class="subtitle">Histórico de leituras meteorológicas</p>
      </div>
      <div class="topbar-right">
    <button class="btn-icon" id="userLogin" onclick="window.location.href='login.html'">
      <i class="fa-solid fa-user"></i>
    </button>

    <button class="btn-icon" id="toggleTheme"><i class="fa-solid fa-moon"></i></button>

    <div class="clock" id="hora">--:--:--</div>
  </div>
    </header>

    <section id="historico" class="section">
      <div class="section-head">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Histórico de Leituras</h2>
        <div class="section-actions">
          <label class="search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="buscaTabela" placeholder="Buscar na tabela..." />
          </label>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table" id="tabelaHistorico">
          <thead>
            <tr>
              <th data-sort="data">Data/Hora</th>
              <th data-sort="num">Temp (°C)</th>
              <th data-sort="num">Umidade (%)</th>
              <th data-sort="num">Pressão (hPa)</th>
              <th data-sort="num">Pressão Nível do Mar (hPa)</th>
              <th data-sort="num">Ponto de Orvalho (°C)</th>
            </tr>
          </thead>
          
          <tbody id="historico-body">
          <?php 
          if (isset($erro_consulta)) {
             // Exibe o erro de consulta, se houver
             echo '<tr><td colspan="6" style="text-align: center; color: red;">' . htmlspecialchars($erro_consulta) . '</td></tr>';
          }
          else if (count($registros) > 0) { 
              foreach ($registros as $linha) {
                  // Certifique-se de que os nomes das colunas são exatamente iguais aos da sua VIEW!
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($linha['data_hora']) . "</td>";
                  echo "<td>" . htmlspecialchars($linha['temperatura']) . "</td>";
                  echo "<td>" . htmlspecialchars($linha['umidade']) . "</td>";
                  echo "<td>" . htmlspecialchars($linha['pressao']) . "</td>";
                  echo "<td>" . htmlspecialchars($linha['pressao_nivel_mar']) . "</td>";
                  echo "<td>" . htmlspecialchars($linha['ponto_orvalho']) . "</td>";
                  echo "</tr>";
              }
          } else {
              // Exibe uma mensagem se a consulta não retornou dados
              echo '<tr><td colspan="6" style="text-align: center;">Nenhum registro encontrado na View: ' . htmlspecialchars($view_dados) . '</td></tr>';
          }
          ?>
          </tbody>
        </table>
      </div>
    </section>
    
    <footer class="footer">
      <span>&copy; 2025 WeatherMonitor — Estação Meteorológica</span>
    </footer>
  </main>

  <div id="loader" class="loader hidden"><div class="spinner"></div><span>Carregando dados...</span></div>
  <div id="toast" class="toast" role="status" aria-live="polite"></div>
  <script src="script.js"></script>
</body>
</html>