<?php

namespace App\Config;

/**
 * Sistema de roteamento da aplicação
 * 
 * @package App\Config
 * @author SES-MS
 * @version 2.0.0
 */
class Routes
{
    private static array $routes = [];
    private static array $middlewares = [];
    private static string $basePath = '';

    /**
     * Define as rotas da aplicação
     */
    public static function define(): void
    {
        // === ROTAS DE AUTENTICAÇÃO ===
        self::get('/', 'AuthController@loginForm');
        self::get('/login', 'AuthController@loginForm');
        self::post('/login', 'AuthController@login');
        self::get('/logout', 'AuthController@logout');
        self::get('/reset-password', 'AuthController@resetPasswordForm');
        self::post('/reset-password', 'AuthController@resetPassword');

        // === ROTAS PROTEGIDAS (requerem autenticação) ===
        self::group(['middleware' => 'auth'], function() {
            // Dashboard e seleção de perfil
            self::get('/dashboard', 'DashboardController@index');
            self::get('/profile-selection', 'DashboardController@profileSelection');
            self::post('/select-profile', 'DashboardController@selectProfile');

            // === GESTÃO DE USUÁRIOS ===
            self::group(['prefix' => 'users', 'middleware' => 'permission:1'], function() {
                self::get('/', 'UserController@index');
                self::get('/create', 'UserController@create');
                self::post('/store', 'UserController@store');
                self::get('/{id}', 'UserController@show');
                self::get('/{id}/edit', 'UserController@edit');
                self::put('/{id}', 'UserController@update');
                self::delete('/{id}', 'UserController@delete');
                self::post('/{id}/authorize', 'UserController@authorize');
                self::post('/{id}/photo', 'UserController@uploadPhoto');
            });

            // === GESTÃO DE EQUIPAMENTOS ===
            self::group(['prefix' => 'equipment', 'middleware' => 'permission:3'], function() {
                self::get('/', 'EquipmentController@index');
                self::get('/tablets', 'EquipmentController@tablets');
                self::get('/chips', 'EquipmentController@chips');
                self::get('/create', 'EquipmentController@create');
                self::post('/store', 'EquipmentController@store');
                self::get('/{id}/edit', 'EquipmentController@edit');
                self::put('/{id}', 'EquipmentController@update');
                self::delete('/{id}', 'EquipmentController@delete');
                self::post('/{id}/authorize', 'EquipmentController@authorize');
                self::get('/export', 'EquipmentController@export');
            });

            // === RELATÓRIOS ===
            self::group(['prefix' => 'reports'], function() {
                self::get('/', 'ReportController@index');
                
                // Relatórios Saúde da Mulher (permissão 5)
                self::get('/health', 'ReportController@health', ['middleware' => 'permission:5']);
                self::post('/health/export', 'ReportController@healthExport', ['middleware' => 'permission:5']);
                
                // Relatórios E-Agentes (permissão 6)
                self::get('/eagents', 'ReportController@eAgents', ['middleware' => 'permission:6']);
                self::post('/eagents/export', 'ReportController@eAgentsExport', ['middleware' => 'permission:6']);
                
                // Relatórios de Equipamentos (permissão 3)
                self::get('/equipment', 'ReportController@equipmentReport', ['middleware' => 'permission:3']);
                self::post('/equipment/export', 'ReportController@equipmentExport', ['middleware' => 'permission:3']);
            });

            // === FORMULÁRIOS ===
            self::group(['prefix' => 'forms'], function() {
                // Formulário Saúde da Mulher (permissão 7)
                self::get('/health', 'HealthFormController@index', ['middleware' => 'permission:7']);
                self::post('/health', 'HealthFormController@store', ['middleware' => 'permission:7']);
                self::get('/health/{id}', 'HealthFormController@show', ['middleware' => 'permission:7']);
                self::put('/health/{id}', 'HealthFormController@update', ['middleware' => 'permission:7']);
                self::delete('/health/{id}', 'HealthFormController@delete', ['middleware' => 'permission:7']);
            });

            // === MAPAS REGIONAIS ===
            self::group(['prefix' => 'mapas'], function() {
                self::get('/', 'MapaController@index');
                self::get('/regiao/{regiao}', 'MapaController@regiao');
                self::get('/visualizar', 'MapaController@visualizar');
                self::get('/visualizar/{regiao}', 'MapaController@visualizar');
            });

            // === INDICADORES DE SAÚDE ===
            self::group(['prefix' => 'indicadores'], function() {
                self::get('/', 'IndicadorController@index');
                self::get('/tutorial', 'IndicadorController@tutorial');
                self::get('/dashboard', 'IndicadorController@dashboard');
                self::get('/regiao/{regiao}', 'IndicadorController@porRegiao');
                self::get('/comparar', 'IndicadorController@comparar');
                self::get('/exportar', 'IndicadorController@exportar');
            });

            // === ADMINISTRAÇÃO (apenas admin) ===
            self::group(['prefix' => 'admin', 'middleware' => 'permission:10'], function() {
                self::get('/municipalities', 'AdminController@municipalities');
                self::post('/municipalities', 'AdminController@storeMunicipality');
                self::put('/municipalities/{id}', 'AdminController@updateMunicipality');
                
                self::get('/permissions', 'AdminController@permissions');
                self::post('/permissions', 'AdminController@updatePermissions');
                
                self::get('/audit', 'AdminController@auditLogs');
                self::get('/system-info', 'AdminController@systemInfo');
            });

            // === PERFIL DO USUÁRIO ===
            self::get('/profile', 'UserController@profile');
            self::put('/profile', 'UserController@updateProfile');
            self::post('/profile/password', 'UserController@changePassword');
        });

        // === API ROUTES ===
        self::group(['prefix' => 'api'], function() {
            // API pública (sem autenticação)
            self::get('/municipalities', 'ApiController@municipalities');
            self::get('/health-check', 'ApiController@healthCheck');
            
            // API Mapas (pública)
            self::get('/mapas/regioes', 'MapaController@getRegioes');
            self::get('/mapas/svg/{regiao}', 'MapaController@getSvg');
            self::get('/mapas/geojson/{regiao}', 'MapaController@getGeoJson');
            
            // API Indicadores (pública) 
            self::get('/indicadores/regioes', 'IndicadorController@getRegioes');
            self::get('/indicadores/dados/{regiao}', 'IndicadorController@getDadosPorRegiao');
            self::get('/indicadores/estatisticas', 'IndicadorController@getEstatisticas');

            // API protegida
            self::group(['middleware' => 'auth'], function() {
                self::get('/user/profile', 'ApiController@userProfile');
                self::get('/equipment/available', 'ApiController@availableEquipment');
                self::post('/upload', 'ApiController@upload');
                
                // API Indicadores protegida
                self::post('/indicadores/dados/{regiao}', 'IndicadorController@storeDados');
                self::put('/indicadores/dados/{id}', 'IndicadorController@updateDados');
                self::delete('/indicadores/dados/{id}', 'IndicadorController@deleteDados');
            });
        });

        // === ARQUIVOS ESTÁTICOS ===
        self::get('/uploads/{type}/{file}', 'FileController@serve');
    }

