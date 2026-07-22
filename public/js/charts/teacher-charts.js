// ========================================
// ACADALERT - Master Teacher Charts (Enhanced)
// Step 18: Full Frontend Integration
// ========================================

console.log('[Teacher Charts] Loading...');

// Prevent multiple initializations
let teacherChartsLoaded = false;

function loadTeacherCharts() {
    // Prevent duplicate loading
    if (teacherChartsLoaded) {
        console.log('[Teacher Charts] Already loaded, skipping...');
        return;
    }
    teacherChartsLoaded = true;
    
    console.log('[Teacher Charts] Initializing teacher charts...');
    
    const charts = [
        { id: 'riskByProgramChart', name: 'Risk by Program', loader: loadRiskByProgram },
        { id: 'departmentTrendChart', name: 'Department Trend', loader: loadDepartmentTrend }
    ];
    
    charts.forEach(chart => {
        const canvas = document.getElementById(chart.id);
        if (canvas) {
            console.log(`[Teacher Charts] Found canvas: ${chart.id}`);
            chart.loader();
        } else {
            console.warn(`[Teacher Charts] Canvas not found: ${chart.id}`);
        }
    });
}

function loadRiskByProgram() {
    const canvas = document.getElementById('riskByProgramChart');
    if (!canvas) {
        console.warn('[Teacher Charts] Canvas riskByProgramChart not found');
        return;
    }

    console.log('[Teacher Charts] Fetching risk by program data...');
    const url = '/charts/teacher/department-risk';

    fetchChartData(url)
        .then(response => {
            console.log('[Teacher Charts] Risk by program response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || data.length === 0) {
                showFallbackMessage(canvas, 'No risk data available for programs.');
                return;
            }

            const labels = data.map(d => d.program || 'Unknown');
            const highRisk = data.map(d => parseInt(d.high_risk) || 0);
            const moderateRisk = data.map(d => parseInt(d.moderate_risk) || 0);
            const lowRisk = data.map(d => parseInt(d.low_risk) || 0);

            console.log(`[Teacher Charts] Risk by Program data: ${labels.length} programs`);

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

            const chart = createChart('riskByProgramChart', config, 'No risk data available for programs.');
            if (chart) {
                console.log('[Teacher Charts] Risk by Program chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Teacher Charts] Risk by Program error:', error);
            showChartError('Risk by Program: ' + error.message);
            showFallbackMessage(canvas, 'Error loading risk data: ' + error.message);
        });
}

function loadDepartmentTrend() {
    const canvas = document.getElementById('departmentTrendChart');
    if (!canvas) {
        console.warn('[Teacher Charts] Canvas departmentTrendChart not found');
        return;
    }

    console.log('[Teacher Charts] Fetching department trend data...');
    const url = '/charts/teacher/department-trend';

    fetchChartData(url)
        .then(response => {
            console.log('[Teacher Charts] Department trend response:', response);
            if (!response.success) {
                throw new Error(response.message || 'Failed to load data');
            }

            const data = response.data;
            if (!data || !data.datasets || !data.datasets[0]) {
                showFallbackMessage(canvas, 'No department trend data available.');
                return;
            }

            const hasData = data.datasets[0].data.some(val => val > 0);
            if (!hasData) {
                showFallbackMessage(canvas, 'No department trend data available. Complete at least one grading period.');
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

            const chart = createChart('departmentTrendChart', config, 'No department trend data available.');
            if (chart) {
                console.log('[Teacher Charts] Department Trend chart created successfully');
            }
        })
        .catch(error => {
            console.error('[Teacher Charts] Department Trend error:', error);
            showChartError('Department Trend: ' + error.message);
            showFallbackMessage(canvas, 'Error loading trend data: ' + error.message);
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

// Load teacher charts when DOM is ready
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(() => {
        if (document.querySelector('#riskByProgramChart, #departmentTrendChart')) {
            loadTeacherCharts();
        }
    }, 100);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            if (document.querySelector('#riskByProgramChart, #departmentTrendChart')) {
                loadTeacherCharts();
            }
        }, 100);
    });
}