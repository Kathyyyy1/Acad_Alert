// ========================================
// ACADALERT - Main JavaScript
// Universidad de Dagupan
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('AcadAlert v1.0.0 loaded successfully.');
    
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function(alert) {
        setTimeout(function() {
            alert.classList.add('fade');
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

// ========================================
// Sidebar Toggle (Mobile)
// ========================================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-wrapper');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// ========================================
// Tooltip Initialization
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// ========================================
// Confirmation Dialog Helper
// ========================================

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ========================================
// Toast Notification (Simple)
// ========================================

function showToast(message, type = 'success') {
    const colors = {
        success: 'bg-success text-white',
        error: 'bg-danger text-white',
        warning: 'bg-warning text-dark',
        info: 'bg-info text-white'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center ${colors[type] || colors.info} border-0 show`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    container.appendChild(toast);
    document.body.appendChild(container);
    
    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() {
            container.remove();
        }, 300);
    }, 4000);
}

// ========================================
// Loading Spinner Helper
// ========================================

function showLoading(element) {
    const original = element.innerHTML;
    element.disabled = true;
    element.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading...
    `;
    element.dataset.original = original;
}

function hideLoading(element) {
    element.disabled = false;
    element.innerHTML = element.dataset.original || 'Submit';
}