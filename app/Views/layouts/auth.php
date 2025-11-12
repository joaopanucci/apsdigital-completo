<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $title ?? 'APS Digital - SES/MS'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Auth CSS -->
    <link rel="stylesheet" href="/assets/css/auth.css">
    
    <style>
        :root {
            --ses-primary: #004F9F;
            --ses-secondary: #2a80dc;
            --ses-success: #28a745;
            --ses-danger: #dc3545;
            --ses-warning: #ffc107;
            --ses-info: #17a2b8;
            --ses-light: #f8f9fa;
            --ses-dark: #343a40;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--ses-primary) 0%, var(--ses-secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 400px;
        }
        
        .auth-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: none;
        }
        
        .auth-header {
            background: var(--ses-primary);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        
        .auth-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .auth-header .subtitle {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }
        
        .auth-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--ses-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--ses-secondary);
            box-shadow: 0 0 0 0.2rem rgba(42, 128, 220, 0.15);
        }
        
        .btn-primary {
            background: var(--ses-primary);
            border-color: var(--ses-primary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: var(--ses-secondary);
            border-color: var(--ses-secondary);
            transform: translateY(-1px);
        }
        
        .btn-link {
            color: var(--ses-primary);
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-link:hover {
            color: var(--ses-secondary);
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
        }
        
        .input-group-text {
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
            color: var(--ses-dark);
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--ses-secondary);
        }
        
        .loading {
            display: none;
        }
        
        .loading.active {
            display: inline-block;
        }
        
        .auth-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .version-info {
            margin-top: 1rem;
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        @media (max-width: 576px) {
            .auth-container {
                margin: 0;
            }
            
            .auth-header {
                padding: 1.5rem;
            }
            
            .auth-body {
                padding: 1.5rem;
            }
            
            .auth-footer {
                padding: 1rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="card auth-card">
            <?php echo $content; ?>
        </div>
        
        <!-- Footer com informações do sistema -->
        <div class="text-center text-white mt-4">
            <small>
                <i class="fas fa-shield-alt me-1"></i>
                Sistema Oficial da Secretaria de Estado de Saúde - MS
            </small>
            <?php if (isset($app_version)): ?>
                <div class="version-info">
                    <i class="fas fa-code-branch me-1"></i>
                    Versão <?php echo htmlspecialchars($app_version); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Auth JS -->
    <script src="/assets/js/auth.js"></script>
    
    <script>
        // Configuração global para requisições AJAX
        window.APP_CONFIG = {
            baseUrl: '<?php echo $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"]; ?>',
            csrfToken: '<?php echo $csrf_token ?? ""; ?>'
        };
        
        // Função para exibir alertas
        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            const alertContainer = document.getElementById('alert-container') || 
                                 document.querySelector('.auth-body') || 
                                 document.body;
            
            if (alertContainer) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = alertHtml;
                alertContainer.insertBefore(tempDiv.firstElementChild, alertContainer.firstElementChild);
                
                // Auto-remover após 5 segundos
                setTimeout(() => {
                    const alert = alertContainer.querySelector('.alert');
                    if (alert) {
                        alert.remove();
                    }
                }, 5000);
            }
        }
        
        // Função para validar CPF
        function validateCPF(cpf) {
            cpf = cpf.replace(/[^\d]/g, '');
            
            if (cpf.length !== 11 || /^(.)\1{10}$/.test(cpf)) {
                return false;
            }
            
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(cpf[i]) * (10 - i);
            }
            
            let digit = (sum * 10) % 11;
            if (digit === 10) digit = 0;
            if (digit !== parseInt(cpf[9])) return false;
            
            sum = 0;
            for (let i = 0; i < 10; i++) {
                sum += parseInt(cpf[i]) * (11 - i);
            }
            
            digit = (sum * 10) % 11;
            if (digit === 10) digit = 0;
            
            return digit === parseInt(cpf[10]);
        }
        
        // Máscara para CPF
        function applyCpfMask(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            input.value = value;
        }
        
        // Aplicar máscara automaticamente
        document.addEventListener('DOMContentLoaded', function() {
            const cpfInputs = document.querySelectorAll('input[name="cpf"], input[data-mask="cpf"]');
            cpfInputs.forEach(input => {
                input.addEventListener('input', () => applyCpfMask(input));
            });
        });
        
        // Verificar mensagens de URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('message')) {
            const messageType = urlParams.get('message');
            let message = '';
            let type = 'info';
            
            switch (messageType) {
                case 'logout_success':
                    message = 'Logout realizado com sucesso.';
                    type = 'success';
                    break;
                case 'session_expired':
                    message = 'Sua sessão expirou. Faça login novamente.';
                    type = 'warning';
                    break;
                case 'permission_denied':
                    message = 'Acesso negado. Você não tem permissão para esta ação.';
                    type = 'danger';
                    break;
                default:
                    if (messageType) {
                        message = messageType.replace(/_/g, ' ');
                        message = message.charAt(0).toUpperCase() + message.slice(1);
                    }
            }
            
            if (message) {
                setTimeout(() => showAlert(message, type), 500);
            }
        }
    </script>
</body>
</html>