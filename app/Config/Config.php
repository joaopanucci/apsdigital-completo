<?php

namespace App\Config;

/**
 * Classe de configuração geral da aplicação
 * 
 * @package App\Config
 * @author SES-MS
 * @version 2.0.0
 */
class Config
{
    /**
     * Configurações da aplicação
     */
    private static array $config = [];

    /**
     * Inicializa configurações
     */
    public static function init(): void
    {
        self::$config = [
            // Aplicação
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'APS Digital',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'https://apsdigital.ses.ms.gov.br',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Campo_Grande'
            ],

            // Segurança
            'security' => [
                'csrf_secret' => $_ENV['CSRF_SECRET'] ?? 'default_csrf_secret_change_this',
                'session_lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
                'max_login_attempts' => (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 3),
                'rate_limit_window' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 600),
                'password_reset_expiry' => (int)($_ENV['PASSWORD_RESET_EXPIRY'] ?? 3600)
            ],

            // Email
            'mail' => [
                'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'APS Digital - SES/MS',
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls'
            ],

            // Upload
            'upload' => [
                'max_file_size' => (int)($_ENV['MAX_FILE_SIZE'] ?? 10485760), // 10MB
                'allowed_extensions' => explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'pdf,xlsx,xls,jpg,png,jpeg'),
                'path' => $_ENV['UPLOAD_PATH'] ?? __DIR__ . '/../../public/uploads',
                'temp_path' => $_ENV['UPLOAD_PATH'] ?? __DIR__ . '/../../storage/temp'
            ],

            // Logs
            'logging' => [
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                'path' => $_ENV['LOG_PATH'] ?? __DIR__ . '/../../storage/logs',
                'max_files' => 30,
                'daily_rotation' => true
            ],

            // Cache
            'cache' => [
                'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
                'path' => $_ENV['CACHE_PATH'] ?? __DIR__ . '/../../storage/cache',
                'ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600)
            ],

            // SES-MS Específico
            'ses' => [
                'logo_url' => $_ENV['SES_LOGO_URL'] ?? '/assets/img/ses-ms-logo.png',
                'contact_email' => $_ENV['SES_CONTACT_EMAIL'] ?? 'suporte@ses.ms.gov.br',
                'contact_phone' => $_ENV['SES_CONTACT_PHONE'] ?? '(67) 3318-1000',
                'address' => 'Av. do Poeta, 181 - Bloco III - Cidade Jardim, Campo Grande - MS, 79080-012',
                'cnpj' => '03.476.409/0001-04'
            ],

            // Perfis do sistema
            'profiles' => [
                1 => 'Administrador SES',
                2 => 'Gestor Regional',
                3 => 'Gestor Municipal',
                4 => 'Técnico Municipal',
                5 => 'Auditor'
            ],

            // Funcionalidades do sistema
            'functionalities' => [
                1 => 'Gestão de Usuários',
                2 => 'Autorização de Usuários',
                3 => 'Gestão de Equipamentos',
                4 => 'Autorização de Equipamentos',
                5 => 'Relatórios Saúde da Mulher',
                6 => 'Relatórios E-Agentes',
                7 => 'Formulário Saúde da Mulher',
                8 => 'Cadastro de Municípios',
                9 => 'Auditoria do Sistema',
                10 => 'Configurações Globais'
            ],

            // Validação
            'validation' => [
                'cpf_required_length' => 11,
                'password_min_length' => 8,
                'name_min_length' => 3,
                'email_max_length' => 255
            ]
        ];

        // Define timezone
        date_default_timezone_set(self::$config['app']['timezone']);
    }

    /**
     * Obtém valor de configuração
     * 
     * @param string $key Chave no formato 'section.key' ou 'section'
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            self::init();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Define valor de configuração
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        if (empty(self::$config)) {
            self::init();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Verifica se chave existe
     * 
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Obtém todas as configurações
     * 
     * @return array
     */
    public static function all(): array
    {
        if (empty(self::$config)) {
            self::init();
        }

        return self::$config;
    }

    /**
     * Verifica se aplicação está em modo debug
     * 
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::get('app.debug', false);
    }

    /**
     * Verifica ambiente da aplicação
     * 
     * @param string $env
     * @return bool
     */
    public static function isEnvironment(string $env): bool
    {
        return self::get('app.env') === $env;
    }

    /**
     * Obtém URL base da aplicação
     * 
     * @param string $path
     * @return string
     */
    public static function url(string $path = ''): string
    {
        $baseUrl = rtrim(self::get('app.url'), '/');
        $path = ltrim($path, '/');
        
        return $path ? $baseUrl . '/' . $path : $baseUrl;
    }

    /**
     * Obtém caminho absoluto para arquivo
     * 
     * @param string $path
     * @return string
     */
    public static function path(string $path = ''): string
    {
        $basePath = realpath(__DIR__ . '/../../');
        $path = ltrim($path, '/\\');
        
        return $path ? $basePath . DIRECTORY_SEPARATOR . $path : $basePath;
    }

    /**
     * Obtém configurações de upload
     * 
     * @return array
     */
    public static function getUploadConfig(): array
    {
        return self::get('upload');
    }

    /**
     * Obtém configurações de email
     * 
     * @return array
     */
    public static function getMailConfig(): array
    {
        return self::get('mail');
    }

    /**
     * Obtém configurações de segurança
     * 
     * @return array
     */
    public static function getSecurityConfig(): array
    {
        return self::get('security');
    }

    /**
     * Obtém lista de perfis
     * 
     * @return array
     */
    public static function getProfiles(): array
    {
        return self::get('profiles', []);
    }

    /**
     * Obtém nome do perfil por ID
     * 
     * @param int $profileId
     * @return string|null
     */
    public static function getProfileName(int $profileId): ?string
    {
        $profiles = self::getProfiles();
        return $profiles[$profileId] ?? null;
    }

    /**
     * Obtém lista de funcionalidades
     * 
     * @return array
     */
    public static function getFunctionalities(): array
    {
        return self::get('functionalities', []);
    }

    /**
     * Obtém nome da funcionalidade por ID
     * 
     * @param int $functionalityId
     * @return string|null
     */
    public static function getFunctionalityName(int $functionalityId): ?string
    {
        $functionalities = self::getFunctionalities();
        return $functionalities[$functionalityId] ?? null;
    }
}