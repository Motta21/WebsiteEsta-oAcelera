let chartsGraphs = {};

const cores = {
    temperatura: "rgba(255, 99, 132, 1)",     
    umidade: "rgba(54, 162, 235, 1)",         
    pressao: "rgba(255, 206, 86, 1)",         
    pressaoNM: "rgba(153, 102, 255, 1)",      
    orvalho: "rgba(75, 192, 192, 1)",
    bat: "rgb(238, 217, 25)"          
};

async function carregarDados(periodo) {
    try {
        // Pega o valor da estação selecionada no HTML
        const estacaoElement = document.getElementById("estacao");
        // Se não tiver select de estação na tela, assume 1 para não travar
        const estacao = estacaoElement ? estacaoElement.value : 1; 

        // Faz o pedido para o PHP enviando PERIODO e COD_E
        const resposta = await fetch(`php/dados_grafico.php?periodo=${periodo}&cod_e=${estacao}`);  
        const json = await resposta.json();

        if (!json.sucesso) {
            mostrarToast("Erro ao carregar dados!");
            return;
        }

        atualizarGraficos(json.dados);

    } catch (erro) {
        console.error(erro); // Ajuda a ver o erro no F12
        mostrarToast("Falha ao buscar dados!");
    } finally {
        // Esconde o loader se ele existir
        const loader = document.getElementById("loader");
        if(loader) loader.classList.add("hidden");
    }
}

function atualizarGraficos(dados) {
    // Se não vier dados (array vazio), limpa os gráficos ou avisa
    if (dados.length === 0) {
        mostrarToast("Sem dados para este período/estação.");
        return;
    }

    const labels = dados.map(d => d.DataHora);

    const temperatura = dados.map(d => parseFloat(d.Temperatura));
    const umidade = dados.map(d => parseFloat(d.Umidade));
    const pressao = dados.map(d => parseFloat(d.Pressao));
    const pressaoNM = dados.map(d => parseFloat(d.Pressao_nivel_mar));
    const orvalho = dados.map(d => parseFloat(d.PTO_Orvalho));
    const bat = dados.map(d => parseFloat(d.NV_Bat));

    criarOuAtualizar("graficoTemp", labels, temperatura, "Temperatura (°C)", cores.temperatura);
    criarOuAtualizar("graficoUmidade", labels, umidade, "Umidade (%)", cores.umidade);
    criarOuAtualizar("graficoPressao", labels, pressao, "Pressão (hPa)", cores.pressao);
    criarOuAtualizar("graficoPressaoNM", labels, pressaoNM, "Pressão Nível Mar (hPa)", cores.pressaoNM);
    criarOuAtualizar("graficoOrvalho", labels, orvalho, "Ponto de Orvalho (°C)", cores.orvalho);
    criarOuAtualizar("graficoBat", labels, bat, "Bateria (V)", cores.bat);
    criarOuAtualizar("graficoTemUmiPto", labels, [
    { label: "Temp", data: temperatura, borderColor: cores.temperatura, tension: 0.4 },
    { label: "Umi", data: umidade, borderColor: cores.umidade, tension: 0.4 },
    { label: "Orvalho", data: orvalho, borderColor: cores.orvalho, tension: 0.4 }
], "Título Ignorado");



}

function criarOuAtualizar(idCanvas, labels, valores, titulo, cor) {
    const canvas = document.getElementById(idCanvas);
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    // 1. Lógica para detectar se 'valores' é um array simples ou múltiplos datasets
    const ehMultiplo = Array.isArray(valores) && typeof valores[0] === 'object';
    
    // Prepara os datasets
    const datasetsConfig = ehMultiplo ? valores : [{
        label: titulo,
        data: valores,
        borderColor: cor,
        backgroundColor: cor ? cor.replace("1)", "0.2)") : "transparent",
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.35
    }];

    // 2. Se o gráfico já existe, atualiza os dados
    if (chartsGraphs[idCanvas]) {
        chartsGraphs[idCanvas].data.labels = labels;
        chartsGraphs[idCanvas].data.datasets = datasetsConfig; // Atualiza o array inteiro
        chartsGraphs[idCanvas].update();
        return;
    }

    // 3. Se não existe, cria um novo
    chartsGraphs[idCanvas] = new Chart(ctx, {
        type: "line",
        data: {
            labels: labels,
            datasets: datasetsConfig // Usa a configuração preparada acima
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

// === EVENT LISTENERS (Aqui embaixo é o lugar certo) ===

// Botão de atualizar manual
const btnRefresh = document.getElementById("btnRefresh");
if(btnRefresh) {
    btnRefresh.addEventListener("click", () => {
        carregarDados(document.getElementById("periodo").value);
    });
}

// Quando muda o período (Diário, Semanal...)
const selectPeriodo = document.getElementById("periodo");
if(selectPeriodo) {
    selectPeriodo.addEventListener("change", () => {
        carregarDados(selectPeriodo.value);
    });
}

// === NOVO: Quando muda a Estação ===
const selectEstacao = document.getElementById("estacao");
if(selectEstacao) {
    selectEstacao.addEventListener("change", () => {
        // Recarrega os dados mantendo o período que já estava escolhido
        carregarDados(document.getElementById("periodo").value);
    });
}

function mostrarToast(msg) {
    const toast = document.getElementById("toast");
    if(!toast) return;
    
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
}

// Iniciar
window.onload = () => carregarDados("diario");