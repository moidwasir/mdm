/**
 * MDM Control Center - Main App Logic
 */

// Toast notification system
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const icons = { success: 'fa-check-circle', error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}" style="color:var(--${type === 'error' ? 'danger' : type});font-size:1.1rem;"></i><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(40px)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Flash messages from PHP
document.addEventListener('DOMContentLoaded', function() {
    const flash = document.querySelector('[data-flash]');
    if (flash) showToast(flash.dataset.message, flash.dataset.type);

    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    document.addEventListener('click', function(e) {
        if (e.target.closest('.mobile-menu-btn')) {
            sidebar.classList.toggle('open');
        }
    });
});

// Confirm action helper
function confirmAction(message, callback) {
    if (confirm(message)) callback();
}

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    });
}
