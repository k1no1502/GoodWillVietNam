/**
 * Main JavaScript for Goodwill Vietnam
 * Xử lý các tương tác frontend chính
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeStatistics();
    initializeRecentDonations();
    initializeImageUpload();
    initializeFormValidation();
    initializeTooltips();
    initializeAlerts();
});

/**
 * Initialize statistics counter animation
 */
function initializeStatistics() {
    const counters = document.querySelectorAll('[id^="total"]');
    
    const animateCounter = (element, target) => {
        let current = 0;
        const increment = target / 100;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 20);
    };
    
    // Animate counters when they come into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.dataset.target) || 0;
                animateCounter(entry.target, target);
                observer.unobserve(entry.target);
            }
        });
    });
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
}

/**
 * Load recent donations via AJAX
 */
function initializeRecentDonations() {
    const container = document.getElementById('recentDonations');
    if (!container) return;
    
    fetch('api/get-recent-donations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = data.html;
            } else {
                container.innerHTML = '<div class="col-12 text-center"><p class="text-muted">Chưa có quyên góp nào.</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading recent donations:', error);
            container.innerHTML = '<div class="col-12 text-center"><p class="text-muted">Lỗi tải dữ liệu.</p></div>';
        });
}

/**
 * Initialize image upload functionality
 */
function initializeImageUpload() {
    const uploadAreas = document.querySelectorAll('.image-upload');
    
    uploadAreas.forEach(area => {
        const input = area.querySelector('input[type="file"]');
        const preview = area.querySelector('.image-preview');
        
        // Click to upload
        area.addEventListener('click', () => {
            input.click();
        });
        
        // Drag and drop
        area.addEventListener('dragover', (e) => {
            e.preventDefault();
            area.classList.add('dragover');
        });
        
        area.addEventListener('dragleave', () => {
            area.classList.remove('dragover');
        });
        
        area.addEventListener('drop', (e) => {
            e.preventDefault();
            area.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0], input, preview);
            }
        });
        
        // File input change
        input.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0], input, preview);
            }
        });
    });
}

/**
 * Handle file upload and preview
 */
function handleFileUpload(file, input, preview) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        showAlert('Chỉ cho phép file ảnh (JPG, PNG, GIF)', 'error');
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        showAlert('File quá lớn. Kích thước tối đa 5MB.', 'error');
        return;
    }
    
    // Show preview
    const reader = new FileReader();
    reader.onload = (e) => {
        if (preview) {
            preview.innerHTML = `
                <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                <p class="mt-2 text-muted">${file.name}</p>
            `;
        }
    };
    reader.readAsDataURL(file);
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize auto-dismiss alerts
 */
function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    
    alerts.forEach(alert => {
        const delay = parseInt(alert.dataset.autoDismiss) || 5000;
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, delay);
    });
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info', autoDismiss = true) {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alertId = 'alert-' + Date.now();
    const alertHtml = `
        <div id="${alertId}" class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    if (autoDismiss) {
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }
}

/**
 * Create alert container if it doesn't exist
 */
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

/**
 * Confirm dialog
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Loading state for buttons
 */
function setButtonLoading(button, loading = true) {
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || button.innerHTML;
    }
}

/**
 * AJAX form submission
 */
function submitFormAjax(form, successCallback, errorCallback) {
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    
    setButtonLoading(submitButton, true);
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        setButtonLoading(submitButton, false);
        
        if (data.success) {
            if (successCallback) successCallback(data);
            else showAlert(data.message || 'Thành công!', 'success');
        } else {
            if (errorCallback) errorCallback(data);
            else showAlert(data.message || 'Có lỗi xảy ra!', 'error');
        }
    })
    .catch(error => {
        setButtonLoading(submitButton, false);
        console.error('Error:', error);
        if (errorCallback) errorCallback({message: 'Lỗi kết nối!'});
        else showAlert('Lỗi kết nối!', 'error');
    });
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = 'VND') {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(date, format = 'dd/mm/yyyy') {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    return format.replace('dd', day).replace('mm', month).replace('yyyy', year);
}

/**
 * Search functionality
 */
function initializeSearch() {
    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        let timeout;
        
        input.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                performSearch(e.target.value, e.target.dataset.target);
            }, 300);
        });
    });
}

/**
 * Perform search
 */
function performSearch(query, target) {
    if (query.length < 2) return;
    
    fetch(`api/search.php?q=${encodeURIComponent(query)}&target=${target}`)
        .then(response => response.json())
        .then(data => {
            const resultsContainer = document.getElementById(`${target}Results`);
            if (resultsContainer) {
                resultsContainer.innerHTML = data.html || '<p class="text-muted">Không tìm thấy kết quả.</p>';
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

/**
 * Initialize data tables
 */
function initializeDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        // Add sorting, filtering, pagination if needed
        // This is a basic implementation
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                sortTable(table, header.dataset.sort);
            });
        });
    });
}

/**
 * Sort table
 */
function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.querySelector(`td[data-sort="${column}"]`).textContent;
        const bVal = b.querySelector(`td[data-sort="${column}"]`).textContent;
        
        return aVal.localeCompare(bVal);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Export functions for global use
window.GoodwillVietnam = {
    showAlert,
    confirmAction,
    setButtonLoading,
    submitFormAjax,
    formatCurrency,
    formatDate,
    performSearch
};
