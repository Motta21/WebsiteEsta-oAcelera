// js/graficos.js
// Requer Chart.js já carregado na página
(() => {
  const API_URL = 'php/getDados.php'; // ajuste se o nome/rota for diferente

  // IDs dos canvases na página
  const CHARTS_INFO = {
    Temperatura: 'graficoTemp',
    Umidade: 'graficoUmidade',
    Pressao: 'graficoPressao',
    Pressao_nivel_mar: 'graficoPressaoNM',
    PTO_Orvalho: 'graficoOrvalho'
  };

  const charts = {}; // instâncias Chart.js
  let currentPeriodo = document.getElementById('periodo')?.value || 'diario';
  const loader = document.getElementById('loader');
  const toast = document.getElementById('toast');
  const btnRefresh = document.getElementById('btnRefresh');

  // UTIL: mostrar/ocultar loader
  function showLoader() { loader && loader.classList.remove('hidden'); }
  function hideLoader() { loader && loader.classList.add('hidden'); }

  // UTIL: toast simples
  let toastTimer = null;
  function showToast(msg, timeout = 3500) {
    if (!toast) return console.warn('Toast element não encontrado:', msg);
    toast.textContent = msg;
    toast.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('visible'), timeout);
  }

  // UTIL: destrói gráfico se já existir
  function destroyChartIfExists(key) {
    if (charts[key]) {
      try { charts[key].destroy(); } catch (e) { /* ignore */ }
      delete charts[key];
    }
  }

  // Cria um gráfico de linha simples, opcionalmente com preenchimento sutil
  function createLineChart(canvasId, label, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) {
      console.error('Canvas não encontrado:', canvasId);
      return null;
    }

    // Ajusta altura responsiva
    ctx.style.height = '220px';

    return new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: data,
          tension: 0.28,
          borderWidth: 2,
          pointRadius: 2,
          pointHoverRadius: 4,
          fill: true,
          backgroundColor: (context) => {
            // gradiente suave (se suportado)
            const c = context.chart.ctx;
            const gradient = c.createLinearGradient(0, 0, 0, context.chart.height);
            gradient.addColorStop(0, 'rgba(0,0,0,0.06)');
            gradient.addColorStop(1, 'rgba(0,0,0,0.00)');
            return gradient;
          }
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: true,
            mode: 'index',
            intersect: false
          }
        },
        scales: {
          x: {
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
            grid: { display: false }
          },
          y: {
            ticks: { beginAtZero: false },
            grid: { color: 'rgba(0,0,0,0.04)' }
          }
        }
      }
    });
  }

  // Função principal que busca dados e atualiza os 5 charts
  async function atualizarTodos() {
    currentPeriodo = document.getElementById('periodo')?.value || currentPeriodo;
    showLoader();
    try {
      const resp = await fetch(`${API_URL}?periodo=${encodeURIComponent(currentPeriodo)}`);
      if (!resp.ok) throw new Error(`Resposta HTTP ${resp.status}`);
      const json = await resp.json();

      // Validação mínima do formato A
      if (!json || !Array.isArray(json.datas)) {
        showToast('Formato de dados inválido do servidor', 5000);
        console.error('Resposta inesperada:', json);
        return;
      }

      const labels = json.datas.map(d => d); // espera strings formatadas

      // Mapeia cada métrica esperada (chaves minúsculas)
      const datasetsMap = {
        Temperatura: json.temperatura || json.Temperatura || [],
        Umidade: json.umidade || json.Umidade || [],
        Pressao: json.pressao || json.Pressao || [],
        Pressao_nivel_mar: json.pressao_nivel_mar || json.Pressao_nivel_mar || [],
        PTO_Orvalho: json.pto_orvalho || json.PTO_Orvalho || []
      };

      // Atualiza cada gráfico
      for (const [metric, canvasId] of Object.entries(CHARTS_INFO)) {
        const values = datasetsMap[metric] || [];
        // Se tamanhos divergirem, tenta ajustar (preenchendo vazio)
        const safeValues = labels.map((_, i) => (typeof values[i] !== 'undefined' ? values[i] : null));

        destroyChartIfExists(metric);

        charts[metric] = createLineChart(canvasId, metric.replace(/_/g, ' '), labels, safeValues);
      }

      hideLoader();
      showToast('Gráficos atualizados', 1200);
    } catch (err) {
      hideLoader();
      console.error('Erro ao buscar dados:', err);
      showToast('Falha ao carregar dados. Veja console.', 5000);
    }
  }

  // Debounce simples para evitar múltiplas requisições rápidas
  function debounce(fn, wait = 300) {
    let t = null;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }

  // Eventos
  if (btnRefresh) btnRefresh.addEventListener('click', debounce(atualizarTodos, 150));
  const periodoSelect = document.getElementById('periodo');
  if (periodoSelect) periodoSelect.addEventListener('change', debounce(atualizarTodos, 200));

  // Carrega automaticamente na abertura
  window.addEventListener('load', () => {
    // pequena espera para a UI estabilizar
    setTimeout(atualizarTodos, 250);
  });

  // Expor função para console (debug)
  window.atualizarGraficos = atualizarTodos;
})();
