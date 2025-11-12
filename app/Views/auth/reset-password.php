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

// Verificar se há mensagem
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// Gerar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
$title = 'Recuperar Senha - APS Digital';
$app_version = '2.0.0';

// Buffer do conteúdo da página
ob_start();
?>

<div class="auth-header">
    <h1>
        <i class="fas fa-key me-2"></i>
        Recuperar Senha
    </h1>
    <p class="subtitle">Informe seu CPF para receber uma nova senha</p>
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

    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Instruções:</strong>
        <ul class="mb-0 mt-2">
            <li>Digite seu CPF cadastrado no sistema</li>
            <li>Uma nova senha será gerada e enviada para seu e-mail</li>
            <li>Use a nova senha para fazer login</li>
            <li>Altere a senha após o primeiro acesso</li>
        </ul>
    </div>

    <form id="resetForm" action="/auth/reset-password" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="mb-4">
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
            <div class="form-text">
                <i class="fas fa-shield-alt me-1"></i>
                Seus dados estão protegidos e serão usados apenas para recuperação da senha
            </div>
        </div>

        <button type="submit" class="btn btn-primary" id="resetBtn">
            <span class="btn-text">
                <i class="fas fa-paper-plane me-2"></i>
                Enviar Nova Senha
            </span>
            <span class="loading d-none">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Enviando...
            </span>
        </button>

        <div class="text-center mt-4">
            <a href="/auth/login" class="btn btn-link">
                <i class="fas fa-arrow-left me-1"></i>
                Voltar ao Login
            </a>
        </div>
    </form>
</div>

<div class="auth-footer">
    <div class="text-center">
        <p class="mb-2">
            <i class="fas fa-phone me-1"></i>
            <strong>Suporte Técnico:</strong> (67) 3318-1000
        </p>
        <p class="mb-0">
            <i class="fas fa-envelope me-1"></i>
            <strong>E-mail:</strong> apsdigital@ses.ms.gov.br
        </p>
        <hr class="my-3">
        <div class="row text-center">
            <div class="col-6">
                <i class="fas fa-clock me-1"></i>
                <small>Segunda à Sexta<br>08:00 às 17:00</small>
            </div>
            <div class="col-6">
                <i class="fas fa-business-time me-1"></i>
                <small>Tempo de resposta<br>até 24 horas</small>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.getElementById('resetForm');
    const cpfInput = document.getElementById('cpf');
    const resetBtn = document.getElementById('resetBtn');
    
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
    resetForm.addEventListener('submit', function(e) {
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
        
        if (hasError) {
            return;
        }
        
        // Exibir loading
        resetBtn.disabled = true;
        resetBtn.querySelector('.btn-text').classList.add('d-none');
        resetBtn.querySelector('.loading').classList.remove('d-none');
        
        // Submeter formulário
        const formData = new FormData(this);
        
        fetch('/auth/reset-password', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Sucesso
                showAlert(data.message || 'Nova senha enviada para seu e-mail!', 'success');
                
                // Limpar formulário
                resetForm.reset();
                
                // Redirecionar para login após alguns segundos
                setTimeout(() => {
                    window.location.href = '/auth/login?message=password_sent';
                }, 3000);
                
            } else {
                // Erro
                showAlert(data.message || 'Erro ao processar solicitação. Tente novamente.', 'danger');
                
                // Focar no campo de CPF se houver erro
                if (data.field === 'cpf') {
                    cpfInput.classList.add('is-invalid');
                    cpfInput.focus();
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro de conexão. Verifique sua internet e tente novamente.', 'danger');
        })
        .finally(() => {
            // Ocultar loading
            resetBtn.disabled = false;
            resetBtn.querySelector('.btn-text').classList.remove('d-none');
            resetBtn.querySelector('.loading').classList.add('d-none');
        });
    });
    
    // Rate limiting visual
    const attemptCount = sessionStorage.getItem('reset_attempts') || 0;
    if (attemptCount >= 3) {
        const lastAttempt = sessionStorage.getItem('last_reset_attempt');
        const now = new Date().getTime();
        const timeDiff = now - (lastAttempt || 0);
        
        // 10 minutos = 600000ms
        if (timeDiff < 600000) {
            const remaining = Math.ceil((600000 - timeDiff) / 1000);
            resetBtn.disabled = true;
            
            const updateCountdown = () => {
                const newRemaining = Math.ceil((600000 - (new Date().getTime() - lastAttempt)) / 1000);
                if (newRemaining <= 0) {
                    resetBtn.disabled = false;
                    resetBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Nova Senha';
                    sessionStorage.removeItem('reset_attempts');
                    sessionStorage.removeItem('last_reset_attempt');
                } else {
                    const minutes = Math.floor(newRemaining / 60);
                    const seconds = newRemaining % 60;
                    resetBtn.innerHTML = `<i class="fas fa-clock me-2"></i>Aguarde ${minutes}:${seconds.toString().padStart(2, '0')}`;
                    setTimeout(updateCountdown, 1000);
                }
            };
            
            updateCountdown();
        }
    }
});

// Monitorar tentativas de reset para rate limiting
let resetAttempts = 0;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    
    form.addEventListener('submit', function() {
        resetAttempts++;
        
        if (resetAttempts >= 3) {
            sessionStorage.setItem('reset_attempts', resetAttempts);
            sessionStorage.setItem('last_reset_attempt', new Date().getTime());
        }
    });
});

// Verificar parâmetros da URL para mensagens
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('message')) {
    const messageType = urlParams.get('message');
    
    setTimeout(() => {
        switch (messageType) {
            case 'invalid_cpf':
                showAlert('CPF não encontrado no sistema. Verifique o número informado.', 'warning');
                break;
            case 'email_error':
                showAlert('Erro no envio do e-mail. Tente novamente ou entre em contato com o suporte.', 'danger');
                break;
            case 'rate_limit':
                showAlert('Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.', 'warning');
                break;
        }
    }, 500);
}
</script>

<?php
$content = ob_get_clean();

// Incluir layout
include __DIR__ . '/../layouts/auth.php';
?>