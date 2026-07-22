// ========================================
// ACADALERT - Chart.js Initialization
// Step 18: Frontend Integration
// ========================================

console.log('[Chart Init] Starting chart initialization...');

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Chart Init] DOM loaded, initializing charts...');
    
    // Determine which charts to load based on current page
    const currentRoute = window.location.pathname;
    console.log('[Chart Init] Current route:', currentRoute);
    
    // Admin Dashboard Charts
    if (currentRoute.includes('/admin/dashboard')) {
        console.log('[Chart Init] Loading Admin Charts...');
        if (typeof loadAdminCharts === 'function') {
            loadAdminCharts();
        } else {
            console.warn('[Chart Init] loadAdminCharts function not found');
        }
    }
    
    // Master Teacher Department Dashboard
    if (currentRoute.includes('/teacher/department')) {
        console.log('[Chart Init] Loading Teacher Department Charts...');
        if (typeof loadTeacherCharts === 'function') {
            loadTeacherCharts();
        } else {
            console.warn('[Chart Init] loadTeacherCharts function not found');
        }
    }
    
    // Master Teacher Block Dashboard
    if (currentRoute.includes('/teacher/block')) {
        console.log('[Chart Init] Loading Block Risk Chart...');
        if (typeof loadBlockRiskChart === 'function') {
            loadBlockRiskChart();
        } else {
            console.warn('[Chart Init] loadBlockRiskChart function not found');
        }
    }
    
    // Counselor Dashboard
    if (currentRoute.includes('/counselor/dashboard') || currentRoute.includes('/counselor/cases')) {
        console.log('[Chart Init] Loading Counselor Charts...');
        if (typeof loadCounselorCharts === 'function') {
            loadCounselorCharts();
        } else {
            console.warn('[Chart Init] loadCounselorCharts function not found');
        }
    }
    
    // Counselor Case View
    if (currentRoute.includes('/counselor/case')) {
        console.log('[Chart Init] Loading Student Risk Trend Chart...');
        if (typeof loadStudentRiskTrend === 'function') {
            loadStudentRiskTrend();
        } else {
            console.warn('[Chart Init] loadStudentRiskTrend function not found');
        }
    }
    
    // Student Dashboard
    if (currentRoute.includes('/student/dashboard')) {
        console.log('[Chart Init] Loading Student Charts...');
        if (typeof loadStudentCharts === 'function') {
            loadStudentCharts();
        } else {
            console.warn('[Chart Init] loadStudentCharts function not found');
        }
    }
});

// ========================================
// Global Error Handler for Charts
// ========================================

window.addEventListener('error', function(e) {
    if (e.message && e.message.includes('Chart')) {
        console.error('[Chart Error]', e.message);
        showChartError(e.message);
    }
});

// Log chart initialization status
console.log('[Chart Init] Chart initialization complete.');