<?php

namespace App\Helpers;

/**
 * Classe de segurança com funções de proteção contra ataques
 * 
 * @package App\Helpers
 * @author SES-MS
 * @version 2.0.0
 */
class Security
{
    /**
     * Sanitiza input removendo caracteres perigosos
     * 
     * @param string|array $input
     * @return string|array
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        if (!is_string($input)) {
            return $input;
        }

        // Remove caracteres nulos e de controle
        $input = str_replace(chr(0), '', $input);
        
        // Remove tags HTML e PHP
        $input = strip_tags($input);
        
        // Converte caracteres especiais em entidades HTML
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove espaços em branco extras
        $input = trim($input);
        
        return $input;
    }

    /**
     * Sanitiza input preservando HTML seguro (para editores)
     * 
     * @param string $input
     * @return string
     */
    public static function sanitizeHtml(string $input): string
    {
        // Tags permitidas para conteúdo rico
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6>';
        
        // Remove tags perigosas mas mantém as seguras
        $input = strip_tags($input, $allowedTags);
        
        // Remove atributos perigosos dos links
        $input = preg_replace('/(<a[^>]*)(on\w+="[^"]*")/i', '$1', $input);
        $input = preg_replace('/(<a[^>]*)(javascript:[^"]*)/i', '$1#', $input);
        
        return trim($input);
    }

    /**
     * Gera token CSRF seguro
     * 
     * @return string
     */
    public static function generateCSRFToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Valida token CSRF
     * 
     * @param string $token
     * @param string $sessionToken
     * @return bool
     */
    public static function validateCSRFToken(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token);
    }

    /**
     * Valida CPF brasileiro
     * 
     * @param string $cpf
     * @return bool
     */
    public static function validateCPF(string $cpf): bool
    {
        // Remove formatação
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }
        
        // Verifica se não são todos iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        
        // Validação do primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $cpf[$i] * (10 - $i);
        }
        $digit1 = 11 - ($sum % 11);
        if ($digit1 >= 10) $digit1 = 0;
        
        if ($cpf[9] != $digit1) {
            return false;
        }
        
        // Validação do segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $cpf[$i] * (11 - $i);
        }
        $digit2 = 11 - ($sum % 11);
        if ($digit2 >= 10) $digit2 = 0;
        
        return $cpf[10] == $digit2;
    }

    /**
     * Formata CPF para exibição
     * 
     * @param string $cpf
     * @return string
     */
    public static function formatCPF(string $cpf): string
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . 
                   substr($cpf, 3, 3) . '.' . 
                   substr($cpf, 6, 3) . '-' . 
                   substr($cpf, 9, 2);
        }
        
        return $cpf;
    }

    /**
     * Rate limiting - controla tentativas por IP/identificador
     * 
     * @param string $identifier
     * @param int $maxAttempts
     * @param int $timeWindow Em segundos
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function rateLimit(string $identifier, int $maxAttempts = 3, int $timeWindow = 600): array
    {
        $cacheKey = 'rate_limit_' . hash('sha256', $identifier);
        $cacheFile = __DIR__ . '/../../storage/cache/' . $cacheKey;
        
        // Cria diretório de cache se não existir
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $now = time();
        $attempts = [];
        
        // Carrega tentativas existentes
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['attempts'])) {
                $attempts = $data['attempts'];
            }
        }
        
        // Remove tentativas antigas (fora da janela de tempo)
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) <= $timeWindow;
        });
        
        // Verifica se excedeu o limite
        $currentAttempts = count($attempts);
        $allowed = $currentAttempts < $maxAttempts;
        $remaining = max(0, $maxAttempts - $currentAttempts - 1);
        
        // Calcula quando o bloqueio será removido
        $resetTime = $attempts ? min($attempts) + $timeWindow : $now;
        
        // Se permitido, registra a nova tentativa
        if ($allowed) {
            $attempts[] = $now;
            file_put_contents($cacheFile, json_encode([
                'attempts' => $attempts,
                'updated_at' => $now
            ]), LOCK_EX);
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'current_attempts' => $currentAttempts
        ];
    }

    /**
     * Gera senha segura aleatória
     * 
     * @param int $length
     * @return string
     */
    public static function generateSecurePassword(int $length = 12): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*';
        
        // Garante pelo menos um de cada tipo
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Completa o restante aleatoriamente
        $allChars = $lowercase . $uppercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Embaralha os caracteres
        return str_shuffle($password);
    }

    /**
     * Hash seguro de senha
     * 
     * @param string $password
     * @return string
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Verifica senha contra hash
     * 
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Criptografa dados sensíveis
     * 
     * @param string $data
     * @param string $key
     * @return string
     */
    public static function encrypt(string $data, string $key = null): string
    {
        $key = $key ?: $_ENV['CSRF_SECRET'] ?? 'default_key_change_this';
        $key = hash('sha256', $key, true);
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa dados
     * 
     * @param string $encryptedData
     * @param string $key
     * @return string|false
     */
    public static function decrypt(string $encryptedData, string $key = null)
    {
        $key = $key ?: $_ENV['CSRF_SECRET'] ?? 'default_key_change_this';
        $key = hash('sha256', $key, true);
        
        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < 16) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Valida força da senha
     * 
     * @param string $password
     * @return array
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        $score = 0;
        
        // Comprimento mínimo
        if (strlen($password) < 8) {
            $errors[] = 'Senha deve ter pelo menos 8 caracteres';
        } else {
            $score += 1;
        }
        
        // Letras minúsculas
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra minúscula';
        } else {
            $score += 1;
        }
        
        // Letras maiúsculas
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra maiúscula';
        } else {
            $score += 1;
        }
        
        // Números
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos um número';
        } else {
            $score += 1;
        }
        
        // Caracteres especiais
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos um caractere especial';
        } else {
            $score += 1;
        }
        
        // Avalia força
        $strength = 'Muito Fraca';
        if ($score >= 5) $strength = 'Muito Forte';
        elseif ($score >= 4) $strength = 'Forte';
        elseif ($score >= 3) $strength = 'Média';
        elseif ($score >= 2) $strength = 'Fraca';
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $score,
            'strength' => $strength
        ];
    }

    /**
     * Limpa dados de cache expirados
     */
    public static function clearExpiredCache(): void
    {
        $cacheDir = __DIR__ . '/../../storage/cache';
        if (!is_dir($cacheDir)) {
            return;
        }
        
        $files = glob($cacheDir . '/rate_limit_*');
        $now = time();
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['updated_at'])) {
                // Remove arquivos com mais de 1 hora
                if (($now - $data['updated_at']) > 3600) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Obtém IP real do cliente (considerando proxies)
     * 
     * @return string
     */
    public static function getClientIP(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Se há múltiplos IPs, pega o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Valida se é um IP válido
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Obtém User Agent sanitizado
     * 
     * @return string
     */
    public static function getUserAgent(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        return self::sanitizeInput(substr($userAgent, 0, 500));
    }

    /**
     * Gera hash seguro para identificadores
     * 
     * @param string $data
     * @return string
     */
    public static function generateSecureHash(string $data): string
    {
        $salt = bin2hex(random_bytes(16));
        return hash('sha256', $salt . $data . $salt);
    }

    /**
     * Valida se referer é da própria aplicação (proteção CSRF adicional)
     * 
     * @return bool
     */
    public static function validateReferer(): bool
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }
        
        $referer = $_SERVER['HTTP_REFERER'];
        $appUrl = $_ENV['APP_URL'] ?? 'https://apsdigital.ses.ms.gov.br';
        
        return strpos($referer, $appUrl) === 0;
    }
}