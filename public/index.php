<?php
/**
 * APS Digital - Application Entry Point
 * Secretaria de Estado de Saúde de Mato Grosso do Sul (SES-MS)
 * 
 * Este é o ponto de entrada principal da aplicação APS Digital.
 * Todas as requisições HTTP são redirecionadas para este arquivo.
 * 
 * @version 2.0.0
 * @author SES-MS Development Team
 * @since 2024
 */

// Definir constantes do sistema
define('APP_START_TIME', microtime(true));
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DS . 'app');
define('PUBLIC_PATH', ROOT_PATH . DS . 'public');
define('STORAGE_PATH', ROOT_PATH . DS . 'storage');
define('UPLOADS_PATH', STORAGE_PATH . DS . 'uploads');
define('LOGS_PATH', STORAGE_PATH . DS . 'logs');
define('CACHE_PATH', STORAGE_PATH . DS . 'cache');

// Configurar timezone
date_default_timezone_set('America/Campo_Grande');

// Configurar encoding
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Configurar relatórios de erro baseado no ambiente
if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . DS . 'error.log');
}

// Carregar Composer
if (!file_exists(ROOT_PATH . DS . 'vendor' . DS . 'autoload.php')) {
    die('<h1>Erro Fatal</h1><p>Dependências não encontradas. Execute: <code>composer install</code></p>');
}

require_once ROOT_PATH . DS . 'vendor' . DS . 'autoload.php';

// Carregar variáveis de ambiente
$envFile = ROOT_PATH . DS . '.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

// Verificar se as constantes necessárias estão definidas
$requiredEnvVars = [
    'APP_NAME', 'APP_URL', 'APP_ENV',
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'SESSION_NAME', 'CSRF_TOKEN_NAME'
];

foreach ($requiredEnvVars as $var) {
    if (empty($_ENV[$var])) {
        die("<h1>Erro de Configuração</h1><p>Variável de ambiente <strong>{$var}</strong> não definida.</p>");
    }
}

try {
    // Autoloader personalizado para classes da aplicação
    spl_autoload_register(function ($class) {
        // Converter namespace em caminho de arquivo
        $classPath = str_replace(['\\', 'App/'], [DS, ''], $class);
        $file = APP_PATH . DS . $classPath . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        
        return false;
    });

    // Inicializar configurações
    App\Config\Config::init();
    
    // Inicializar conexão com banco de dados
    App\Config\Database::init();
    
    // Configurar sessão segura
    initializeSecureSession();
    
    // Inicializar sistema de roteamento
    $router = new App\Config\Routes();
    
    // Processar requisição
    processRequest($router);
    
} catch (Exception $e) {
    handleFatalError($e);
} catch (Error $e) {
    handleFatalError($e);
}

/**
 * Inicializar sessão com configurações de segurança
 */
function initializeSecureSession()
{
    // Configurações de segurança da sessão
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isHttps() ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_lifetime', 0); // Session cookie
    ini_set('session.gc_maxlifetime', 7200); // 2 horas
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    // Nome da sessão customizado
    session_name($_ENV['SESSION_NAME'] ?? 'APS_DIGITAL_SESSION');
    
    // Iniciar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerar ID da sessão periodicamente
    if (!isset($_SESSION['session_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated'] = time();
    } elseif ($_SESSION['session_regenerated'] < (time() - 300)) { // 5 minutos
        session_regenerate_id(true);
        $_SESSION['session_regenerated'] = time();
    }
    
    // Validação adicional de segurança da sessão
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== getUserAgent()) {
        session_destroy();
        session_start();
    }
    
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = getUserAgent();
    }
    
    // Timeout de inatividade
    if (isset($_SESSION['last_activity']) && 
        $_SESSION['last_activity'] < (time() - ini_get('session.gc_maxlifetime'))) {
        session_destroy();
        session_start();
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Processar requisição HTTP
 */
function processRequest($router)
{
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    
    // Remover query string da URI
    $requestUri = strtok($requestUri, '?');
    
    // Normalizar URI (remover trailing slash, exceto para root)
    if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
        $requestUri = rtrim($requestUri, '/');
    }
    
    // Log da requisição
    logRequest($requestMethod, $requestUri);
    
    // Aplicar middlewares globais
    applyGlobalMiddlewares($requestMethod, $requestUri);
    
    try {
        // Processar rota
        $router->dispatch($requestMethod, $requestUri);
    } catch (Exception $e) {
        handleRouteException($e, $requestMethod, $requestUri);
    }
}

/**
 * Aplicar middlewares globais
 */
function applyGlobalMiddlewares($method, $uri)
{
    // Rate Limiting
    if (!checkRateLimit()) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Muitas requisições. Tente novamente em alguns minutos.']);
        exit;
    }
    
    // Security Headers
    setSecurityHeaders();
    
    // CSRF Protection para requisições POST/PUT/DELETE
    if (in_array($method, ['POST', 'PUT', 'DELETE']) && !isApiRequest($uri)) {
        $csrf = new App\Middleware\CSRFMiddleware();
        if (!$csrf->validate()) {
            http_response_code(403);
            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Token CSRF inválido']);
            } else {
                redirectTo('/login?error=csrf');
            }
            exit;
        }
    }
    
    // Content Security Policy
    if (!isApiRequest($uri)) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'");
    }
}

