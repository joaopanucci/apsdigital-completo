<?php

namespace App\Middleware;

use App\Helpers\Security;
use App\Config\Database;
use App\Services\AuditService;

/**
 * Middleware de autenticação
 * 
 * @package App\Middleware
 * @author SES-MS
 * @version 2.0.0
 */
class AuthMiddleware
{
    /**
     * Verifica se usuário está autenticado
     * 
     * @return bool
     */
    public static function handle(): bool
    {
        // Inicia sessão se não estiver ativa
        if (session_status() === PHP_SESSION_NONE) {
            self::startSecureSession();
        }

        // Verifica se há sessão ativa
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Verifica se sessão não expirou
        if (self::isSessionExpired()) {
            self::destroySession();
            return false;
        }

        // Verifica se usuário ainda existe e está ativo
        if (!self::isUserValid($_SESSION['user_id'])) {
            self::destroySession();
            return false;
        }

        // Atualiza último acesso
        self::updateLastAccess();

        return true;
    }

    /**
     * Inicia sessão segura
     */
    private static function startSecureSession(): void
    {
        // Configurações de segurança da sessão
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();

        // Regenera ID da sessão periodicamente
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Verifica se sessão expirou
     * 
     * @return bool
     */
    private static function isSessionExpired(): bool
    {
        $sessionLifetime = $_ENV['SESSION_LIFETIME'] ?? 7200; // 2 horas padrão
        
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }

        return (time() - $_SESSION['last_activity']) > $sessionLifetime;
    }

