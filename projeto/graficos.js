// graficos.js
// Carrega e gera os gráficos usando Chart.js

(() => {
  const API_URL = "php/dados_grafico.php";

  const charts = {}; // Instâncias Chart.js

  const chartMap = {
    temperatura: "graficoTemp",
    umidade: "graficoUmidade",
    pressao: "graficoPressao",
    pressao_nm: "graficoPressaoNM",
    orvalho: "graficoOrvalho"
  };

  const loader = document.getElementById("loader");
  const toast = document.getElementById("toast");

  function showLoader() { loader.classList.remove("hidden"); }
  function hideLoader() { loader.classList.add("hidden"); }

  function showToast(msg, time = 3000) {
    toast.textContent = msg;
    toast.classList.add("visible");
    setTimeout(() => toast.classList.remove("visible"), time);
  }

  function destroy(chartName) {
    if (charts[chartName]) {
      charts[chartName].destroy();
      delete charts[chartName];
    }
  }

  function createChart(canvasId, label, labels, data) {
    return new Chart(document.getElementById(canvasId), {
      type: "line",
      data: {
        labels,
        datasets: [{
          label,
          data,
          borderWidth: 2,
          pointRadius: 2,
          tension: 0.3,
          fill: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        interaction: { mode: "index", intersect: false }
      }
    });
  }

  async function atualizarGraficos() {
    showLoader();
    const periodo = document.getElementById("periodo").value;

    try {
      const resp = await fetch(`${API_URL}?periodo=${periodo}`);
      const json = await resp.json();

      if (!json || !json.labels) {
        showToast("Dados inválidos.");
        hideLoader();
        return;
      }

      const labels = json.labels;

      destroy("temperatura");
      destroy("umidade");
      destroy("pressao");
      destroy("pressao_nm");
      destroy("orvalho");

      charts.temperatura = createChart(chartMap.temperatura, "Temperatura (°C)", labels, json.temperatura);
      charts.umidade = createChart(chartMap.umidade, "Umidade (%)", labels, json.umidade);
      charts.pressao = createChart(chartMap.pressao, "Pressão (hPa)", labels, json.pressao);
      charts.pressao_nm = createChart(chartMap.pressao_nm, "Pressão Nível do Mar (hPa)", labels, json.pressao_nm);
      charts.orvalho = createChart(chartMap.orvalho, "Ponto de Orvalho (°C)", labels, json.orvalho);

      showToast("Gráficos atualizados!");
    } catch (e) {
      showToast("Erro ao carregar dados.");
      console.error(e);
    }

    hideLoader();
  }

  document.getElementById("periodo").addEventListener("change", atualizarGraficos);
  document.getElementById("btnRefresh").addEventListener("click", atualizarGraficos);

  window.addEventListener("load", atualizarGraficos);
})();
