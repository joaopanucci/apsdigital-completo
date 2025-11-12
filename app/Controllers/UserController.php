<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\ReportService;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Municipality;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Helpers\Sanitizer;
use App\Helpers\FileUpload;
use Exception;

/**
 * Controlador de Usuários
 * Gestão de usuários, perfis e permissões
 */
class UserController
{
    private $authService;
    private $reportService;
    private $auditService;
    private $emailService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reportService = new ReportService();
        $this->auditService = new AuditService();
        $this->emailService = new EmailService();
    }

    /**
     * Página principal de usuários
     */
    public function index(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'read')) {
                $this->redirect('/dashboard?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Filtros baseados no perfil
            $filters = [];
            if (!in_array($profile['profile_name'], ['Nacional', 'Regional']) && $profile['municipality_id']) {
                $filters['municipality_id'] = $profile['municipality_id'];
            }

            // Aplicar filtros da requisição
            $page = (int) ($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $profileFilter = $_GET['profile'] ?? '';
            $status = $_GET['status'] ?? '';

            if ($search) $filters['search'] = $search;
            if ($profileFilter) $filters['profile_name'] = $profileFilter;
            if ($status) $filters['active'] = $status === 'active';

            $userModel = new User();
            $users = $userModel->search($filters, $page, 20);

            // Obter perfis disponíveis
            $profileModel = new UserProfile();
            $availableProfiles = $profileModel->getAvailableProfiles();

            // Obter municípios (se permitido)
            $municipalities = [];
            if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                $municipalityModel = new Municipality();
                $municipalities = $municipalityModel->getAll(['active' => true]);
            }

            $data = [
                'title' => 'Usuários - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'users' => $users,
                'available_profiles' => $availableProfiles,
                'municipalities' => $municipalities,
                'filters' => $filters,
                'csrf_token' => Security::generateCSRFToken(),
                'can_create' => $this->authService->hasPermission('users', 'create'),
                'can_update' => $this->authService->hasPermission('users', 'update'),
                'can_delete' => $this->authService->hasPermission('users', 'delete')
            ];

            $this->view('users/index', $data);

        } catch (Exception $e) {
            error_log("Erro na página de usuários: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Página de criação de usuário
     */
    public function create(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'create')) {
                $this->redirect('/users?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Obter perfis disponíveis baseado no perfil atual
            $profileModel = new UserProfile();
            $availableProfiles = $profileModel->getAvailableProfiles($profile['profile_name']);

            // Obter municípios
            $municipalities = [];
            if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                $municipalityModel = new Municipality();
                $municipalities = $municipalityModel->getAll(['active' => true]);
            } elseif ($profile['municipality_id']) {
                $municipalityModel = new Municipality();
                $municipality = $municipalityModel->findById($profile['municipality_id']);
                if ($municipality) {
                    $municipalities = [$municipality];
                }
            }

            $data = [
                'title' => 'Criar Usuário - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'available_profiles' => $availableProfiles,
                'municipalities' => $municipalities,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('users/create', $data);

        } catch (Exception $e) {
            error_log("Erro na página de criação: " . $e->getMessage());
            $this->redirect('/users?error=system_error');
        }
    }

    /**
     * Processa criação de usuário
     */
    public function store(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'create')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
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

            // Sanitizar dados do usuário
            $userData = [
                'name' => Sanitizer::sanitizeString($_POST['name'] ?? ''),
                'cpf' => Sanitizer::sanitizeString($_POST['cpf'] ?? ''),
                'email' => Sanitizer::sanitizeString($_POST['email'] ?? ''),
                'telefone' => Sanitizer::sanitizeString($_POST['telefone'] ?? ''),
                'cns_profissional' => Sanitizer::sanitizeString($_POST['cns_profissional'] ?? ''),
                'profissional_cadastrante' => $_SESSION['user']['name'],
                'cpf_profissional_cadastrante' => $_SESSION['user']['cpf']
            ];

            // Validações
            $validationErrors = $this->validateUserData($userData);
            if (!empty($validationErrors)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validationErrors
                ], 400);
                return;
            }

            // Processar upload de foto se fornecida
            if (!empty($_FILES['photo']['name'])) {
                $fileUpload = new FileUpload();
                $photoResult = $fileUpload->uploadUserPhoto($_FILES['photo']);
                
                if ($photoResult['success']) {
                    $userData['photo'] = $photoResult['filename'];
                }
            }

            // Gerar senha temporária
            $temporaryPassword = Security::generatePassword();
            $userData['password'] = password_hash($temporaryPassword, PASSWORD_DEFAULT);

            // Criar usuário
            $userModel = new User();
            $userId = $userModel->create($userData);

            if (!$userId) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao criar usuário'], 500);
                return;
            }

            // Criar perfis
            $profiles = $_POST['profiles'] ?? [];
            $profileModel = new UserProfile();
            $createdProfiles = [];

            foreach ($profiles as $profileData) {
                $profileInfo = [
                    'user_id' => $userId,
                    'profile_id' => (int) $profileData['profile_id'],
                    'municipality_id' => !empty($profileData['municipality_id']) ? (int) $profileData['municipality_id'] : null,
                    'cnes' => !empty($profileData['cnes']) ? Sanitizer::sanitizeString($profileData['cnes']) : null,
                    'ine' => !empty($profileData['ine']) ? Sanitizer::sanitizeString($profileData['ine']) : null,
                    'microarea' => !empty($profileData['microarea']) ? (int) $profileData['microarea'] : null
                ];

                $profileId = $profileModel->create($profileInfo);
                if ($profileId) {
                    $createdProfiles[] = $profileId;
                }
            }

            if (empty($createdProfiles)) {
                // Remover usuário se nenhum perfil foi criado
                $userModel->delete($userId);
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao criar perfis do usuário'], 500);
                return;
            }

            // Enviar e-mail de boas-vindas
            $emailResult = $this->emailService->sendWelcomeEmail($userData, $temporaryPassword);

            // Log da ação
            $this->auditService->logAction(
                'users',
                $userId,
                'create',
                [],
                [
                    'name' => $userData['name'],
                    'cpf' => $userData['cpf'],
                    'email' => $userData['email'],
                    'profiles_count' => count($createdProfiles)
                ]
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'user_id' => $userId,
                'email_sent' => $emailResult['success'] ?? false,
                'temporary_password' => $temporaryPassword // Apenas para debug/teste
            ]);

        } catch (Exception $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Página de edição de usuário
     */
    public function edit(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'update')) {
                $this->redirect('/users?error=permission_denied');
                return;
            }

            $userId = (int) ($_GET['id'] ?? 0);
            if (!$userId) {
                $this->redirect('/users?error=invalid_user');
                return;
            }

            $userModel = new User();
            $targetUser = $userModel->findById($userId);

            if (!$targetUser) {
                $this->redirect('/users?error=user_not_found');
                return;
            }

            // Verificar se pode editar este usuário
            if (!$this->canManageUser($targetUser)) {
                $this->redirect('/users?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Obter perfis do usuário
            $profileModel = new UserProfile();
            $userProfiles = $profileModel->getUserProfiles($userId);
            $availableProfiles = $profileModel->getAvailableProfiles($profile['profile_name']);

            // Obter municípios
            $municipalities = [];
            if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                $municipalityModel = new Municipality();
                $municipalities = $municipalityModel->getAll(['active' => true]);
            }

            $data = [
                'title' => 'Editar Usuário - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'target_user' => $targetUser,
                'user_profiles' => $userProfiles,
                'available_profiles' => $availableProfiles,
                'municipalities' => $municipalities,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('users/edit', $data);

        } catch (Exception $e) {
            error_log("Erro na página de edição: " . $e->getMessage());
            $this->redirect('/users?error=system_error');
        }
    }

    /**
     * Processa atualização de usuário
     */
    public function update(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'update')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId) {
                $this->jsonResponse(['success' => false, 'message' => 'ID do usuário não informado'], 400);
                return;
            }

            $userModel = new User();
            $oldUser = $userModel->findById($userId);

            if (!$oldUser) {
                $this->jsonResponse(['success' => false, 'message' => 'Usuário não encontrado'], 404);
                return;
            }

            // Verificar se pode editar este usuário
            if (!$this->canManageUser($oldUser)) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada para este usuário'], 403);
                return;
            }

            // Sanitizar dados
            $userData = [
                'name' => Sanitizer::sanitizeString($_POST['name'] ?? ''),
                'email' => Sanitizer::sanitizeString($_POST['email'] ?? ''),
                'telefone' => Sanitizer::sanitizeString($_POST['telefone'] ?? ''),
                'cns_profissional' => Sanitizer::sanitizeString($_POST['cns_profissional'] ?? '')
            ];

            // CPF só pode ser alterado por perfis superiores
            $currentProfile = $_SESSION['user']['current_profile'];
            if (in_array($currentProfile['profile_name'], ['Nacional', 'Regional'])) {
                $userData['cpf'] = Sanitizer::sanitizeString($_POST['cpf'] ?? '');
            }

            // Validações
            $validationErrors = $this->validateUserData($userData, $userId);
            if (!empty($validationErrors)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validationErrors
                ], 400);
                return;
            }

            // Processar upload de nova foto
            if (!empty($_FILES['photo']['name'])) {
                $fileUpload = new FileUpload();
                $photoResult = $fileUpload->uploadUserPhoto($_FILES['photo']);
                
                if ($photoResult['success']) {
                    $userData['photo'] = $photoResult['filename'];
                    
                    // Remover foto antiga se existir
                    if ($oldUser['photo']) {
                        $fileUpload->deleteUserPhoto($oldUser['photo']);
                    }
                }
            }

            // Atualizar usuário
            $result = $userModel->update($userId, $userData);

            if (!$result) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao atualizar usuário'], 500);
                return;
            }

            // Log da ação
            $this->auditService->logAction(
                'users',
                $userId,
                'update',
                $oldUser,
                $userData
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso'
            ]);

        } catch (Exception $e) {
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Ativa/desativa usuário
     */
    public function toggleStatus(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'update')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId) {
                $this->jsonResponse(['success' => false, 'message' => 'ID do usuário não informado'], 400);
                return;
            }

            $userModel = new User();
            $user = $userModel->findById($userId);

            if (!$user) {
                $this->jsonResponse(['success' => false, 'message' => 'Usuário não encontrado'], 404);
                return;
            }

            // Verificar se pode gerenciar este usuário
            if (!$this->canManageUser($user)) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada para este usuário'], 403);
                return;
            }

            // Não permitir desativar próprio usuário
            if ($userId === $_SESSION['user']['user_id']) {
                $this->jsonResponse(['success' => false, 'message' => 'Não é possível desativar seu próprio usuário'], 400);
                return;
            }

            $newStatus = !$user['active'];
            $result = $userModel->update($userId, ['active' => $newStatus]);

            if (!$result) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao atualizar status'], 500);
                return;
            }

            // Log da ação
            $this->auditService->logAction(
                'users',
                $userId,
                $newStatus ? 'activate' : 'deactivate',
                ['active' => $user['active']],
                ['active' => $newStatus]
            );

            // Encerrar sessões ativas se desativado
            if (!$newStatus) {
                $this->authService->terminateAllSessions($userId);
            }

            $this->jsonResponse([
                'success' => true,
                'message' => $newStatus ? 'Usuário ativado com sucesso' : 'Usuário desativado com sucesso',
                'new_status' => $newStatus
            ]);

        } catch (Exception $e) {
            error_log("Erro ao alterar status do usuário: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Redefine senha do usuário
     */
    public function resetPassword(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'update')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId) {
                $this->jsonResponse(['success' => false, 'message' => 'ID do usuário não informado'], 400);
                return;
            }

            $userModel = new User();
            $user = $userModel->findById($userId);

            if (!$user) {
                $this->jsonResponse(['success' => false, 'message' => 'Usuário não encontrado'], 404);
                return;
            }

            // Verificar se pode gerenciar este usuário
            if (!$this->canManageUser($user)) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada para este usuário'], 403);
                return;
            }

            // Gerar nova senha
            $newPassword = Security::generatePassword();
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $result = $userModel->update($userId, ['password' => $hashedPassword]);

            if (!$result) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao redefinir senha'], 500);
                return;
            }

            // Enviar nova senha por e-mail
            $emailResult = $this->emailService->sendPasswordResetEmail($user, $newPassword);

            // Log da ação
            $this->auditService->logAction(
                'users',
                $userId,
                'password_reset',
                [],
                ['reset_by' => $_SESSION['user']['user_id']]
            );

            // Encerrar sessões ativas
            $this->authService->terminateAllSessions($userId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Senha redefinida com sucesso',
                'email_sent' => $emailResult['success'] ?? false,
                'new_password' => $newPassword // Apenas para debug/teste
            ]);

        } catch (Exception $e) {
            error_log("Erro ao redefinir senha: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Visualiza perfil do usuário
     */
    public function profile(): void
    {
        try {
            $userId = (int) ($_GET['id'] ?? $_SESSION['user']['user_id']);
            
            // Verificar permissões
            if ($userId !== $_SESSION['user']['user_id'] && !$this->checkPermission('users', 'read')) {
                $this->redirect('/dashboard?error=permission_denied');
                return;
            }

            $userModel = new User();
            $user = $userModel->findById($userId);

            if (!$user) {
                $this->redirect('/users?error=user_not_found');
                return;
            }

            // Obter perfis do usuário
            $profileModel = new UserProfile();
            $userProfiles = $profileModel->getUserProfiles($userId);

            // Obter atividades recentes se for o próprio usuário ou tiver permissão
            $recentActivities = [];
            if ($userId === $_SESSION['user']['user_id'] || $this->authService->hasPermission('audit', 'read')) {
                $activityReport = $this->auditService->getUserActivityReport($userId, 30);
                $recentActivities = $activityReport['activities'] ?? [];
            }

            $data = [
                'title' => 'Perfil do Usuário - APS Digital',
                'target_user' => $user,
                'user_profiles' => $userProfiles,
                'recent_activities' => $recentActivities,
                'is_own_profile' => $userId === $_SESSION['user']['user_id'],
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('users/profile', $data);

        } catch (Exception $e) {
            error_log("Erro na visualização do perfil: " . $e->getMessage());
            $this->redirect('/users?error=system_error');
        }
    }

    /**
     * Busca usuários (AJAX)
     */
    public function search(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'read')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $currentProfile = $_SESSION['user']['current_profile'];

            // Filtros baseados no perfil
            $filters = [];
            if (!in_array($currentProfile['profile_name'], ['Nacional', 'Regional']) && $currentProfile['municipality_id']) {
                $filters['municipality_id'] = $currentProfile['municipality_id'];
            }

            // Aplicar filtros da busca
            $searchParams = ['search', 'profile_name', 'active', 'municipality_id'];
            foreach ($searchParams as $param) {
                if (!empty($_GET[$param])) {
                    $filters[$param] = $_GET[$param];
                }
            }

            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 20);

            $userModel = new User();
            $result = $userModel->search($filters, $page, $limit);

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro na busca de usuários: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Valida dados do usuário
     */
    private function validateUserData(array $data, ?int $userId = null): array
    {
        $errors = [];

        // Validar nome
        if (empty($data['name']) || strlen($data['name']) < 2) {
            $errors['name'] = 'Nome deve ter pelo menos 2 caracteres';
        }

        // Validar CPF
        if (!empty($data['cpf']) && !Validator::validateCPF($data['cpf'])) {
            $errors['cpf'] = 'CPF inválido';
        }

        // Validar email
        if (empty($data['email']) || !Validator::validateEmail($data['email'])) {
            $errors['email'] = 'E-mail inválido';
        }

        // Verificar duplicatas
        $userModel = new User();
        
        if (!empty($data['cpf'])) {
            $existingUser = $userModel->findByCpf($data['cpf']);
            if ($existingUser && (!$userId || $existingUser['id'] !== $userId)) {
                $errors['cpf'] = 'CPF já cadastrado';
            }
        }

        $existingUser = $userModel->findByEmail($data['email']);
        if ($existingUser && (!$userId || $existingUser['id'] !== $userId)) {
            $errors['email'] = 'E-mail já cadastrado';
        }

        return $errors;
    }

    /**
     * Verifica se pode gerenciar um usuário
     */
    private function canManageUser(array $targetUser): bool
    {
        $currentProfile = $_SESSION['user']['current_profile'];
        $profileName = $currentProfile['profile_name'];

        // Nacional e Regional podem gerenciar qualquer usuário
        if (in_array($profileName, ['Nacional', 'Regional'])) {
            return true;
        }

        // Municipal pode gerenciar apenas usuários do mesmo município
        if ($profileName === 'Municipal' && $currentProfile['municipality_id']) {
            $profileModel = new UserProfile();
            $targetProfiles = $profileModel->getUserProfiles($targetUser['id']);
            
            foreach ($targetProfiles as $profile) {
                if ($profile['municipality_id'] === $currentProfile['municipality_id']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica permissões
     */
    private function checkPermission(string $resource, string $action): bool
    {
        if (!$this->authService->isValidSession()) {
            return false;
        }

        return $this->authService->hasPermission($resource, $action);
    }

    /**
     * Renderiza view
     */
    private function view(string $view, array $data = []): void
    {
        extract($data);
        
        ob_start();
        include __DIR__ . "/../Views/{$view}.php";
        $content = ob_get_clean();
        
        include __DIR__ . "/../Views/layouts/app.php";
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
        header("Location: {$url}");
        exit;
    }
}