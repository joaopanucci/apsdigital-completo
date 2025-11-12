<?php
// Verificar se já existe uma sessão ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se usuário já está logado
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit;
}

// Verificar se há mensagem de erro
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// Gerar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
$title = 'Login - APS Digital';
$app_version = '2.0.0';

// Buffer do conteúdo da página
ob_start();
?>

<div class="auth-header">
    <h1>
        <i class="fas fa-hospital-alt me-2"></i>
        APS Digital
    </h1>
    <p class="subtitle">Sistema de Gerenciamento da Atenção Primária à Saúde</p>
</div>

<div class="auth-body">
    <!-- Container para alertas -->
    <div id="alert-container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <form id="loginForm" action="/auth/login" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="mb-3">
            <label for="cpf" class="form-label">
                <i class="fas fa-id-card me-1"></i>
                CPF
            </label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-user"></i>
                </span>
                <input 
                    type="text" 
                    class="form-control" 
                    id="cpf" 
                    name="cpf" 
                    data-mask="cpf"
                    placeholder="000.000.000-00"
                    maxlength="14"
                    required
                    autocomplete="username"
                >
            </div>
            <div class="invalid-feedback" id="cpf-error"></div>
        </div>

        <div class="mb-4">
            <label for="senha" class="form-label">
                <i class="fas fa-lock me-1"></i>
                Senha
            </label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-key"></i>
                </span>
                <input 
                    type="password" 
                    class="form-control" 
                    id="senha" 
                    name="senha" 
                    placeholder="Digite sua senha"
                    required
                    autocomplete="current-password"
                >
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
            <div class="invalid-feedback" id="senha-error"></div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="lembrar" name="lembrar">
            <label class="form-check-label" for="lembrar">
                Lembrar CPF neste dispositivo
            </label>
        </div>

        <button type="submit" class="btn btn-primary" id="loginBtn">
            <span class="btn-text">
                <i class="fas fa-sign-in-alt me-2"></i>
                Entrar
            </span>
            <span class="loading d-none">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Entrando...
            </span>
        </button>

        <div class="text-center mt-3">
            <a href="/auth/reset-password" class="btn btn-link">
                <i class="fas fa-question-circle me-1"></i>
                Esqueci minha senha
            </a>
        </div>
    </form>
</div>

<div class="auth-footer">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <strong>Secretaria de Estado de Saúde</strong><br>
            <small>Mato Grosso do Sul</small>
        </div>
        <div class="text-end">
            <i class="fas fa-phone me-1"></i>
            <small>(67) 3318-1000</small><br>
            <i class="fas fa-envelope me-1"></i>
            <small>apsdigital@ses.ms.gov.br</small>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const cpfInput = document.getElementById('cpf');
    const senhaInput = document.getElementById('senha');
    const loginBtn = document.getElementById('loginBtn');
    const togglePassword = document.getElementById('togglePassword');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');
    const lembrarCheckbox = document.getElementById('lembrar');
    
    // Carregar CPF salvo
    const savedCpf = localStorage.getItem('saved_cpf');
    if (savedCpf) {
        cpfInput.value = savedCpf;
        lembrarCheckbox.checked = true;
    }
    
    // Toggle para mostrar/ocultar senha
    togglePassword.addEventListener('click', function() {
        const type = senhaInput.type === 'password' ? 'text' : 'password';
        senhaInput.type = type;
        
        if (type === 'password') {
            togglePasswordIcon.className = 'fas fa-eye';
        } else {
            togglePasswordIcon.className = 'fas fa-eye-slash';
        }
    });
    
    // Validação em tempo real do CPF
    cpfInput.addEventListener('blur', function() {
        const cpf = this.value.replace(/\D/g, '');
        const cpfError = document.getElementById('cpf-error');
        
        if (cpf && !validateCPF(cpf)) {
            this.classList.add('is-invalid');
            cpfError.textContent = 'CPF inválido';
        } else {
            this.classList.remove('is-invalid');
            cpfError.textContent = '';
        }
    });
    
    // Submissão do formulário
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Limpar mensagens de erro anteriores
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        
        // Validações
        let hasError = false;
        
        const cpf = cpfInput.value.replace(/\D/g, '');
        if (!cpf) {
            cpfInput.classList.add('is-invalid');
            document.getElementById('cpf-error').textContent = 'CPF é obrigatório';
            hasError = true;
        } else if (!validateCPF(cpf)) {
            cpfInput.classList.add('is-invalid');
            document.getElementById('cpf-error').textContent = 'CPF inválido';
            hasError = true;
        }
        
        if (!senhaInput.value.trim()) {
            senhaInput.classList.add('is-invalid');
            document.getElementById('senha-error').textContent = 'Senha é obrigatória';
            hasError = true;
        }
        
        if (hasError) {
            return;
        }
        
        // Salvar ou remover CPF do localStorage
        if (lembrarCheckbox.checked) {
            localStorage.setItem('saved_cpf', cpfInput.value);
        } else {
            localStorage.removeItem('saved_cpf');
        }
        
        // Exibir loading
        loginBtn.disabled = true;
        loginBtn.querySelector('.btn-text').classList.add('d-none');
        loginBtn.querySelector('.loading').classList.remove('d-none');
        
        // Submeter formulário
        const formData = new FormData(this);
        
        fetch('/auth/login', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sucesso - redirecionar
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = '/dashboard';
                }
            } else {
                // Erro - exibir mensagem
                showAlert(data.message || 'Erro no login. Verifique suas credenciais.', 'danger');
                
                // Focar no primeiro campo com erro
                if (data.field) {
                    const errorField = document.getElementById(data.field);
                    if (errorField) {
                        errorField.classList.add('is-invalid');
                        errorField.focus();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro de conexão. Tente novamente.', 'danger');
        })
        .finally(() => {
            // Ocultar loading
            loginBtn.disabled = false;
            loginBtn.querySelector('.btn-text').classList.remove('d-none');
            loginBtn.querySelector('.loading').classList.add('d-none');
        });
    });
    
    // Rate limiting visual (desabilitar botão por alguns segundos após tentativas falhadas)
    const attemptCount = sessionStorage.getItem('login_attempts') || 0;
    if (attemptCount >= 3) {
        const lastAttempt = sessionStorage.getItem('last_attempt');
        const now = new Date().getTime();
        const timeDiff = now - (lastAttempt || 0);
        
        // 5 minutos = 300000ms
        if (timeDiff < 300000) {
            const remaining = Math.ceil((300000 - timeDiff) / 1000);
            loginBtn.disabled = true;
            loginBtn.innerHTML = `<i class="fas fa-clock me-2"></i>Aguarde ${remaining}s`;
            
            const countdown = setInterval(() => {
                const newRemaining = Math.ceil((300000 - (new Date().getTime() - lastAttempt)) / 1000);
                if (newRemaining <= 0) {
                    clearInterval(countdown);
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Entrar';
                    sessionStorage.removeItem('login_attempts');
                    sessionStorage.removeItem('last_attempt');
                } else {
                    loginBtn.innerHTML = `<i class="fas fa-clock me-2"></i>Aguarde ${newRemaining}s`;
                }
            }, 1000);
        }
    }
});

// Capturar tentativas falhadas para rate limiting visual
window.addEventListener('beforeunload', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error')) {
        const currentAttempts = parseInt(sessionStorage.getItem('login_attempts') || 0);
        sessionStorage.setItem('login_attempts', currentAttempts + 1);
        sessionStorage.setItem('last_attempt', new Date().getTime());
    }
});
</script>

<?php
$content = ob_get_clean();

// Incluir layout
include __DIR__ . '/../layouts/auth.php';
?>