<?php

namespace App\Middleware;

use App\Helpers\Security;
use App\Config\Config;

/**
 * Middleware de proteção CSRF
 * 
 * @package App\Middleware
 * @author SES-MS
 * @version 2.0.0
 */
class CSRFMiddleware
{
    private static string $tokenKey = 'csrf_token';
    private static string $headerKey = 'X-CSRF-Token';

    /**
     * Gera e armazena token CSRF na sessão
     * 
     * @return string
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = Security::generateCSRFToken();
        $_SESSION[self::$tokenKey] = $token;
        $_SESSION['csrf_generated_at'] = time();

        return $token;
    }

    /**
     * Obtém token CSRF atual
     * 
     * @return string|null
     */
    public static function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[self::$tokenKey] ?? null;
    }

    /**
     * Valida token CSRF
     * 
     * @param string $token
     * @return bool
     */
    public static function validateToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verifica se existe token na sessão
        if (!isset($_SESSION[self::$tokenKey])) {
            return false;
        }

        $sessionToken = $_SESSION[self::$tokenKey];

        // Verifica se token não expirou (1 hora padrão)
        $tokenAge = time() - ($_SESSION['csrf_generated_at'] ?? 0);
        $maxAge = Config::get('security.csrf_token_lifetime', 3600);
        
        if ($tokenAge > $maxAge) {
            self::regenerateToken();
            return false;
        }

        // Valida token usando comparação segura
        return Security::validateCSRFToken($token, $sessionToken);
    }

    /**
     * Middleware principal para verificação CSRF
     * 
     * @return bool
     */
    public static function handle(): bool
    {
        // Só verifica CSRF para métodos que modificam dados
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }

        // Obtém token da requisição
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            return false;
        }

        return self::validateToken($token);
    }

    /**
     * Obtém token da requisição (POST, header ou query string)
     * 
     * @return string|null
     */
    private static function getTokenFromRequest(): ?string
    {
        // Primeiro tenta POST/PUT data
        if (isset($_POST[self::$tokenKey])) {
            return $_POST[self::$tokenKey];
        }

        // Depois tenta header HTTP
        $headerName = 'HTTP_' . str_replace('-', '_', strtoupper(self::$headerKey));
        if (isset($_SERVER[$headerName])) {
            return $_SERVER[$headerName];
        }

        // Por último tenta query string (menos seguro)
        if (isset($_GET[self::$tokenKey])) {
            return $_GET[self::$tokenKey];
        }

        // Tenta JSON payload
        $jsonInput = file_get_contents('php://input');
        if ($jsonInput) {
            $data = json_decode($jsonInput, true);
            if (isset($data[self::$tokenKey])) {
                return $data[self::$tokenKey];
            }
        }

        return null;
    }

    /**
     * Regenera token CSRF
     * 
     * @return string
     */
    public static function regenerateToken(): string
    {
        return self::generateToken();
    }

    /**
     * Gera campo hidden HTML com token CSRF
     * 
     * @return string
     */
    public static function getHiddenInput(): string
    {
        $token = self::getToken() ?: self::generateToken();
        return '<input type="hidden" name="' . self::$tokenKey . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Gera meta tag para AJAX
     * 
     * @return string
     */
    public static function getMetaTag(): string
    {
        $token = self::getToken() ?: self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Obtém token para JavaScript
     * 
     * @return array
     */
    public static function getTokenForJS(): array
    {
        $token = self::getToken() ?: self::generateToken();
        return [
            'token' => $token,
            'header_name' => self::$headerKey,
            'field_name' => self::$tokenKey
        ];
    }

    /**
     * Valida referer para proteção adicional
     * 
     * @return bool
     */
    public static function validateReferer(): bool
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        $referer = $_SERVER['HTTP_REFERER'];
        $appUrl = Config::get('app.url', 'http://localhost');
        
        // Verifica se referer é do mesmo domínio
        return strpos($referer, $appUrl) === 0;
    }

    /**
     * Middleware com validação de referer
     * 
     * @param bool $checkReferer
     * @return bool
     */
    public static function handleWithReferer(bool $checkReferer = true): bool
    {
        // Verifica CSRF
        if (!self::handle()) {
            return false;
        }

        // Verifica referer se solicitado
        if ($checkReferer && !self::validateReferer()) {
            return false;
        }

        return true;
    }

    /**
     * Limpa tokens expirados da sessão
     */
    public static function cleanExpiredTokens(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $tokenAge = time() - ($_SESSION['csrf_generated_at'] ?? 0);
        $maxAge = Config::get('security.csrf_token_lifetime', 3600);

        if ($tokenAge > $maxAge) {
            unset($_SESSION[self::$tokenKey]);
            unset($_SESSION['csrf_generated_at']);
        }
    }

    /**
     * Middleware para APIs que usam token de autorização
     * 
     * @return bool
     */
    public static function handleAPI(): bool
    {
        // Para APIs, verifica token de autorização em vez de CSRF
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $token = substr($authHeader, 7);
        
        // Aqui você validaria o token JWT ou API key
        // Por simplicidade, vamos usar uma validação básica
        return !empty($token) && strlen($token) >= 32;
    }

    /**
     * Cria token CSRF específico para formulário
     * 
     * @param string $form Identificador do formulário
     * @return string
     */
    public static function generateFormToken(string $form): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = Security::generateCSRFToken();
        $_SESSION["csrf_form_{$form}"] = $token;
        $_SESSION["csrf_form_{$form}_time"] = time();

        return $token;
    }

    /**
     * Valida token específico de formulário
     * 
     * @param string $token
     * @param string $form
     * @return bool
     */
    public static function validateFormToken(string $token, string $form): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionKey = "csrf_form_{$form}";
        $timeKey = "csrf_form_{$form}_time";

        if (!isset($_SESSION[$sessionKey])) {
            return false;
        }

        // Verifica expiração
        $tokenAge = time() - ($_SESSION[$timeKey] ?? 0);
        if ($tokenAge > 3600) { // 1 hora
            unset($_SESSION[$sessionKey], $_SESSION[$timeKey]);
            return false;
        }

        $isValid = Security::validateCSRFToken($token, $_SESSION[$sessionKey]);

        // Remove token após uso (uso único)
        if ($isValid) {
            unset($_SESSION[$sessionKey], $_SESSION[$timeKey]);
        }

        return $isValid;
    }

    /**
     * Middleware para páginas de upload
     * 
     * @return bool
     */
    public static function handleFileUpload(): bool
    {
        // Verificações adicionais para upload
        if (!self::handle()) {
            return false;
        }

        // Verifica se o content-type é multipart
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_starts_with($contentType, 'multipart/form-data')) {
            return false;
        }

        return true;
    }

    /**
     * Obtém configurações de CSRF para frontend
     * 
     * @return array
     */
    public static function getFrontendConfig(): array
    {
        return [
            'token' => self::getToken() ?: self::generateToken(),
            'header' => self::$headerKey,
            'parameter' => self::$tokenKey,
            'validate_referer' => Config::get('security.validate_referer', true)
        ];
    }

    /**
     * Middleware que bloqueia requisições sem CSRF válido
     */
    public static function enforceCSRF(): void
    {
        if (!self::handle()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'CSRF token inválido ou ausente',
                'code' => 'CSRF_TOKEN_MISMATCH'
            ]);
            exit;
        }
    }

    /**
     * Middleware silencioso (retorna status sem interromper)
     * 
     * @return array
     */
    public static function checkSilent(): array
    {
        $isValid = self::handle();
        
        return [
            'valid' => $isValid,
            'token' => self::getToken(),
            'reason' => $isValid ? null : 'Token CSRF inválido ou ausente'
        ];
    }

    /**
     * Configura headers de segurança relacionados
     */
    public static function setSecurityHeaders(): void
    {
        // Proteção XSS
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // CSP básico
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' cdnjs.cloudflare.com cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; " .
               "font-src 'self' fonts.gstatic.com; " .
               "img-src 'self' data: *.ses.ms.gov.br;";
        
        header("Content-Security-Policy: {$csp}");
        
        // HSTS se for HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}