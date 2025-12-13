// Mobile Navigation
function initMobileNav() {
    const mobileMenuTrigger = document.querySelector('.mobile-menu-trigger');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    
    if (mobileMenuTrigger && sidebar && backdrop) {
        mobileMenuTrigger.addEventListener('click', () => {
            sidebar.classList.add('active');
            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('active');
            backdrop.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
}

// Table Responsiveness
function initResponsiveTables() {
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-container')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-container';
            table.parentElement.insertBefore(wrapper, table);
            wrapper.appendChild(table);
            table.classList.add('responsive-table');
        }
    });
}

// Chart Responsiveness
function initResponsiveCharts() {
    const resizeCharts = () => {
        const chartCanvases = document.querySelectorAll('canvas');
        chartCanvases.forEach(canvas => {
            if (canvas.chart) {
                canvas.chart.resize();
            }
        });
    };

    window.addEventListener('resize', resizeCharts);
}

// Initialize all responsive features
document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initResponsiveTables();
    initResponsiveCharts();
});