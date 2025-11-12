/* ==========================================================================
   APS Digital - Authentication JavaScript Module
   Secretaria de Estado de Saúde de Mato Grosso do Sul (SES-MS)
   ========================================================================== */

(function() {
    'use strict';

    // Wait for main app to be available
    if (typeof APSDigital === 'undefined') {
        console.warn('APSDigital not found, retrying...');
        setTimeout(arguments.callee, 100);
        return;
    }

    const APP = window.APSDigital;

    // ==========================================================================
    // 1. Authentication Module
    // ==========================================================================

    APP.modules.Auth = {
        config: {
            loginForm: '#login-form',
            resetForm: '#reset-form',
            cpfField: '#cpf',
            passwordField: '#password',
            rememberField: '#remember',
            submitButton: '#submit-btn',
            maxAttempts: 5,
            lockoutTime: 15 * 60 * 1000, // 15 minutes
            passwordMinLength: 8
        },

        /**
         * Initialize Authentication Module
         */
        init: function() {
            console.log('Auth Module - Initializing...');
            
            this.setupLoginForm();
            this.setupResetForm();
            this.setupFieldValidation();
            this.setupPasswordStrength();
            this.checkLockout();
            this.setupAutoFocus();
            this.setupKeyboardNavigation();
            
            console.log('Auth Module - Initialized');
        },

        /**
         * Setup Login Form
         */
        setupLoginForm: function() {
            const form = document.querySelector(this.config.loginForm);
            if (!form) return;

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleLogin(form);
            });

            // Auto-submit on Enter in password field
            const passwordField = document.querySelector(this.config.passwordField);
            if (passwordField) {
                passwordField.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.handleLogin(form);
                    }
                });
            }
        },

        /**
         * Setup Reset Password Form
         */
        setupResetForm: function() {
            const form = document.querySelector(this.config.resetForm);
            if (!form) return;

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleResetPassword(form);
            });
        },

        /**
         * Setup Field Validation
         */
        setupFieldValidation: function() {
            const cpfField = document.querySelector(this.config.cpfField);
            if (cpfField) {
                // CPF formatting and validation
                cpfField.addEventListener('input', (e) => {
                    this.formatCPF(e.target);
                    this.validateCPF(e.target);
                });

                // Remove invalid state on focus
                cpfField.addEventListener('focus', (e) => {
                    e.target.classList.remove('is-invalid');
                });
            }

            const passwordField = document.querySelector(this.config.passwordField);
            if (passwordField) {
                // Password validation
                passwordField.addEventListener('input', (e) => {
                    this.validatePassword(e.target);
                });

                // Remove invalid state on focus
                passwordField.addEventListener('focus', (e) => {
                    e.target.classList.remove('is-invalid');
                });

                // Show/hide password toggle
                this.setupPasswordToggle(passwordField);
            }
        },

        /**
         * Setup Password Strength Indicator
         */
        setupPasswordStrength: function() {
            const passwordField = document.querySelector(this.config.passwordField);
            if (!passwordField) return;

            // Create strength indicator
            const strengthContainer = document.createElement('div');
            strengthContainer.className = 'password-strength mt-2';
            strengthContainer.innerHTML = `
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <small class="strength-text text-muted"></small>
            `;

            passwordField.parentNode.appendChild(strengthContainer);

            // Update strength on input
            passwordField.addEventListener('input', (e) => {
                this.updatePasswordStrength(e.target.value, strengthContainer);
            });
        },

        /**
         * Setup Password Toggle
         */
        setupPasswordToggle: function(passwordField) {
            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn btn-outline-secondary password-toggle';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.setAttribute('title', 'Mostrar/Ocultar senha');

            // Wrap password field in input group
            const wrapper = document.createElement('div');
            wrapper.className = 'input-group';
            
            passwordField.parentNode.insertBefore(wrapper, passwordField);
            wrapper.appendChild(passwordField);
            wrapper.appendChild(toggleBtn);

            // Toggle functionality
            toggleBtn.addEventListener('click', () => {
                const type = passwordField.type === 'password' ? 'text' : 'password';
                passwordField.type = type;
                
                const icon = toggleBtn.querySelector('i');
                icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
                
                toggleBtn.setAttribute('title', type === 'password' ? 'Mostrar senha' : 'Ocultar senha');
            });
        },

        /**
         * Setup Auto Focus
         */
        setupAutoFocus: function() {
            const cpfField = document.querySelector(this.config.cpfField);
            if (cpfField && !cpfField.value) {
                setTimeout(() => cpfField.focus(), 100);
            }
        },

        /**
         * Setup Keyboard Navigation
         */
        setupKeyboardNavigation: function() {
            document.addEventListener('keydown', (e) => {
                // Alt + L for login focus
                if (e.altKey && e.key.toLowerCase() === 'l') {
                    e.preventDefault();
                    const cpfField = document.querySelector(this.config.cpfField);
                    if (cpfField) cpfField.focus();
                }

                // Escape to clear form
                if (e.key === 'Escape') {
                    this.clearForm();
                }
            });
        },

        /**
         * Handle Login Submission
         */
        handleLogin: function(form) {
            const submitBtn = form.querySelector(this.config.submitButton);
            const cpfField = form.querySelector(this.config.cpfField);
            const passwordField = form.querySelector(this.config.passwordField);

            // Validate fields
            if (!this.validateCPF(cpfField) || !this.validatePassword(passwordField)) {
                this.showError('Por favor, corrija os erros no formulário');
                return;
            }

            // Check lockout
            if (this.isLockedOut()) {
                this.showError('Muitas tentativas. Tente novamente em alguns minutos.');
                return;
            }

            // Show loading state
            this.setLoadingState(submitBtn, true);

            const formData = new FormData(form);

            APP.utils.ajax({
                url: form.action,
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.success) {
                    this.handleLoginSuccess(response);
                } else {
                    this.handleLoginError(response);
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                this.showError('Erro de conexão. Tente novamente.');
            })
            .finally(() => {
                this.setLoadingState(submitBtn, false);
            });
        },

        /**
         * Handle Reset Password Submission
         */
        handleResetPassword: function(form) {
            const submitBtn = form.querySelector('[type="submit"]');
            const cpfField = form.querySelector('#reset-cpf');

            if (!this.validateCPF(cpfField)) {
                this.showError('Por favor, informe um CPF válido');
                return;
            }

            this.setLoadingState(submitBtn, true);

            const formData = new FormData(form);

            APP.utils.ajax({
                url: form.action,
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.success) {
                    this.showSuccess(response.message || 'Instruções enviadas para seu email');
                    form.reset();
                } else {
                    this.showError(response.message || 'Erro ao processar solicitação');
                }
            })
            .catch(error => {
                console.error('Reset password error:', error);
                this.showError('Erro de conexão. Tente novamente.');
            })
            .finally(() => {
                this.setLoadingState(submitBtn, false);
            });
        },

        /**
         * Handle Login Success
         */
        handleLoginSuccess: function(response) {
            this.clearAttempts();
            this.showSuccess(response.message || 'Login realizado com sucesso!');
            
            // Redirect after short delay
            setTimeout(() => {
                window.location.href = response.redirect || '/dashboard';
            }, 1000);
        },

        /**
         * Handle Login Error
         */
        handleLoginError: function(response) {
            this.incrementAttempts();
            this.showError(response.message || 'CPF ou senha incorretos');
            
            // Clear password field
            const passwordField = document.querySelector(this.config.passwordField);
            if (passwordField) {
                passwordField.value = '';
                passwordField.focus();
            }

            // Check if should lockout
            if (this.getAttempts() >= this.config.maxAttempts) {
                this.setLockout();
            }
        },

        /**
         * Format CPF Input
         */
        formatCPF: function(field) {
            let value = field.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            
            field.value = value;
        },

        /**
         * Validate CPF Field
         */
        validateCPF: function(field) {
            if (!field) return false;

            const value = field.value.replace(/\D/g, '');
            const isValid = value.length === 11 && APP.utils.validateCPF(field.value);

            if (field.value && !isValid) {
                field.classList.add('is-invalid');
                this.showFieldError(field, 'CPF inválido');
                return false;
            } else if (field.value && isValid) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                this.clearFieldError(field);
                return true;
            }

            field.classList.remove('is-invalid', 'is-valid');
            this.clearFieldError(field);
            return true;
        },

        /**
         * Validate Password Field
         */
        validatePassword: function(field) {
            if (!field) return false;

            const value = field.value;
            const isValid = value.length >= this.config.passwordMinLength;

            if (value && !isValid) {
                field.classList.add('is-invalid');
                this.showFieldError(field, `Senha deve ter pelo menos ${this.config.passwordMinLength} caracteres`);
                return false;
            } else if (value && isValid) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                this.clearFieldError(field);
                return true;
            }

            field.classList.remove('is-invalid', 'is-valid');
            this.clearFieldError(field);
            return true;
        },

        /**
         * Update Password Strength
         */
        updatePasswordStrength: function(password, container) {
            const progressBar = container.querySelector('.progress-bar');
            const strengthText = container.querySelector('.strength-text');

            if (!password) {
                progressBar.style.width = '0%';
                progressBar.className = 'progress-bar';
                strengthText.textContent = '';
                return;
            }

            let score = 0;
            let feedback = '';

            // Length check
            if (password.length >= 8) score += 20;
            if (password.length >= 12) score += 10;

            // Character variety
            if (/[a-z]/.test(password)) score += 20;
            if (/[A-Z]/.test(password)) score += 20;
            if (/[0-9]/.test(password)) score += 15;
            if (/[^A-Za-z0-9]/.test(password)) score += 15;

            // Determine strength level
            if (score < 30) {
                progressBar.className = 'progress-bar bg-danger';
                feedback = 'Muito fraca';
            } else if (score < 60) {
                progressBar.className = 'progress-bar bg-warning';
                feedback = 'Fraca';
            } else if (score < 80) {
                progressBar.className = 'progress-bar bg-info';
                feedback = 'Boa';
            } else {
                progressBar.className = 'progress-bar bg-success';
                feedback = 'Forte';
            }

            progressBar.style.width = Math.min(score, 100) + '%';
            strengthText.textContent = `Força da senha: ${feedback}`;
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

            errorElement.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${message}`;
        },

        /**
         * Clear Field Error
         */
        clearFieldError: function(field) {
            const errorElement = field.parentNode.querySelector('.invalid-feedback');
            if (errorElement) {
                errorElement.innerHTML = '';
            }
        },

        /**
         * Set Loading State
         */
        setLoadingState: function(button, loading) {
            if (!button) return;

            if (loading) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Verificando...
                `;
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || 'Entrar';
            }
        },

        /**
         * Show Error Message
         */
        showError: function(message) {
            const alertContainer = this.getAlertContainer();
            alertContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        },

        /**
         * Show Success Message
         */
        showSuccess: function(message) {
            const alertContainer = this.getAlertContainer();
            alertContainer.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        },

        /**
         * Get Alert Container
         */
        getAlertContainer: function() {
            let container = document.getElementById('alert-container');
            
            if (!container) {
                container = document.createElement('div');
                container.id = 'alert-container';
                container.className = 'mb-3';
                
                const form = document.querySelector('.auth-body form');
                if (form) {
                    form.parentNode.insertBefore(container, form);
                }
            }

            return container;
        },

        /**
         * Clear Form
         */
        clearForm: function() {
            const forms = document.querySelectorAll('.auth-body form');
            forms.forEach(form => {
                form.reset();
                form.querySelectorAll('.is-invalid, .is-valid').forEach(field => {
                    field.classList.remove('is-invalid', 'is-valid');
                });
                form.querySelectorAll('.invalid-feedback').forEach(error => {
                    error.innerHTML = '';
                });
            });

            const alertContainer = document.getElementById('alert-container');
            if (alertContainer) {
                alertContainer.innerHTML = '';
            }
        },

        /**
         * Login Attempts Management
         */
        getAttempts: function() {
            return parseInt(localStorage.getItem('login_attempts') || '0');
        },

        incrementAttempts: function() {
            const attempts = this.getAttempts() + 1;
            localStorage.setItem('login_attempts', attempts.toString());
            return attempts;
        },

        clearAttempts: function() {
            localStorage.removeItem('login_attempts');
            localStorage.removeItem('lockout_until');
        },

        setLockout: function() {
            const lockoutUntil = Date.now() + this.config.lockoutTime;
            localStorage.setItem('lockout_until', lockoutUntil.toString());
            this.showError(`Muitas tentativas de login. Tente novamente em ${this.config.lockoutTime / 60000} minutos.`);
        },

        isLockedOut: function() {
            const lockoutUntil = parseInt(localStorage.getItem('lockout_until') || '0');
            return lockoutUntil > Date.now();
        },

        checkLockout: function() {
            if (this.isLockedOut()) {
                const lockoutUntil = parseInt(localStorage.getItem('lockout_until'));
                const remainingTime = Math.ceil((lockoutUntil - Date.now()) / 1000 / 60);
                
                this.showError(`Conta temporariamente bloqueada. Tente novamente em ${remainingTime} minutos.`);
                
                // Disable form
                const form = document.querySelector(this.config.loginForm);
                if (form) {
                    const inputs = form.querySelectorAll('input, button');
                    inputs.forEach(input => input.disabled = true);
                }
            }
        }
    };

    // ==========================================================================
    // 2. Auto-Initialize
    // ==========================================================================

    function init() {
        APP.modules.Auth.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();