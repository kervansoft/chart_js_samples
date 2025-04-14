var chartInstanceSector2; // Grafik örneği için değişken

var colorPalette = [
    "#FFCDD2", "#F8BBD0", "#E1BEE7", "#D1C4E9", "#C5CAE9", "#BBDEFB", "#B3E5FC", "#B2EBF2", "#B2DFDB"
];

function createChartSector2(data) {
    // Veri hazırlığı
    var cabNames = Object.keys(data); // CabName'leri al
    var sectorNames = [...new Set(Object.values(data).flatMap(sector => Object.keys(sector)))]; // Tüm sektör isimlerini al

    var datasets = sectorNames.map((sector, index) => {
        var totalUsersData = cabNames.map(cabName => data[cabName][sector] || 0); // Sektör bazında kullanıcı sayıları
        var colorIndex = index % colorPalette.length;

        return {
            label: sector,
            data: totalUsersData,
            backgroundColor: colorPalette[colorIndex]
        };
    });

    // Grafik varsa önce yok et
    if (chartInstanceSector2) {
        chartInstanceSector2.destroy();
    }

    // Grafik oluşturma
    var ctxSector2 = document.getElementById("myChartSector").getContext("2d");
    chartInstanceSector2 = new Chart(ctxSector2, {
        type: "bar",
        data: {
            labels: cabNames,
            datasets: datasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: "top"
                },
                title: {
                    display: false,
                    // text: "Chart Title"
                }
            },
            scales: {
                x: {
                    stacked: true,
                    title: {
                        display: false,
                        // text: "CabName"
                    }
                },
                y: {
                    stacked: true,
                    title: {
                        display: false,
                        // text: "TotalUsers"
                    }
                }
            }
        }
    });
}

function updateChartSector2() {
    $.ajax({
        type: "GET",
        url: "/eventgrafiksectorcell/" + event_id,
        data: {
            id: event_id,
            _token: csrf
        },
        success: function(response) {
            createChartSector2(response); // Grafik oluşturucuya yanıtı ilet
        }
    });
}

$(document).ready(function() {
    updateChartSector2(); // Sayfa yüklendiğinde grafik güncellemesi yap
    setInterval(updateChartSector2, setIntervalMinute); // Belirli aralıklarla grafik güncelle
});
