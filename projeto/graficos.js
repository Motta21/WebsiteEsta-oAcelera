let chartsGraphs = {};

const cores = {
    temperatura: "rgba(255, 99, 132, 1)",     
    umidade: "rgba(54, 162, 235, 1)",         
    pressao: "rgba(255, 206, 86, 1)",         
    pressaoNM: "rgba(153, 102, 255, 1)",      
    orvalho: "rgba(75, 192, 192, 1)"          
};


async function carregarDados(periodo) {
    try {
        document.getElementById("estacao").addEventListener("change", () => {
                const periodo = document.getElementById("periodo").value;
                carregarDados(periodo);
        });
        const estacao = document.getElementById("estacao").value;
        const resposta = await fetch(`php/dados_grafico.php?periodo=${periodo}&cod_e=${estacao}`);  
        const json = await resposta.json();

        if (!json.sucesso) {
            mostrarToast("Erro ao carregar dados!");
            return;
        }

        atualizarGraficos(json.dados);

    } catch (erro) {
        mostrarToast("Falha ao buscar dados!");
    } finally {
        document.getElementById("loader").classList.add("hidden");
    }
}


function atualizarGraficos(dados) {

    const labels = dados.map(d => d.DataHora);

    const temperatura = dados.map(d => parseFloat(d.Temperatura));
    const umidade = dados.map(d => parseFloat(d.Umidade));
    const pressao = dados.map(d => parseFloat(d.Pressao));
    const pressaoNM = dados.map(d => parseFloat(d.Pressao_nivel_mar));
    const orvalho = dados.map(d => parseFloat(d.PTO_Orvalho));

    criarOuAtualizar("graficoTemp", labels, temperatura, "Temperatura (°C)", cores.temperatura);
    criarOuAtualizar("graficoUmidade", labels, umidade, "Umidade (%)", cores.umidade);
    criarOuAtualizar("graficoPressao", labels, pressao, "Pressão (hPa)", cores.pressao);
    criarOuAtualizar("graficoPressaoNM", labels, pressaoNM, "Pressão Nível Mar (hPa)", cores.pressaoNM);
    criarOuAtualizar("graficoOrvalho", labels, orvalho, "Ponto de Orvalho (°C)", cores.orvalho);
}


function criarOuAtualizar(idCanvas, labels, valores, titulo, cor) {

    const ctx = document.getElementById(idCanvas).getContext("2d");

    if (chartsGraphs[idCanvas]) {
        chartsGraphs[idCanvas].data.labels = labels;
        chartsGraphs[idCanvas].data.datasets[0].data = valores;
        chartsGraphs[idCanvas].update();
        return;
    }

    chartsGraphs[idCanvas] = new Chart(ctx, {
        type: "line",
        data: {
            labels: labels,
            datasets: [{
                label: titulo,
                data: valores,
                borderColor: cor,
                backgroundColor: cor.replace("1)", "0.2)"),
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: "index", intersect: false },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8
                    }
                }
            }
        }
    });
}


document.getElementById("btnRefresh").addEventListener("click", () => {
    carregarDados(document.getElementById("periodo").value);
});

document.getElementById("periodo").addEventListener("change", () => {
    carregarDados(document.getElementById("periodo").value);
});

function mostrarToast(msg) {
    const toast = document.getElementById("toast");
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
}

window.onload = () => carregarDados("diario");
