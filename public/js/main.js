/**
 * JavaScript Principal - Parque Industrial de Catamarca
 */

document.addEventListener('DOMContentLoaded', function() {
    // Animación de números en stat cards
    const animateNumbers = () => {
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseInt(el.dataset.count);
            const duration = 1500;
            const start = 0;
            const startTime = performance.now();
            
            const update = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = Math.floor(start + (target - start) * eased);
                el.textContent = current.toLocaleString('es-AR');
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            };
            requestAnimationFrame(update);
        });
    };
    
    // Observer para animar cuando sea visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateNumbers();
                observer.disconnect();
            }
        });
    });
    
    const statsSection = document.querySelector('.stat-cards');
    if (statsSection) observer.observe(statsSection);
    
    // Auto-cerrar alertas
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(t => new bootstrap.Tooltip(t));
    
    // Confirmación de eliminación
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || '¿Está seguro?')) {
                e.preventDefault();
            }
        });
    });
    
    // Toggle sidebar mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    }
    
    // Preview de imagen al subir
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function() {
            const preview = document.getElementById(this.dataset.preview);
            if (preview && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => preview.src = e.target.result;
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // Validación de formularios Bootstrap
    document.querySelectorAll('form.needs-validation').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});

// Función helper para mostrar notificaciones toast
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Formato de números
function formatNumber(num) {
    return new Intl.NumberFormat('es-AR').format(num);
}

// Función para cargar datos via AJAX
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...options.headers },
            ...options
        });
        if (!response.ok) throw new Error('Error en la petición');
        return await response.json();
    } catch (error) {
        console.error('Error:', error);
        showToast('Error al cargar datos', 'danger');
        throw error;
    }
}
