/* ==========================================================================
   APS Digital - JavaScript Application Entry Point
   Secretaria de Estado de Saúde de Mato Grosso do Sul (SES-MS)
   ========================================================================== */

(function() {
    'use strict';

    // ==========================================================================
    // 1. Application Configuration
    // ==========================================================================
    
    window.APSDigital = window.APSDigital || {
        config: {
            version: '2.0.0',
            environment: 'production',
            debug: false,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            api_base_url: '/api',
            upload_max_size: 10 * 1024 * 1024, // 10MB
            chart_colors: {
                primary: '#004F9F',
                secondary: '#2a80dc',
                success: '#28a745',
                danger: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            }
        },
        modules: {},
        utils: {},
        components: {}
    };

    const APP = window.APSDigital;

    // ==========================================================================
    // 2. Utility Functions
    // ==========================================================================

    APP.utils = {
        /**
         * AJAX Request Helper with CSRF Protection
         */
        ajax: function(options) {
            const defaults = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': APP.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            };

            const config = Object.assign({}, defaults, options);
            
            // Add CSRF token to FormData if present
            if (config.body instanceof FormData) {
                config.body.append('_token', APP.config.csrf_token);
                delete config.headers['Content-Type']; // Let browser set it
            }

            return fetch(config.url, config)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    }
                    return response.text();
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    throw error;
                });
        },

        /**
         * Show Toast Notification
         */
        showToast: function(message, type = 'info', duration = 5000) {
            const toastContainer = document.getElementById('toast-container') || this.createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${this.getToastIcon(type)} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast, { delay: duration });
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', function() {
                toast.remove();
            });
        },

        /**
         * Create Toast Container
         */
        createToastContainer: function() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        },

        /**
         * Get Toast Icon by Type
         */
        getToastIcon: function(type) {
            const icons = {
                success: 'check-circle',
                danger: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle',
                primary: 'bell'
            };
            return icons[type] || 'info-circle';
        },

        /**
         * Format Date
         */
        formatDate: function(date, format = 'dd/MM/yyyy') {
            if (!date) return '';
            
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');

            switch (format) {
                case 'dd/MM/yyyy':
                    return `${day}/${month}/${year}`;
                case 'dd/MM/yyyy HH:mm':
                    return `${day}/${month}/${year} ${hours}:${minutes}`;
                case 'yyyy-MM-dd':
                    return `${year}-${month}-${day}`;
                default:
                    return d.toLocaleDateString('pt-BR');
            }
        },

        /**
         * Format Currency (Brazilian Real)
         */
        formatCurrency: function(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        },

        /**
         * Format Number
         */
        formatNumber: function(value, decimals = 0) {
            return new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(value);
        },

        /**
         * CPF Validation
         */
        validateCPF: function(cpf) {
            cpf = cpf.replace(/[^\d]/g, '');
            
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
                return false;
            }

            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let checkDigit = 11 - (sum % 11);
            if (checkDigit === 10 || checkDigit === 11) checkDigit = 0;
            if (checkDigit !== parseInt(cpf.charAt(9))) return false;

            sum = 0;
            for (let i = 0; i < 10; i++) {
                sum += parseInt(cpf.charAt(i)) * (11 - i);
            }
            checkDigit = 11 - (sum % 11);
            if (checkDigit === 10 || checkDigit === 11) checkDigit = 0;
            
            return checkDigit === parseInt(cpf.charAt(10));
        },

        /**
         * Email Validation
         */
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * Phone Validation
         */
        validatePhone: function(phone) {
            const re = /^\(\d{2}\)\s\d{4,5}-\d{4}$/;
            return re.test(phone);
        },

        /**
         * Debounce Function
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle Function
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Generate Random ID
         */
        generateId: function(prefix = 'id') {
            return `${prefix}_${Math.random().toString(36).substr(2, 9)}`;
        },

        /**
         * Deep Clone Object
         */
        deepClone: function(obj) {
            if (obj === null || typeof obj !== 'object') return obj;
            if (obj instanceof Date) return new Date(obj.getTime());
            if (obj instanceof Array) return obj.map(item => this.deepClone(item));
            if (typeof obj === 'object') {
                const clonedObj = {};
                for (const key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        clonedObj[key] = this.deepClone(obj[key]);
                    }
                }
                return clonedObj;
            }
        },

        /**
         * Local Storage Helper
         */
        storage: {
            set: function(key, value) {
                try {
                    localStorage.setItem(key, JSON.stringify(value));
                    return true;
                } catch (e) {
                    console.warn('LocalStorage not available:', e);
                    return false;
                }
            },
            get: function(key, defaultValue = null) {
                try {
                    const item = localStorage.getItem(key);
                    return item ? JSON.parse(item) : defaultValue;
                } catch (e) {
                    console.warn('LocalStorage not available:', e);
                    return defaultValue;
                }
            },
            remove: function(key) {
                try {
                    localStorage.removeItem(key);
                    return true;
                } catch (e) {
                    console.warn('LocalStorage not available:', e);
                    return false;
                }
            },
            clear: function() {
                try {
                    localStorage.clear();
                    return true;
                } catch (e) {
                    console.warn('LocalStorage not available:', e);
                    return false;
                }
            }
        }
    };

    // ==========================================================================
    // 3. Form Handling Components
    // ==========================================================================

    APP.components.Form = {
        /**
         * Initialize Form Validation
         */
        init: function() {
            this.setupValidation();
            this.setupMasks();
            this.setupFileUploads();
        },

        /**
         * Setup Bootstrap Validation
         */
        setupValidation: function() {
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (form.checkValidity()) {
                        APP.components.Form.handleSubmit(form);
                    }

                    form.classList.add('was-validated');
                }, false);
            });

            // Real-time validation
            document.addEventListener('input', function(e) {
                if (e.target.hasAttribute('data-validate')) {
                    APP.components.Form.validateField(e.target);
                }
            });
        },

        /**
         * Setup Input Masks
         */
        setupMasks: function() {
            // CPF Mask
            document.addEventListener('input', function(e) {
                if (e.target.hasAttribute('data-mask-cpf')) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    e.target.value = value;
                }
            });

            // Phone Mask
            document.addEventListener('input', function(e) {
                if (e.target.hasAttribute('data-mask-phone')) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length <= 10) {
                        value = value.replace(/(\d{2})(\d)/, '($1) $2');
                        value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    } else {
                        value = value.replace(/(\d{2})(\d)/, '($1) $2');
                        value = value.replace(/(\d{5})(\d)/, '$1-$2');
                    }
                    e.target.value = value;
                }
            });

            // CEP Mask
            document.addEventListener('input', function(e) {
                if (e.target.hasAttribute('data-mask-cep')) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                    e.target.value = value;
                }
            });
        },

        /**
         * Setup File Upload Components
         */
        setupFileUploads: function() {
            document.addEventListener('change', function(e) {
                if (e.target.type === 'file' && e.target.hasAttribute('data-upload-preview')) {
                    APP.components.Form.handleFilePreview(e.target);
                }
            });
        },

        /**
         * Validate Individual Field
         */
        validateField: function(field) {
            const value = field.value.trim();
            let isValid = true;
            let message = '';

            // CPF Validation
            if (field.hasAttribute('data-validate-cpf')) {
                if (value && !APP.utils.validateCPF(value)) {
                    isValid = false;
                    message = 'CPF inválido';
                }
            }

            // Email Validation
            if (field.hasAttribute('data-validate-email')) {
                if (value && !APP.utils.validateEmail(value)) {
                    isValid = false;
                    message = 'Email inválido';
                }
            }

            // Phone Validation
            if (field.hasAttribute('data-validate-phone')) {
                if (value && !APP.utils.validatePhone(value)) {
                    isValid = false;
                    message = 'Telefone inválido';
                }
            }

            // Update field state
            if (isValid) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                this.clearFieldError(field);
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                this.showFieldError(field, message);
            }

            return isValid;
        },

        /**
         * Show Field Error
         */
        showFieldError: function(field, message) {
            let errorElement = field.parentNode.querySelector('.invalid-feedback');
            
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'invalid-feedback';
                field.parentNode.appendChild(errorElement);
            }

            errorElement.textContent = message;
        },

        /**
         * Clear Field Error
         */
        clearFieldError: function(field) {
            const errorElement = field.parentNode.querySelector('.invalid-feedback');
            if (errorElement) {
                errorElement.textContent = '';
            }
        },

        /**
         * Handle File Preview
         */
        handleFilePreview: function(input) {
            const file = input.files[0];
            const previewId = input.getAttribute('data-upload-preview');
            const previewElement = document.getElementById(previewId);

            if (!file || !previewElement) return;

            // File size validation
            if (file.size > APP.config.upload_max_size) {
                APP.utils.showToast('Arquivo muito grande. Tamanho máximo: 10MB', 'danger');
                input.value = '';
                return;
            }

            // File type validation for images
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.innerHTML = `
                        <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">
                        <div class="mt-2">
                            <small class="text-muted">
                                ${file.name} (${APP.utils.formatNumber(file.size / 1024, 1)} KB)
                            </small>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                previewElement.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-file me-2"></i>
                        ${file.name} (${APP.utils.formatNumber(file.size / 1024, 1)} KB)
                    </div>
                `;
            }
        },

        /**
         * Handle Form Submission
         */
        handleSubmit: function(form) {
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn?.innerHTML;
            
            // Show loading state
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Processando...
                `;
            }

            const formData = new FormData(form);
            
            APP.utils.ajax({
                url: form.action,
                method: form.method || 'POST',
                body: formData
            })
            .then(response => {
                if (response.success) {
                    APP.utils.showToast(response.message || 'Operação realizada com sucesso!', 'success');
                    
                    if (response.redirect) {
                        setTimeout(() => {
                            window.location.href = response.redirect;
                        }, 1000);
                    } else if (response.reload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    APP.utils.showToast(response.message || 'Erro ao processar formulário', 'danger');
                    
                    if (response.errors) {
                        this.showFormErrors(form, response.errors);
                    }
                }
            })
            .catch(error => {
                APP.utils.showToast('Erro interno do servidor', 'danger');
                console.error('Form submission error:', error);
            })
            .finally(() => {
                // Restore button state
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        },

        /**
         * Show Form Errors
         */
        showFormErrors: function(form, errors) {
            Object.keys(errors).forEach(fieldName => {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.classList.add('is-invalid');
                    this.showFieldError(field, errors[fieldName][0]);
                }
            });
        }
    };

    // ==========================================================================
    // 4. DataTable Component
    // ==========================================================================

    APP.components.DataTable = {
        /**
         * Initialize DataTables
         */
        init: function() {
            document.querySelectorAll('[data-datatable]').forEach(table => {
                this.setupDataTable(table);
            });
        },

        /**
         * Setup Individual DataTable
         */
        setupDataTable: function(table) {
            const config = JSON.parse(table.getAttribute('data-datatable') || '{}');
            
            const defaultConfig = {
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                },
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                processing: true,
                serverSide: config.serverSide || false,
                order: [[0, 'desc']],
                columnDefs: [
                    {
                        targets: 'no-sort',
                        orderable: false
                    },
                    {
                        targets: 'no-search',
                        searchable: false
                    }
                ]
            };

            const finalConfig = Object.assign({}, defaultConfig, config);
            
            // Initialize DataTable
            const dt = $(table).DataTable(finalConfig);
            
            // Store reference
            table.dataTable = dt;

            // Handle row actions
            this.setupRowActions(table, dt);
        },

        /**
         * Setup Row Actions
         */
        setupRowActions: function(table, dt) {
            $(table).on('click', '[data-action]', function(e) {
                e.preventDefault();
                
                const action = this.getAttribute('data-action');
                const id = this.getAttribute('data-id');
                const row = dt.row($(this).closest('tr'));
                
                switch (action) {
                    case 'edit':
                        APP.components.DataTable.handleEdit(id, row);
                        break;
                    case 'delete':
                        APP.components.DataTable.handleDelete(id, row);
                        break;
                    case 'view':
                        APP.components.DataTable.handleView(id, row);
                        break;
                }
            });
        },

        /**
         * Handle Edit Action
         */
        handleEdit: function(id, row) {
            // Implementation depends on specific module
            console.log('Edit action for ID:', id);
        },

        /**
         * Handle Delete Action
         */
        handleDelete: function(id, row) {
            if (confirm('Tem certeza que deseja excluir este item?')) {
                APP.utils.ajax({
                    url: `/api/delete/${id}`,
                    method: 'DELETE'
                })
                .then(response => {
                    if (response.success) {
                        row.remove().draw();
                        APP.utils.showToast(response.message || 'Item excluído com sucesso!', 'success');
                    } else {
                        APP.utils.showToast(response.message || 'Erro ao excluir item', 'danger');
                    }
                })
                .catch(error => {
                    APP.utils.showToast('Erro interno do servidor', 'danger');
                    console.error('Delete error:', error);
                });
            }
        },

        /**
         * Handle View Action
         */
        handleView: function(id, row) {
            // Implementation depends on specific module
            console.log('View action for ID:', id);
        },

        /**
         * Refresh DataTable
         */
        refresh: function(tableId) {
            const table = document.getElementById(tableId);
            if (table && table.dataTable) {
                table.dataTable.ajax.reload();
            }
        }
    };

    // ==========================================================================
    // 5. Chart Component
    // ==========================================================================

    APP.components.Chart = {
        /**
         * Initialize Charts
         */
        init: function() {
            document.querySelectorAll('[data-chart]').forEach(canvas => {
                this.setupChart(canvas);
            });
        },

        /**
         * Setup Individual Chart
         */
        setupChart: function(canvas) {
            const config = JSON.parse(canvas.getAttribute('data-chart') || '{}');
            const type = config.type || 'bar';
            const data = config.data || {};
            const options = config.options || {};

            const defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            };

            const finalOptions = this.mergeDeep(defaultOptions, options);
            
            // Apply color scheme
            if (data.datasets) {
                data.datasets.forEach((dataset, index) => {
                    if (!dataset.backgroundColor) {
                        dataset.backgroundColor = this.getColorPalette()[index % this.getColorPalette().length];
                    }
                    if (!dataset.borderColor) {
                        dataset.borderColor = dataset.backgroundColor;
                    }
                });
            }

            const chart = new Chart(canvas, {
                type: type,
                data: data,
                options: finalOptions
            });

            // Store reference
            canvas.chart = chart;
        },

        /**
         * Get Color Palette
         */
        getColorPalette: function() {
            return [
                APP.config.chart_colors.primary,
                APP.config.chart_colors.secondary,
                APP.config.chart_colors.success,
                APP.config.chart_colors.warning,
                APP.config.chart_colors.info,
                APP.config.chart_colors.danger,
                '#6f42c1',
                '#fd7e14',
                '#20c997',
                '#e83e8c'
            ];
        },

        /**
         * Deep Merge Objects
         */
        mergeDeep: function(target, source) {
            const output = Object.assign({}, target);
            if (this.isObject(target) && this.isObject(source)) {
                Object.keys(source).forEach(key => {
                    if (this.isObject(source[key])) {
                        if (!(key in target))
                            Object.assign(output, { [key]: source[key] });
                        else
                            output[key] = this.mergeDeep(target[key], source[key]);
                    } else {
                        Object.assign(output, { [key]: source[key] });
                    }
                });
            }
            return output;
        },

        /**
         * Check if Object
         */
        isObject: function(item) {
            return item && typeof item === 'object' && !Array.isArray(item);
        },

        /**
         * Update Chart Data
         */
        updateChart: function(canvasId, newData) {
            const canvas = document.getElementById(canvasId);
            if (canvas && canvas.chart) {
                canvas.chart.data = newData;
                canvas.chart.update();
            }
        }
    };

    // ==========================================================================
    // 6. Application Initialization
    // ==========================================================================

    /**
     * Initialize Application
     */
    function init() {
        console.log(`APS Digital v${APP.config.version} - Initializing...`);

        // Initialize components
        APP.components.Form.init();
        
        // Initialize DataTables (wait for jQuery)
        if (typeof $ !== 'undefined' && $.fn.dataTable) {
            APP.components.DataTable.init();
        }
        
        // Initialize Charts (wait for Chart.js)
        if (typeof Chart !== 'undefined') {
            APP.components.Chart.init();
        }

        // Initialize Bootstrap components
        if (typeof bootstrap !== 'undefined') {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }

        // Global error handling
        window.addEventListener('error', function(e) {
            if (APP.config.debug) {
                console.error('JavaScript Error:', e.error);
            }
        });

        // Unhandled promise rejections
        window.addEventListener('unhandledrejection', function(e) {
            if (APP.config.debug) {
                console.error('Unhandled Promise Rejection:', e.reason);
            }
        });

        console.log('APS Digital - Initialization complete');
    }

    // ==========================================================================
    // 7. Auto-Initialize
    // ==========================================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();