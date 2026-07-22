// ========================================
// ACADALERT - Chart.js Configuration
// Step 17: API Endpoints for Chart.js Data
// ========================================

// Global Chart.js defaults
Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = true;

// Color palette
const COLORS = {
    primary: '#4e73df',
    success: '#28a745',
    warning: '#ffc107',
    danger: '#dc3545',
    info: '#17a2b8',
    secondary: '#6c757d',
    purple: '#6f42c1',
};

const RISK_COLORS = {
    low: '#28a745',
    moderate: '#ffc107',
    high: '#dc3545',
};

// ========================================
// Store chart instances to prevent duplicates
// ========================================
const chartInstances = {};

// ========================================
// Chart Helper Functions
// ========================================

/**
 * Safe fetch with error handling
 */
async function fetchChartData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...options.headers,
            },
            ...options,
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('[Chart] Fetch error:', error);
        showChartError(error.message);
        throw error;
    }
}

/**
 * Show chart error in UI
 */
function showChartError(message) {
    const errorContainer = document.getElementById('chartErrorContainer');
    if (errorContainer) {
        errorContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Chart Error:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        errorContainer.style.display = 'block';
    } else {
        // Create a floating error toast
        const toast = document.createElement('div');
        toast.className = 'alert alert-danger alert-dismissible fade show';
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.maxWidth = '400px';
        toast.innerHTML = `
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Chart Error:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 8000);
    }
}

/**
 * Create a chart with proper error handling and duplicate prevention
 * 
 * FIX: This is the critical function - it now properly destroys existing charts
 */
function createChart(canvasId, config, fallbackMessage = 'No data available') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`[Chart] Canvas element "${canvasId}" not found.`);
        return null;
    }

    // ============================================================
    // FIX: Destroy existing chart instance if it exists
    // ============================================================
    
    // First check our global registry
    if (chartInstances[canvasId]) {
        console.log(`[Chart] Destroying existing chart on canvas "${canvasId}" (from registry)`);
        try {
            chartInstances[canvasId].destroy();
            chartInstances[canvasId] = null;
            delete chartInstances[canvasId];
        } catch (e) {
            console.warn(`[Chart] Could not destroy existing chart from registry:`, e);
        }
    }

    // Also check if canvas has a chart attached directly
    if (canvas.chart) {
        console.log(`[Chart] Destroying chart attached to canvas "${canvasId}"`);
        try {
            canvas.chart.destroy();
            canvas.chart = null;
        } catch (e) {
            console.warn(`[Chart] Could not destroy attached chart:`, e);
        }
    }

    // ALSO check for any Chart.js instances attached via __chartjs property
    if (canvas.__chartjs) {
        console.log(`[Chart] Destroying chart via __chartjs property on canvas "${canvasId}"`);
        try {
            canvas.__chartjs.destroy();
            canvas.__chartjs = null;
        } catch (e) {
            console.warn(`[Chart] Could not destroy chart via __chartjs:`, e);
        }
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        console.warn(`[Chart] Could not get context for "${canvasId}".`);
        return null;
    }

    try {
        // Check if there's any data to display
        const hasData = config.data.datasets.some(dataset => 
            dataset.data && dataset.data.length > 0 && dataset.data.some(val => val !== 0 && val !== null)
        );

        if (!hasData) {
            // Show fallback message on the canvas
            const parent = canvas.parentElement;
            if (parent) {
                parent.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-simple fa-2x d-block mb-2 opacity-50"></i>
                        <p class="mb-0">${fallbackMessage}</p>
                    </div>
                `;
            }
            return null;
        }

        // Create new chart
        const chart = new Chart(ctx, config);
        
        // Store reference to prevent duplicates - store in ALL possible locations
        chartInstances[canvasId] = chart;
        canvas.chart = chart;
        canvas.__chartjs = chart;
        
        console.log(`[Chart] Chart "${canvasId}" created successfully.`);
        return chart;
        
    } catch (error) {
        console.error(`[Chart] Error creating chart "${canvasId}":`, error);
        const parent = canvas.parentElement;
        if (parent) {
            parent.innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                    <p class="mb-0">Failed to load chart: ${error.message}</p>
                </div>
            `;
        }
        return null;
    }
}

/**
 * Create a chart with loading state
 */
function createChartWithLoading(canvasId, config, loadingMessage = 'Loading chart data...') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return Promise.resolve(null);
    }

    // Show loading state
    const parent = canvas.parentElement;
    const loadingEl = document.createElement('div');
    loadingEl.className = 'chart-loading';
    loadingEl.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary mb-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mb-0">${loadingMessage}</p>
        </div>
    `;
    parent.appendChild(loadingEl);
    canvas.style.display = 'none';

    return new Promise((resolve) => {
        setTimeout(() => {
            loadingEl.remove();
            canvas.style.display = 'block';
            const chart = createChart(canvasId, config);
            resolve(chart);
        }, 300);
    });
}

/**
 * Destroy all chart instances (useful for cleanup)
 */
function destroyAllCharts() {
    console.log('[Chart] Destroying all chart instances...');
    for (const canvasId in chartInstances) {
        if (chartInstances[canvasId]) {
            try {
                chartInstances[canvasId].destroy();
                console.log(`[Chart] Destroyed chart on canvas "${canvasId}"`);
            } catch (e) {
                console.warn(`[Chart] Could not destroy chart on "${canvasId}":`, e);
            }
        }
    }
    // Clear the registry
    for (const key in chartInstances) {
        delete chartInstances[key];
    }
    // Also clear canvas references
    document.querySelectorAll('canvas').forEach(canvas => {
        if (canvas.chart) {
            try {
                canvas.chart.destroy();
            } catch (e) {}
            canvas.chart = null;
        }
        if (canvas.__chartjs) {
            try {
                canvas.__chartjs.destroy();
            } catch (e) {}
            canvas.__chartjs = null;
        }
    });
    console.log('[Chart] All charts destroyed.');
}

// Expose destroyAllCharts globally for debugging
window.destroyAllCharts = destroyAllCharts;