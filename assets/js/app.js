/**
 * Spice & Savor RMS - Core Client Utilities
 */

const RMS = {
    // Open a slide-in panel by ID (no modals per PRD requirement)
    openSlidePanel: function(panelId) {
        const panel = document.getElementById(panelId);
        let backdrop = document.getElementById('slideBackdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'slideBackdrop';
            backdrop.className = 'slide-panel-backdrop';
            document.body.appendChild(backdrop);
            backdrop.addEventListener('click', () => RMS.closeAllSlidePanels());
        }
        if (panel) {
            panel.classList.add('open');
            backdrop.classList.add('show');
        }
    },

    closeSlidePanel: function(panelId) {
        const panel = document.getElementById(panelId);
        const backdrop = document.getElementById('slideBackdrop');
        if (panel) panel.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
    },

    closeAllSlidePanels: function() {
        document.querySelectorAll('.slide-panel').forEach(p => p.classList.remove('open'));
        const backdrop = document.getElementById('slideBackdrop');
        if (backdrop) backdrop.classList.remove('show');
    },

    // In-app Toast Notification (Top-right position to avoid overlapping bottom action buttons)
    toast: function(message, type = 'success') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1200';
            container.style.pointerEvents = 'none';
            document.body.appendChild(container);
        }

        const bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-primary'));
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white ${bgClass} border-0 show my-1 shadow-lg`;
        toastEl.setAttribute('role', 'alert');
        toastEl.style.pointerEvents = 'auto';
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body fw-medium">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        container.appendChild(toastEl);
        setTimeout(() => {
            toastEl.remove();
        }, 4000);
    },

    // Async Fetch wrapper with JSON parsing & error reporting
    api: async function(url, options = {}) {
        const defaultHeaders = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (options.body && !(options.body instanceof FormData)) {
            defaultHeaders['Content-Type'] = 'application/json';
        }

        const config = {
            ...options,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {})
            }
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'An unexpected server error occurred.');
            }
            return data;
        } catch (err) {
            RMS.toast(err.message || 'Network request failed', 'error');
            throw err;
        }
    },

    // Format currency string
    formatMoney: function(amount, symbol = 'Rs. ') {
        return symbol + parseFloat(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
};

// Initialize sidebar toggle
document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('d-none');
        });
    }
});