    /**
     * Verifica se usuário é válido
     * 
     * @param int $userId
     * @return bool
     */
    private static function isUserValid(int $userId): bool
    {
        try {
            $user = Database::fetch(
                "SELECT id, ativo FROM tb_usuarios WHERE id = ?",
                [$userId]
            );

            return $user && $user['ativo'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Atualiza último acesso
     */
    private static function updateLastAccess(): void
    {
        $_SESSION['last_activity'] = time();

        // Atualiza no banco de dados (assíncrono, não bloqueia)
        try {
            Database::execute(
                "UPDATE tb_usuarios SET dt_ultimo_acesso = NOW() WHERE id = ?",
                [$_SESSION['user_id']]
            );
        } catch (\Exception $e) {
            // Log silencioso do erro, não interrompe execução
            error_log("Erro ao atualizar último acesso: " . $e->getMessage());
        }
    }

    /**
     * Faz login do usuário
     * 
     * @param int $userId
     * @param array $userData
     * @return bool
     */
    public static function login(int $userId, array $userData = []): bool
    {
        self::startSecureSession();

        // Remove dados anteriores da sessão
        session_unset();

        // Define dados da sessão
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_cpf'] = $userData['cpf'] ?? '';
        $_SESSION['user_name'] = $userData['nome'] ?? '';
        $_SESSION['user_email'] = $userData['email'] ?? '';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = Security::getClientIP();
        $_SESSION['user_agent'] = Security::getUserAgent();

        // Regenera ID da sessão por segurança
        session_regenerate_id(true);

        // Registra sessão no banco
        self::registerSession($userId);

        // Log de auditoria
        AuditService::logUserAction($userId, 'login', [
            'ip' => Security::getClientIP(),
            'user_agent' => Security::getUserAgent()
        ]);

        return true;
    }

    /**
     * Registra sessão ativa no banco
     * 
     * @param int $userId
     */
    private static function registerSession(int $userId): void
    {
        try {
            $sessionId = session_id();
            $expirationTime = time() + ($_ENV['SESSION_LIFETIME'] ?? 7200);

            Database::execute(
                "INSERT INTO tb_sessoes (id, id_usuario, ip_address, user_agent, dt_expiracao) 
                 VALUES (?, ?, ?, ?, ?) 
                 ON CONFLICT (id) DO UPDATE SET 
                 dt_ultimo_acesso = NOW(), dt_expiracao = ?",
                [
                    $sessionId,
                    $userId,
                    Security::getClientIP(),
                    Security::getUserAgent(),
                    date('Y-m-d H:i:s', $expirationTime),
                    date('Y-m-d H:i:s', $expirationTime)
                ]
            );
        } catch (\Exception $e) {
            error_log("Erro ao registrar sessão: " . $e->getMessage());
        }
    }

    /**
     * Faz logout do usuário
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;

        // Remove sessão do banco
        if ($userId) {
            try {
                Database::execute(
                    "UPDATE tb_sessoes SET ativo = false WHERE id_usuario = ? AND id = ?",
                    [$userId, session_id()]
                );

                // Log de auditoria
                AuditService::logUserAction($userId, 'logout', [
                    'ip' => Security::getClientIP()
                ]);
            } catch (\Exception $e) {
                error_log("Erro ao desativar sessão: " . $e->getMessage());
            }
        }

        self::destroySession();
    }

    /**
     * Destrói sessão
     */
    private static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Remove cookie da sessão
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }

    /**
     * Obtém dados do usuário logado
     * 
     * @return array|null
     */
    public static function getUser(): ?array
    {
        if (!self::handle()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'cpf' => $_SESSION['user_cpf'] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'active_profile_id' => $_SESSION['active_profile_id'] ?? null,
            'active_profile_name' => $_SESSION['active_profile_name'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null
        ];
    }

    /**
     * Define perfil ativo na sessão
     * 
     * @param int $profileId
     * @param string $profileName
     * @param array $profileData
     */
    public static function setActiveProfile(int $profileId, string $profileName, array $profileData = []): void
    {
        $_SESSION['active_profile_id'] = $profileId;
        $_SESSION['active_profile_name'] = $profileName;
        $_SESSION['profile_ibge'] = $profileData['ibge'] ?? null;
        $_SESSION['profile_cnes'] = $profileData['cnes'] ?? null;
        $_SESSION['profile_permissions'] = $profileData['permissions'] ?? [];

        // Atualiza no banco
        try {
            Database::execute(
                "UPDATE tb_sessoes SET id_perfil_ativo = ?, dt_ultimo_acesso = NOW() WHERE id = ?",
                [$profileId, session_id()]
            );
        } catch (\Exception $e) {
            error_log("Erro ao atualizar perfil ativo: " . $e->getMessage());
        }
    }

    /**
     * Verifica se usuário tem perfil ativo
     * 
     * @return bool
     */
    public static function hasActiveProfile(): bool
    {
        return isset($_SESSION['active_profile_id']);
    }

    /**
     * Obtém perfil ativo
     * 
     * @return array|null
     */
    public static function getActiveProfile(): ?array
    {
        if (!self::hasActiveProfile()) {
            return null;
        }

        return [
            'id' => $_SESSION['active_profile_id'],
            'name' => $_SESSION['active_profile_name'] ?? '',
            'ibge' => $_SESSION['profile_ibge'] ?? null,
            'cnes' => $_SESSION['profile_cnes'] ?? null,
            'permissions' => $_SESSION['profile_permissions'] ?? []
        ];
    }

    /**
     * Limpa sessões expiradas do banco
     */
    public static function cleanExpiredSessions(): void
    {
        try {
            Database::execute(
                "DELETE FROM tb_sessoes WHERE dt_expiracao < NOW() OR ativo = false"
            );
        } catch (\Exception $e) {
            error_log("Erro ao limpar sessões expiradas: " . $e->getMessage());
        }
    }

    /**
     * Força logout de todas as sessões de um usuário
     * 
     * @param int $userId
     */
    public static function forceLogoutUser(int $userId): void
    {
        try {
            Database::execute(
                "UPDATE tb_sessoes SET ativo = false WHERE id_usuario = ?",
                [$userId]
            );

            AuditService::logUserAction($userId, 'force_logout', [
                'reason' => 'Logout forçado pelo administrador'
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao forçar logout: " . $e->getMessage());
        }
    }

    /**
     * Obtém estatísticas de sessões
     * 
     * @return array
     */
    public static function getSessionStats(): array
    {
        try {
            $stats = Database::fetchAll(
                "SELECT 
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN ativo = true THEN 1 END) as active_sessions,
                    COUNT(DISTINCT id_usuario) as unique_users
                 FROM tb_sessoes 
                 WHERE dt_expiracao > NOW()"
            );

            return $stats[0] ?? [
                'total_sessions' => 0,
                'active_sessions' => 0,
                'unique_users' => 0
            ];
        } catch (\Exception $e) {
            return [
                'total_sessions' => 0,
                'active_sessions' => 0,
                'unique_users' => 0
            ];
        }
    }

    /**
     * Middleware para rotas que requerem perfil ativo
     * 
     * @return bool
     */
    public static function requireProfile(): bool
    {
        return self::handle() && self::hasActiveProfile();
    }

    /**
     * Redireciona para login se não autenticado
     */
    public static function redirectIfNotAuthenticated(): void
    {
        if (!self::handle()) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Redireciona para seleção de perfil se necessário
     */
    public static function redirectIfNoProfile(): void
    {
        if (self::handle() && !self::hasActiveProfile()) {
            header('Location: /profile-selection');
            exit;
        }
    }
}