    /**
     * Adiciona rota GET
     */
    public static function get(string $uri, $handler, array $options = []): void
    {
        self::addRoute('GET', $uri, $handler, $options);
    }

    /**
     * Adiciona rota POST
     */
    public static function post(string $uri, $handler, array $options = []): void
    {
        self::addRoute('POST', $uri, $handler, $options);
    }

    /**
     * Adiciona rota PUT
     */
    public static function put(string $uri, $handler, array $options = []): void
    {
        self::addRoute('PUT', $uri, $handler, $options);
    }

    /**
     * Adiciona rota DELETE
     */
    public static function delete(string $uri, $handler, array $options = []): void
    {
        self::addRoute('DELETE', $uri, $handler, $options);
    }

    /**
     * Agrupa rotas com middleware comum
     */
    public static function group(array $options, callable $callback): void
    {
        $previousMiddlewares = self::$middlewares;
        $previousBasePath = self::$basePath;

        // Adiciona middleware do grupo
        if (isset($options['middleware'])) {
            self::$middlewares = array_merge(self::$middlewares, (array)$options['middleware']);
        }

        // Adiciona prefixo do grupo
        if (isset($options['prefix'])) {
            self::$basePath .= '/' . trim($options['prefix'], '/');
        }

        // Executa callback com contexto do grupo
        $callback();

        // Restaura contexto anterior
        self::$middlewares = $previousMiddlewares;
        self::$basePath = $previousBasePath;
    }

    /**
     * Adiciona rota ao sistema
     */
    private static function addRoute(string $method, string $uri, $handler, array $options = []): void
    {
        // Aplica prefixo do grupo
        $fullUri = self::$basePath . $uri;
        
        // Remove barras duplicadas
        $fullUri = preg_replace('#/+#', '/', $fullUri);
        
        // Garante que inicie com /
        if (!str_starts_with($fullUri, '/')) {
            $fullUri = '/' . $fullUri;
        }

        // Combina middlewares do grupo com middlewares específicos da rota
        $middlewares = array_merge(self::$middlewares, $options['middleware'] ?? []);

        self::$routes[] = [
            'method' => $method,
            'uri' => $fullUri,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Resolve rota atual
     */
    public static function resolve(string $method, string $uri): ?array
    {
        // Remove query string
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Remove trailing slash (exceto para root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        foreach (self::$routes as $route) {
            if ($route['method'] === $method && self::matchUri($route['uri'], $uri)) {
                $route['params'] = self::extractParams($route['uri'], $uri);
                return $route;
            }
        }

        return null;
    }

    /**
     * Verifica se URI da rota corresponde à URI solicitada
     */
    private static function matchUri(string $routeUri, string $requestUri): bool
    {
        // Converte parâmetros {id} para regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        return preg_match($pattern, $requestUri);
    }

    /**
     * Extrai parâmetros da URI
     */
    private static function extractParams(string $routeUri, string $requestUri): array
    {
        $params = [];
        
        // Encontra nomes dos parâmetros
        preg_match_all('/\{([^}]+)\}/', $routeUri, $paramNames);
        
        // Encontra valores dos parâmetros
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $requestUri, $matches)) {
            array_shift($matches); // Remove match completo
            
            foreach ($paramNames[1] as $index => $paramName) {
                if (isset($matches[$index])) {
                    $params[$paramName] = $matches[$index];
                }
            }
        }

        return $params;
    }

    /**
     * Gera URL para rota nomeada
     */
    public static function url(string $name, array $params = []): string
    {
        // Esta seria uma implementação mais complexa
        // Por simplicidade, vamos usar URLs diretas
        $baseUrl = Config::get('app.url');
        
        // Remove parâmetros e monta URL
        $url = $baseUrl . $name;
        
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }
        
        return $url;
    }

    /**
     * Redireciona para rota
     */
    public static function redirect(string $uri, int $code = 302): void
    {
        if (!str_starts_with($uri, 'http')) {
            $uri = Config::get('app.url') . '/' . ltrim($uri, '/');
        }

        header("Location: $uri", true, $code);
        exit;
    }

    /**
     * Obtém todas as rotas
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Limpa rotas (útil para testes)
     */
    public static function clear(): void
    {
        self::$routes = [];
        self::$middlewares = [];
        self::$basePath = '';
    }
}