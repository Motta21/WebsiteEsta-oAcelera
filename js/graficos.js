const charts = {
    Temperatura: null,
    Umidade: null,
    Pressao: null,
    Pressao_nivel_mar: null,
    PTO_Orvalho: null
};

function carregarGrafico(campo, canvasId) {
    const periodo = document.getElementById("periodo").value;

    return fetch(`php/graficos_api.php?periodo=${periodo}&topico=${campo}`)
        .then(r => r.json())
        .then(d => {
            if (charts[campo]) charts[campo].destroy();

            const ctx = document.getElementById(canvasId);

            charts[campo] = new Chart(ctx, {
                type: "line",
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: campo,
                        data: d.dados,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                }
            });
        });
}

function atualizarTodos() {
    document.getElementById("loader").classList.remove("hidden");

    Promise.all([
        carregarGrafico("Temperatura", "graficoTemp"),
        carregarGrafico("Umidade", "graficoUmidade"),
        carregarGrafico("Pressao", "graficoPressao"),
        carregarGrafico("Pressao_nivel_mar", "graficoPressaoNM"),
        carregarGrafico("PTO_Orvalho", "graficoOrvalho")
    ]).finally(() => {
        document.getElementById("loader").classList.add("hidden");
    });
}

// Botão atualizar
document.getElementById("btnRefresh").addEventListener("click", atualizarTodos);

// Carrega ao abrir a página
atualizarTodos();