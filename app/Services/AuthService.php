<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Helpers\Sanitizer;
use App\Config\Database;
use PDO;
use Exception;

/**
 * Serviço de Autenticação
 * Gerencia autenticação, autorização e sessões de usuários
 */
class AuthService
{
    private $pdo;
    private $userModel;
    private $userProfileModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->userModel = new User();
        $this->userProfileModel = new UserProfile();
    }

    /**
     * Autentica um usuário pelo CPF e senha
     */
    public function authenticate(string $cpf, string $password): array
    {
        try {
            // Sanitizar dados de entrada
            $cpf = Sanitizer::sanitizeString($cpf);
            
            // Validar CPF
            if (!Validator::validateCPF($cpf)) {
                return [
                    'success' => false,
                    'message' => 'CPF inválido'
                ];
            }

            // Verificar rate limiting
            if (!Security::checkRateLimit('login_' . $cpf, 5, 900)) { // 5 tentativas em 15 min
                return [
                    'success' => false,
                    'message' => 'Muitas tentativas de login. Tente novamente em 15 minutos.'
                ];
            }

            // Buscar usuário
            $user = $this->userModel->findByCpf($cpf);
            if (!$user) {
                Security::recordRateLimitAttempt('login_' . $cpf);
                return [
                    'success' => false,
                    'message' => 'CPF ou senha inválidos'
                ];
            }

            // Verificar se usuário está ativo
            if (!$user['active']) {
                return [
                    'success' => false,
                    'message' => 'Usuário inativo. Entre em contato com o administrador.'
                ];
            }

            // Verificar senha
            if (!password_verify($password, $user['password_hash'])) {
                Security::recordRateLimitAttempt('login_' . $cpf);
                $this->logFailedLogin($user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
                return [
                    'success' => false,
                    'message' => 'CPF ou senha inválidos'
                ];
            }

            // Buscar perfis do usuário
            $profiles = $this->userProfileModel->getUserProfiles($user['id']);
            if (empty($profiles)) {
                return [
                    'success' => false,
                    'message' => 'Usuário sem perfil de acesso definido. Entre em contato com o administrador.'
                ];
            }

            // Iniciar sessão
            $sessionData = $this->createUserSession($user, $profiles);
            
            // Registrar login bem-sucedido
            $this->logSuccessfulLogin($user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            // Atualizar último acesso
            $this->userModel->updateLastAccess($user['id']);

            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => $sessionData
            ];

        } catch (Exception $e) {
            error_log("Erro na autenticação: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro interno do servidor'
            ];
        }
    }

    /**
     * Cria sessão para o usuário autenticado
     */
    private function createUserSession(array $user, array $profiles): array
    {
        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);

        $sessionData = [
            'user_id' => $user['id'],
            'cpf' => $user['cpf'],
            'name' => $user['name'],
            'email' => $user['email'],
            'photo' => $user['photo'],
            'active' => $user['active'],
            'profiles' => $profiles,
            'current_profile' => null,
            'permissions' => [],
            'login_time' => time(),
            'last_activity' => time(),
            'csrf_token' => Security::generateCSRFToken()
        ];

        // Definir perfil padrão (primeiro da lista ou mais relevante)
        if (count($profiles) === 1) {
            $sessionData['current_profile'] = $profiles[0];
            $sessionData['permissions'] = $this->getProfilePermissions($profiles[0]['profile_id']);
        } else {
            // Se múltiplos perfis, usuário deve selecionar
            $sessionData['needs_profile_selection'] = true;
        }

        // Salvar na sessão
        $_SESSION['user'] = $sessionData;

        // Registrar sessão no banco
        $this->registerSessionInDatabase($user['id'], session_id());

        return $sessionData;
    }

    /**
     * Define o perfil ativo do usuário
     */
    public function setActiveProfile(int $userId, int $profileId): array
    {
        try {
            // Verificar se usuário tem acesso ao perfil
            $userProfiles = $this->userProfileModel->getUserProfiles($userId);
            $targetProfile = null;
            
            foreach ($userProfiles as $profile) {
                if ($profile['profile_id'] == $profileId) {
                    $targetProfile = $profile;
                    break;
                }
            }

            if (!$targetProfile) {
                return [
                    'success' => false,
                    'message' => 'Perfil não encontrado ou usuário não tem acesso'
                ];
            }

            // Verificar se perfil está ativo
            if (!$targetProfile['active']) {
                return [
                    'success' => false,
                    'message' => 'Perfil inativo'
                ];
            }

            // Atualizar sessão
            $_SESSION['user']['current_profile'] = $targetProfile;
            $_SESSION['user']['permissions'] = $this->getProfilePermissions($profileId);
            $_SESSION['user']['last_activity'] = time();
            unset($_SESSION['user']['needs_profile_selection']);

            return [
                'success' => true,
                'message' => 'Perfil ativado com sucesso',
                'profile' => $targetProfile
            ];

        } catch (Exception $e) {
            error_log("Erro ao definir perfil ativo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro interno do servidor'
            ];
        }
    }

    /**
     * Obtém permissões de um perfil
     */
    private function getProfilePermissions(int $profileId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT pp.permission_name, pp.can_create, pp.can_read, 
                       pp.can_update, pp.can_delete, pp.can_export
                FROM profile_permissions pp
                WHERE pp.profile_id = ? AND pp.active = true
            ");
            $stmt->execute([$profileId]);
            
            $permissions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $permissions[$row['permission_name']] = [
                    'create' => $row['can_create'],
                    'read' => $row['can_read'],
                    'update' => $row['can_update'],
                    'delete' => $row['can_delete'],
                    'export' => $row['can_export']
                ];
            }

            return $permissions;

        } catch (Exception $e) {
            error_log("Erro ao buscar permissões: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica se usuário tem permissão específica
     */
    public function hasPermission(string $permission, string $action = 'read'): bool
    {
        if (!isset($_SESSION['user']['permissions'][$permission])) {
            return false;
        }

        return $_SESSION['user']['permissions'][$permission][$action] ?? false;
    }

    /**
     * Verifica se usuário tem acesso a município
     */
    public function hasAccessToMunicipality(int $municipalityId): bool
    {
        if (!isset($_SESSION['user']['current_profile'])) {
            return false;
        }

        $profile = $_SESSION['user']['current_profile'];
        
        // Nacional e Regional têm acesso a todos
        if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
            return true;
        }

        // Municipal e Unidade têm acesso apenas ao seu município
        if ($profile['municipality_id']) {
            return $profile['municipality_id'] == $municipalityId;
        }

        return false;
    }

    /**
     * Faz logout do usuário
     */
    public function logout(): bool
    {
        try {
            if (isset($_SESSION['user']['user_id'])) {
                // Remover sessão do banco
                $this->removeSessionFromDatabase($_SESSION['user']['user_id'], session_id());
                
                // Registrar logout
                $this->logLogout($_SESSION['user']['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            }

            // Limpar sessão
            session_unset();
            session_destroy();

            // Regenerar ID por segurança
            session_start();
            session_regenerate_id(true);

            return true;

        } catch (Exception $e) {
            error_log("Erro no logout: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se sessão é válida
     */
    public function isValidSession(): bool
    {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        $user = $_SESSION['user'];
        
        // Verificar timeout da sessão (4 horas)
        $sessionTimeout = 4 * 60 * 60; // 4 horas em segundos
        if ((time() - $user['last_activity']) > $sessionTimeout) {
            $this->logout();
            return false;
        }

        // Verificar se usuário ainda está ativo
        $currentUser = $this->userModel->findById($user['user_id']);
        if (!$currentUser || !$currentUser['active']) {
            $this->logout();
            return false;
        }

        // Atualizar última atividade
        $_SESSION['user']['last_activity'] = time();

        return true;
    }

    /**
     * Registra sessão no banco de dados
     */
    private function registerSessionInDatabase(int $userId, string $sessionId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (session_id) DO UPDATE SET
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $userId,
                $sessionId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

        } catch (Exception $e) {
            error_log("Erro ao registrar sessão: " . $e->getMessage());
        }
    }

    /**
     * Remove sessão do banco de dados
     */
    private function removeSessionFromDatabase(int $userId, string $sessionId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_sessions 
                WHERE user_id = ? AND session_id = ?
            ");
            $stmt->execute([$userId, $sessionId]);

        } catch (Exception $e) {
            error_log("Erro ao remover sessão: " . $e->getMessage());
        }
    }

    /**
     * Registra tentativa de login falhada
     */
    private function logFailedLogin(int $userId, string $ipAddress): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (table_name, record_id, action, user_id, 
                                      old_values, new_values, ip_address, created_at)
                VALUES ('users', ?, 'failed_login', ?, '{}', 
                       ?::jsonb, ?, CURRENT_TIMESTAMP)
            ");
            
            $details = json_encode([
                'ip_address' => $ipAddress,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $stmt->execute([$userId, $userId, $details, $ipAddress]);

        } catch (Exception $e) {
            error_log("Erro ao registrar falha de login: " . $e->getMessage());
        }
    }

    /**
     * Registra login bem-sucedido
     */
    private function logSuccessfulLogin(int $userId, string $ipAddress): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (table_name, record_id, action, user_id, 
                                      old_values, new_values, ip_address, created_at)
                VALUES ('users', ?, 'login', ?, '{}', 
                       ?::jsonb, ?, CURRENT_TIMESTAMP)
            ");
            
            $details = json_encode([
                'ip_address' => $ipAddress,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $stmt->execute([$userId, $userId, $details, $ipAddress]);

        } catch (Exception $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
        }
    }

    /**
     * Registra logout
     */
    private function logLogout(int $userId, string $ipAddress): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (table_name, record_id, action, user_id, 
                                      old_values, new_values, ip_address, created_at)
                VALUES ('users', ?, 'logout', ?, '{}', 
                       ?::jsonb, ?, CURRENT_TIMESTAMP)
            ");
            
            $details = json_encode([
                'ip_address' => $ipAddress,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            $stmt->execute([$userId, $userId, $details, $ipAddress]);

        } catch (Exception $e) {
            error_log("Erro ao registrar logout: " . $e->getMessage());
        }
    }

    /**
     * Limpa sessões expiradas
     */
    public function cleanExpiredSessions(): int
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM user_sessions 
                WHERE updated_at < NOW() - INTERVAL '4 hours'
            ");
            $stmt->execute();
            
            return $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Erro ao limpar sessões expiradas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtém sessões ativas do usuário
     */
    public function getActiveSessions(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT session_id, ip_address, user_agent, created_at, updated_at
                FROM user_sessions 
                WHERE user_id = ? 
                ORDER BY updated_at DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Erro ao buscar sessões ativas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Encerra todas as sessões de um usuário (exceto a atual)
     */
    public function terminateAllSessions(int $userId, string $currentSessionId = null): bool
    {
        try {
            if ($currentSessionId) {
                $stmt = $this->pdo->prepare("
                    DELETE FROM user_sessions 
                    WHERE user_id = ? AND session_id != ?
                ");
                $stmt->execute([$userId, $currentSessionId]);
            } else {
                $stmt = $this->pdo->prepare("
                    DELETE FROM user_sessions WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
            }

            return true;

        } catch (Exception $e) {
            error_log("Erro ao encerrar sessões: " . $e->getMessage());
            return false;
        }
    }
}