/**
 * Configurar cabeçalhos de segurança
 */
function setSecurityHeaders()
{
    // Prevenir ataques XSS
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevenir MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Controlar frames/iframes
    header('X-Frame-Options: SAMEORIGIN');
    
    // HTTPS Strict Transport Security (apenas em produção)
    if ($_ENV['APP_ENV'] === 'production' && isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Feature Policy / Permissions Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * Rate Limiting simples
 */
function checkRateLimit()
{
    $clientIp = getClientIpAddress();
    $cacheKey = 'rate_limit_' . md5($clientIp);
    $cacheFile = CACHE_PATH . DS . $cacheKey . '.json';
    
    $maxRequests = 60; // Máximo de requisições
    $timeWindow = 60;  // Em 60 segundos
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if ($data && $data['timestamp'] > (time() - $timeWindow)) {
            if ($data['count'] >= $maxRequests) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'timestamp' => time()];
        }
    } else {
        $data = ['count' => 1, 'timestamp' => time()];
    }
    
    // Criar diretório de cache se não existir
    if (!is_dir(CACHE_PATH)) {
        mkdir(CACHE_PATH, 0755, true);
    }
    
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    
    return true;
}

/**
 * Tratar exceções de rota
 */
function handleRouteException($exception, $method, $uri)
{
    $statusCode = 500;
    $message = 'Erro interno do servidor';
    
    if ($exception instanceof App\Exceptions\NotFoundException) {
        $statusCode = 404;
        $message = 'Página não encontrada';
    } elseif ($exception instanceof App\Exceptions\UnauthorizedException) {
        $statusCode = 401;
        $message = 'Acesso negado';
    } elseif ($exception instanceof App\Exceptions\ForbiddenException) {
        $statusCode = 403;
        $message = 'Acesso proibido';
    }
    
    http_response_code($statusCode);
    
    // Log do erro
    error_log(sprintf(
        "[%s] %s %s - %s: %s",
        date('Y-m-d H:i:s'),
        $method,
        $uri,
        get_class($exception),
        $exception->getMessage()
    ));
    
    if (isAjaxRequest() || isApiRequest($uri)) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $message,
            'code' => $statusCode
        ]);
    } else {
        // Carregar página de erro personalizada
        $errorView = APP_PATH . DS . 'Views' . DS . 'errors' . DS . $statusCode . '.php';
        if (file_exists($errorView)) {
            include $errorView;
        } else {
            echo "<h1>Erro {$statusCode}</h1><p>{$message}</p>";
        }
    }
}

/**
 * Tratar erros fatais
 */
function handleFatalError($error)
{
    $message = 'Erro interno do servidor';
    $details = '';
    
    if ($_ENV['APP_DEBUG'] === 'true') {
        $message = $error->getMessage();
        $details = $error->getTraceAsString();
    }
    
    // Log do erro
    error_log(sprintf(
        "[%s] FATAL ERROR: %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        $error->getTraceAsString()
    ));
    
    http_response_code(500);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    } else {
        $errorTemplate = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro 500 - APS Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erro do Servidor
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">{$message}</p>
                        {$details}
                        <div class="mt-3">
                            <a href="/" class="btn btn-primary">Voltar ao Início</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
        echo $details ? str_replace('{$details}', "<pre class=\"bg-light p-3 mt-3\">{$details}</pre>", $errorTemplate) : str_replace('{$details}', '', $errorTemplate);
    }
    
    exit;
}

/**
 * Registrar requisição no log
 */
function logRequest($method, $uri)
{
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] %s %s - IP: %s - User-Agent: %s",
        date('Y-m-d H:i:s'),
        $method,
        $uri,
        getClientIpAddress(),
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    );
    
    file_put_contents(
        LOGS_PATH . DS . 'access.log',
        $logEntry . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Utilitários
 */
function isHttps()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || $_SERVER['SERVER_PORT'] == 443
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
}

function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function isApiRequest($uri)
{
    return strpos($uri, '/api/') === 0;
}

function getUserAgent()
{
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '' . getClientIpAddress());
}

function getClientIpAddress()
{
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function redirectTo($url)
{
    header("Location: {$url}");
    exit;
}

// Registrar shutdown function para cleanup
register_shutdown_function(function() {
    $executionTime = microtime(true) - APP_START_TIME;
    
    // Log tempo de execução se for lento
    if ($executionTime > 2.0) { // Mais de 2 segundos
        error_log(sprintf(
            "[%s] SLOW QUERY: %s %s - Execution time: %.4f seconds",
            date('Y-m-d H:i:s'),
            $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            $_SERVER['REQUEST_URI'] ?? '/',
            $executionTime
        ));
    }
});