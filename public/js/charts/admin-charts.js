// ========================================
// ACADALERT - Admin Charts (Enhanced)
// Step 18: Full Frontend Integration
// ========================================

console.log('[Admin Charts] Loading...');

// Prevent multiple initializations
let adminChartsLoaded = false;

function loadAdminCharts() {
    // Prevent duplicate loading
    if (adminChartsLoaded) {
        console.log('[Admin Charts] Already loaded, skipping...');
        return;
    }
    adminChartsLoaded = true;
    
    console.log('[Admin Charts] Initializing admin charts...');
    
    const charts = [
        { id: 'riskByDeptChart', name: 'Risk by Department', loader: loadRiskByDepartment },
        { id: 'riskDistributionChart', name: 'Risk Distribution', loader: loadRiskDistribution },
        { id: 'riskTrendChart', name: 'Risk Trend', loader: loadRiskTrend }
    ];
    
    charts.forEach(chart => {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            console.log(`[Admin Charts] Found canvas: ${chart.id}`);
            chart.loader();
        } else {
            console.warn(`[Admin Charts] Canvas not found: ${chart.id}`);
        }
    });
}

function loadRiskByDepartment() {
    const canvas = document.getElementById('riskByDeptChart');
    if (!canvas) {
        console.warn('[Admin Charts] Canvas riskByDeptChart not found');
        return;
    }

    console.log('[Admin Charts] Fetching risk by department data...');
    const url = '/charts/admin/risk-by-department';

    fetchChartData(url)
        .then(response => {
            console.log('[Admin Charts] Risk by department response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || data.length === 0) {
                showFallbackMessage(canvas, 'No department risk data available.');
                return;
            }

            const labels = data.map(d => d.department || 'Unknown');
            const highRisk = data.map(d => parseInt(d.high_risk) || 0);
            const moderateRisk = data.map(d => parseInt(d.moderate_risk) || 0);
            const lowRisk = data.map(d => parseInt(d.low_risk) || 0);

            console.log(`[Admin Charts] Risk by Department data: ${labels.length} departments`);

            const config = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'High Risk',
                            data: highRisk,
                            backgroundColor: '#dc3545',
                            borderRadius: 4,
                        },
                        {
                            label: 'Moderate Risk',
                            data: moderateRisk,
                            backgroundColor: '#ffc107',
                            borderRadius: 4,
                        },
                        {
                            label: 'Low Risk',
                            data: lowRisk,
                            backgroundColor: '#28a745',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.x + ' students';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        },
                        y: {
                            stacked: true,
                            grid: { display: false }
                        }
                    }
                }
            };

            const chart = createChart('riskByDeptChart', config, 'No department risk data available.');
            if (chart) {
                console.log('[Admin Charts] Risk by Department chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Admin Charts] Risk by Department error:', error);
            showChartError('Risk by Department: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk data: ' + error.message);
        });
}

function loadRiskDistribution() {
    const canvas = document.getElementById('riskDistributionChart');
    if (!canvas) {
        console.warn('[Admin Charts] Canvas riskDistributionChart not found');
        return;
    }

    console.log('[Admin Charts] Fetching risk distribution data...');
    const url = '/charts/admin/risk-distribution';

    fetchChartData(url)
        .then(response => {
            console.log('[Admin Charts] Risk distribution response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No risk distribution data available.');
                return;
            }

            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
            console.log(`[Admin Charts] Risk distribution total: ${total} students`);

            if (total === 0) {
                showFallbackMessage(canvas, 'No risk scores recorded yet.');
                return;
            }

            const config = {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed + ' students (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%',
                }
            };

            const chart = createChart('riskDistributionChart', config, 'No risk distribution data available.');
            if (chart) {
                console.log('[Admin Charts] Risk Distribution chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Admin Charts] Risk Distribution error:', error);
            showChartError('Risk Distribution: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk distribution: ' + error.message);
        });
}

function loadRiskTrend() {
    const canvas = document.getElementById('riskTrendChart');
    if (!canvas) {
        console.warn('[Admin Charts] Canvas riskTrendChart not found');
        return;
    }

    console.log('[Admin Charts] Fetching risk trend data...');
    const url = '/charts/admin/risk-trend';

    fetchChartData(url)
        .then(response => {
            console.log('[Admin Charts] Risk trend response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No risk trend data available.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No risk trend data available. Complete at least one grading period.');
                return;
            }

            const config = {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'High Risk: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            title: {
                                display: true,
                                text: 'High Risk %'
                            }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            };

            const chart = createChart('riskTrendChart', config, 'No risk trend data available.');
            if (chart) {
                console.log('[Admin Charts] Risk Trend chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Admin Charts] Risk Trend error:', error);
            showChartError('Risk Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk trend: ' + error.message);
        });
}

function showFallbackMessage(canvas, message) {
    const parent = canvas.parentElement;
    if (parent) {
        parent.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-chart-simple fa-2x d-block mb-2 opacity-50"></i>
                <p class="mb-0">${message}</p>
            </div>
        `;
    }
}

// Load admin charts when DOM is ready - using a more reliable approach
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    // Small delay to ensure everything is rendered
    setTimeout(() => {
        if (document.querySelector('#riskByDeptChart, #riskDistributionChart, #riskTrendChart')) {
            loadAdminCharts();
        }
    }, 100);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            if (document.querySelector('#riskByDeptChart, #riskDistributionChart, #riskTrendChart')) {
                loadAdminCharts();
            }
        }, 100);
    });
}