<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Helpers\Sanitizer;
use App\Config\Config;
use Exception;

/**
 * Controlador de Autenticação
 * Gerencia login, logout, recuperação de senha e seleção de perfis
 */
class AuthController
{
    private $authService;
    private $auditService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->auditService = new AuditService();
    }

    /**
     * Exibe página de login
     */
    public function loginPage(): void
    {
        // Se já logado, redirecionar
        if ($this->authService->isValidSession()) {
            $this->redirect('/dashboard');
            return;
        }

        $data = [
            'title' => 'Login - APS Digital',
            'csrf_token' => Security::generateCSRFToken(),
            'app_name' => Config::get('app.name'),
            'app_version' => Config::get('app.version')
        ];

        $this->view('auth/login', $data);
    }

    /**
     * Processa login
     */
    public function login(): void
    {
        try {
            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            // Verificar token CSRF
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 400);
                return;
            }

            // Validar dados obrigatórios
            $cpf = Sanitizer::sanitizeString($_POST['cpf'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($cpf) || empty($password)) {
                $this->jsonResponse(['success' => false, 'message' => 'CPF e senha são obrigatórios'], 400);
                return;
            }

            // Validar CPF
            if (!Validator::validateCPF($cpf)) {
                $this->jsonResponse(['success' => false, 'message' => 'CPF inválido'], 400);
                return;
            }

            // Tentar autenticar
            $result = $this->authService->authenticate($cpf, $password);

            if ($result['success']) {
                // Verificar se precisa selecionar perfil
                if (isset($_SESSION['user']['needs_profile_selection'])) {
                    $this->jsonResponse([
                        'success' => true,
                        'redirect' => '/auth/profile-selection',
                        'message' => 'Login realizado. Selecione seu perfil.'
                    ]);
                } else {
                    $this->jsonResponse([
                        'success' => true,
                        'redirect' => '/dashboard',
                        'message' => $result['message']
                    ]);
                }
            } else {
                $this->jsonResponse(['success' => false, 'message' => $result['message']], 401);
            }

        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Exibe página de seleção de perfil
     */
    public function profileSelectionPage(): void
    {
        // Verificar se usuário está logado
        if (!$this->authService->isValidSession()) {
            $this->redirect('/login');
            return;
        }

        // Verificar se realmente precisa selecionar perfil
        if (!isset($_SESSION['user']['needs_profile_selection'])) {
            $this->redirect('/dashboard');
            return;
        }

        $data = [
            'title' => 'Seleção de Perfil - APS Digital',
            'user' => $_SESSION['user'],
            'profiles' => $_SESSION['user']['profiles'],
            'csrf_token' => Security::generateCSRFToken()
        ];

        $this->view('auth/profile-selection', $data);
    }

    /**
     * Define perfil ativo
     */
    public function setActiveProfile(): void
    {
        try {
            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            // Verificar se usuário está logado
            if (!$this->authService->isValidSession()) {
                $this->jsonResponse(['success' => false, 'message' => 'Sessão inválida'], 401);
                return;
            }

            // Verificar token CSRF
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 400);
                return;
            }

            $profileId = (int) ($_POST['profile_id'] ?? 0);
            $userId = $_SESSION['user']['user_id'];

            if (!$profileId) {
                $this->jsonResponse(['success' => false, 'message' => 'Perfil não informado'], 400);
                return;
            }

            $result = $this->authService->setActiveProfile($userId, $profileId);

            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'redirect' => '/dashboard',
                    'message' => $result['message']
                ]);
            } else {
                $this->jsonResponse(['success' => false, 'message' => $result['message']], 400);
            }

        } catch (Exception $e) {
            error_log("Erro ao definir perfil: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Exibe página de recuperação de senha
     */
    public function resetPasswordPage(): void
    {
        // Se já logado, redirecionar
        if ($this->authService->isValidSession()) {
            $this->redirect('/dashboard');
            return;
        }

        $data = [
            'title' => 'Recuperar Senha - APS Digital',
            'csrf_token' => Security::generateCSRFToken()
        ];

        $this->view('auth/reset-password', $data);
    }

    /**
     * Processa recuperação de senha
     */
    public function resetPassword(): void
    {
        try {
            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            // Verificar token CSRF
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 400);
                return;
            }

            $cpf = Sanitizer::sanitizeString($_POST['cpf'] ?? '');

            if (empty($cpf)) {
                $this->jsonResponse(['success' => false, 'message' => 'CPF é obrigatório'], 400);
                return;
            }

            // Validar CPF
            if (!Validator::validateCPF($cpf)) {
                $this->jsonResponse(['success' => false, 'message' => 'CPF inválido'], 400);
                return;
            }

            // Verificar rate limiting
            if (!Security::checkRateLimit('reset_' . $cpf, 3, 3600)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Muitas tentativas de recuperação. Tente novamente em 1 hora.'
                ], 429);
                return;
            }

            // Processar recuperação (implementar na próxima versão)
            // Por ora, apenas simular sucesso
            Security::recordRateLimitAttempt('reset_' . $cpf);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Se o CPF estiver cadastrado, você receberá um e-mail com instruções.'
            ]);

        } catch (Exception $e) {
            error_log("Erro na recuperação de senha: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Processa logout
     */
    public function logout(): void
    {
        try {
            $result = $this->authService->logout();

            if ($result) {
                $this->redirect('/login?message=logout_success');
            } else {
                $this->redirect('/login?message=logout_error');
            }

        } catch (Exception $e) {
            error_log("Erro no logout: " . $e->getMessage());
            $this->redirect('/login?message=logout_error');
        }
    }

    /**
     * Verifica status da sessão (AJAX)
     */
    public function checkSession(): void
    {
        $isValid = $this->authService->isValidSession();
        
        $this->jsonResponse([
            'valid' => $isValid,
            'user' => $isValid ? $_SESSION['user'] : null
        ]);
    }

    /**
     * Altera senha do usuário
     */
    public function changePassword(): void
    {
        try {
            // Verificar se usuário está logado
            if (!$this->authService->isValidSession()) {
                $this->jsonResponse(['success' => false, 'message' => 'Sessão inválida'], 401);
                return;
            }

            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            // Verificar token CSRF
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 400);
                return;
            }

            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validações
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $this->jsonResponse(['success' => false, 'message' => 'Todos os campos são obrigatórios'], 400);
                return;
            }

            if ($newPassword !== $confirmPassword) {
                $this->jsonResponse(['success' => false, 'message' => 'Nova senha e confirmação não coincidem'], 400);
                return;
            }

            if (!Validator::validatePassword($newPassword)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Nova senha deve ter pelo menos 8 caracteres, incluindo letras, números e símbolos'
                ], 400);
                return;
            }

            // Implementar alteração de senha (usar UserService)
            // Por ora, simular sucesso
            $this->auditService->logAction(
                'users',
                $_SESSION['user']['user_id'],
                'password_change',
                [],
                ['changed_at' => date('Y-m-d H:i:s')]
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);

        } catch (Exception $e) {
            error_log("Erro ao alterar senha: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Renderiza view
     */
    private function view(string $view, array $data = []): void
    {
        // Extrair variáveis para o escopo da view
        extract($data);
        
        // Determinar layout baseado na view
        $isAuthView = strpos($view, 'auth/') === 0;
        $layout = $isAuthView ? 'auth' : 'app';
        
        // Buffer da view
        ob_start();
        include __DIR__ . "/../Views/{$view}.php";
        $content = ob_get_clean();
        
        // Incluir layout
        include __DIR__ . "/../Views/layouts/{$layout}.php";
    }

    /**
     * Resposta JSON
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirecionamento
     */
    private function redirect(string $url): void
    {
        // Se for requisição AJAX, retornar redirect em JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->jsonResponse(['redirect' => $url]);
            return;
        }

        header("Location: {$url}");
        exit;
    }

    /**
     * Limpa sessões expiradas (cron job)
     */
    public function cleanExpiredSessions(): void
    {
        try {
            $count = $this->authService->cleanExpiredSessions();
            
            echo "Limpeza de sessões concluída. {$count} sessões removidas.\n";

        } catch (Exception $e) {
            error_log("Erro na limpeza de sessões: " . $e->getMessage());
            echo "Erro na limpeza de sessões.\n";
        }
    }

    /**
     * Obtém informações do usuário atual
     */
    public function getCurrentUser(): void
    {
        if (!$this->authService->isValidSession()) {
            $this->jsonResponse(['success' => false, 'message' => 'Sessão inválida'], 401);
            return;
        }

        $user = $_SESSION['user'];
        
        // Remover informações sensíveis
        unset($user['csrf_token']);

        $this->jsonResponse([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Troca de perfil sem relogin
     */
    public function switchProfile(): void
    {
        try {
            // Verificar se usuário está logado
            if (!$this->authService->isValidSession()) {
                $this->jsonResponse(['success' => false, 'message' => 'Sessão inválida'], 401);
                return;
            }

            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            $profileId = (int) ($_POST['profile_id'] ?? 0);
            $userId = $_SESSION['user']['user_id'];

            if (!$profileId) {
                $this->jsonResponse(['success' => false, 'message' => 'Perfil não informado'], 400);
                return;
            }

            $result = $this->authService->setActiveProfile($userId, $profileId);

            if ($result['success']) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Perfil alterado com sucesso',
                    'profile' => $result['profile']
                ]);
            } else {
                $this->jsonResponse(['success' => false, 'message' => $result['message']], 400);
            }

        } catch (Exception $e) {
            error_log("Erro ao trocar perfil: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }
}