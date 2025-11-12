<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\ReportService;
use App\Services\AuditService;
use App\Models\User;
use App\Models\Equipment;
use App\Models\HealthForm;
use App\Helpers\Security;
use Exception;

/**
 * Controlador do Dashboard
 * Página principal com resumos e estatísticas
 */
class DashboardController
{
    private $authService;
    private $reportService;
    private $auditService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reportService = new ReportService();
        $this->auditService = new AuditService();
    }

    /**
     * Página principal do dashboard
     */
    public function index(): void
    {
        try {
            // Verificar autenticação
            if (!$this->authService->isValidSession()) {
                $this->redirect('/login');
                return;
            }

            // Verificar se precisa selecionar perfil
            if (isset($_SESSION['user']['needs_profile_selection'])) {
                $this->redirect('/auth/profile-selection');
                return;
            }

            $user = $_SESSION['user'];
            $currentProfile = $user['current_profile'];

            // Obter dados do dashboard baseado no perfil
            $dashboardData = $this->getDashboardData($currentProfile);

            $data = [
                'title' => 'Dashboard - APS Digital',
                'user' => $user,
                'profile' => $currentProfile,
                'dashboard' => $dashboardData,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('dashboard/index', $data);

        } catch (Exception $e) {
            error_log("Erro no dashboard: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Obtém dados do dashboard baseado no perfil
     */
    private function getDashboardData(array $profile): array
    {
        $profileName = $profile['profile_name'];
        $municipalityId = $profile['municipality_id'] ?? null;

        switch ($profileName) {
            case 'Nacional':
                return $this->getNationalDashboard();
            
            case 'Regional':
                return $this->getRegionalDashboard();
            
            case 'Municipal':
                return $this->getMunicipalDashboard($municipalityId);
            
            case 'Unidade':
                return $this->getUnitDashboard($municipalityId);
            
            default:
                return $this->getBasicDashboard();
        }
    }

    /**
     * Dashboard para perfil Nacional
     */
    private function getNationalDashboard(): array
    {
        $executiveDashboard = $this->reportService->generateExecutiveDashboard();
        
        return [
            'type' => 'nacional',
            'kpis' => $executiveDashboard['dashboard']['kpis'] ?? [],
            'forms_growth' => $executiveDashboard['dashboard']['forms_growth'] ?? [],
            'top_municipalities' => $executiveDashboard['dashboard']['top_municipalities'] ?? [],
            'alerts' => $executiveDashboard['dashboard']['alerts'] ?? [],
            'permissions' => $this->getAvailableModules('Nacional')
        ];
    }

    /**
     * Dashboard para perfil Regional
     */
    private function getRegionalDashboard(): array
    {
        // Similar ao nacional, mas com filtros regionais
        $executiveDashboard = $this->reportService->generateExecutiveDashboard();
        
        return [
            'type' => 'regional',
            'kpis' => $executiveDashboard['dashboard']['kpis'] ?? [],
            'forms_growth' => $executiveDashboard['dashboard']['forms_growth'] ?? [],
            'top_municipalities' => array_slice($executiveDashboard['dashboard']['top_municipalities'] ?? [], 0, 10),
            'alerts' => $executiveDashboard['dashboard']['alerts'] ?? [],
            'permissions' => $this->getAvailableModules('Regional')
        ];
    }

    /**
     * Dashboard para perfil Municipal
     */
    private function getMunicipalDashboard(?int $municipalityId): array
    {
        if (!$municipalityId) {
            return $this->getBasicDashboard();
        }

        // Estatísticas específicas do município
        $healthStats = $this->getHealthFormsStats($municipalityId);
        $equipmentStats = $this->getEquipmentStats($municipalityId);
        $userStats = $this->getUserStats($municipalityId);

        return [
            'type' => 'municipal',
            'municipality_id' => $municipalityId,
            'health_forms' => $healthStats,
            'equipment' => $equipmentStats,
            'users' => $userStats,
            'recent_activities' => $this->getRecentActivities($municipalityId),
            'permissions' => $this->getAvailableModules('Municipal')
        ];
    }

    /**
     * Dashboard para perfil Unidade
     */
    private function getUnitDashboard(?int $municipalityId): array
    {
        if (!$municipalityId) {
            return $this->getBasicDashboard();
        }

        // Estatísticas limitadas da unidade
        $healthStats = $this->getHealthFormsStats($municipalityId);
        $equipmentStats = $this->getEquipmentStats($municipalityId, true); // Apenas visualização

        return [
            'type' => 'unidade',
            'municipality_id' => $municipalityId,
            'health_forms' => $healthStats,
            'equipment' => $equipmentStats,
            'permissions' => $this->getAvailableModules('Unidade')
        ];
    }

    /**
     * Dashboard básico
     */
    private function getBasicDashboard(): array
    {
        return [
            'type' => 'basic',
            'message' => 'Dashboard em construção para este perfil',
            'permissions' => []
        ];
    }

    /**
     * Estatísticas de fichas de saúde
     */
    private function getHealthFormsStats(?int $municipalityId): array
    {
        try {
            $filters = [];
            if ($municipalityId) {
                $filters['municipality_id'] = $municipalityId;
            }

            $healthFormModel = new HealthForm();
            
            // Estatísticas dos últimos 30 dias
            $filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
            $last30Days = $healthFormModel->getStatistics($filters);

            // Estatísticas dos últimos 7 dias
            $filters['date_from'] = date('Y-m-d', strtotime('-7 days'));
            $last7Days = $healthFormModel->getStatistics($filters);

            // Total geral
            unset($filters['date_from']);
            $total = $healthFormModel->getStatistics($filters);

            return [
                'total' => $total['total_forms'] ?? 0,
                'last_30_days' => $last30Days['total_forms'] ?? 0,
                'last_7_days' => $last7Days['total_forms'] ?? 0,
                'avg_age' => $total['avg_age'] ?? 0,
                'gender_distribution' => [
                    'male' => $total['male_count'] ?? 0,
                    'female' => $total['female_count'] ?? 0
                ]
            ];

        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas de fichas: " . $e->getMessage());
            return ['error' => true];
        }
    }

    /**
     * Estatísticas de equipamentos
     */
    private function getEquipmentStats(?int $municipalityId, bool $readOnly = false): array
    {
        try {
            $equipmentModel = new Equipment();
            $filters = [];
            
            if ($municipalityId) {
                $filters['municipality_id'] = $municipalityId;
            }

            $stats = $equipmentModel->getStatistics($filters);

            return [
                'total' => $stats['total_equipment'] ?? 0,
                'available' => $stats['available_count'] ?? 0,
                'delivered' => $stats['delivered_count'] ?? 0,
                'in_use' => $stats['in_use_count'] ?? 0,
                'maintenance' => $stats['maintenance_count'] ?? 0,
                'damaged' => $stats['damaged_count'] ?? 0,
                'read_only' => $readOnly
            ];

        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas de equipamentos: " . $e->getMessage());
            return ['error' => true];
        }
    }

    /**
     * Estatísticas de usuários
     */
    private function getUserStats(?int $municipalityId): array
    {
        try {
            $userModel = new User();
            $filters = [];
            
            if ($municipalityId) {
                $filters['municipality_id'] = $municipalityId;
            }

            $stats = $userModel->getStatistics($filters);

            return [
                'total' => $stats['total_users'] ?? 0,
                'active' => $stats['active_users'] ?? 0,
                'inactive' => $stats['inactive_users'] ?? 0,
                'online' => $stats['online_users'] ?? 0
            ];

        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas de usuários: " . $e->getMessage());
            return ['error' => true];
        }
    }

    /**
     * Atividades recentes
     */
    private function getRecentActivities(?int $municipalityId, int $limit = 10): array
    {
        try {
            $filters = [
                'date_from' => date('Y-m-d', strtotime('-7 days'))
            ];

            if ($municipalityId) {
                $filters['municipality_id'] = $municipalityId;
            }

            $result = $this->auditService->getAuditHistory($filters, 1, $limit);
            
            return $result['data'] ?? [];

        } catch (Exception $e) {
            error_log("Erro ao obter atividades recentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Módulos disponíveis baseado no perfil
     */
    private function getAvailableModules(string $profileName): array
    {
        $allModules = [
            'users' => [
                'name' => 'Usuários',
                'icon' => 'fas fa-users',
                'url' => '/users',
                'description' => 'Gestão de usuários do sistema'
            ],
            'equipment' => [
                'name' => 'Equipamentos',
                'icon' => 'fas fa-tablet-alt',
                'url' => '/equipment',
                'description' => 'Gestão de tablets e chips'
            ],
            'health_forms' => [
                'name' => 'Fichas de Saúde',
                'icon' => 'fas fa-file-medical',
                'url' => '/health-forms',
                'description' => 'Formulários de saúde'
            ],
            'reports' => [
                'name' => 'Relatórios',
                'icon' => 'fas fa-chart-bar',
                'url' => '/reports',
                'description' => 'Relatórios e estatísticas'
            ],
            'municipalities' => [
                'name' => 'Municípios',
                'icon' => 'fas fa-map-marker-alt',
                'url' => '/municipalities',
                'description' => 'Gestão de municípios'
            ],
            'audit' => [
                'name' => 'Auditoria',
                'icon' => 'fas fa-search',
                'url' => '/audit',
                'description' => 'Logs e auditoria'
            ]
        ];

        // Filtrar módulos baseado no perfil
        switch ($profileName) {
            case 'Nacional':
                return $allModules;
                
            case 'Regional':
                unset($allModules['audit']); // Regional não tem acesso completo à auditoria
                return $allModules;
                
            case 'Municipal':
                return [
                    'equipment' => $allModules['equipment'],
                    'health_forms' => $allModules['health_forms'],
                    'reports' => $allModules['reports'],
                    'users' => $allModules['users']
                ];
                
            case 'Unidade':
                return [
                    'health_forms' => $allModules['health_forms'],
                    'reports' => $allModules['reports']
                ];
                
            default:
                return [];
        }
    }

    /**
     * API para obter estatísticas em tempo real (AJAX)
     */
    public function getStats(): void
    {
        try {
            if (!$this->authService->isValidSession()) {
                $this->jsonResponse(['success' => false, 'message' => 'Sessão inválida'], 401);
                return;
            }

            $type = $_GET['type'] ?? '';
            $municipalityId = isset($_GET['municipality_id']) ? (int) $_GET['municipality_id'] : null;

            $data = [];

            switch ($type) {
                case 'health_forms':
                    $data = $this->getHealthFormsStats($municipalityId);
                    break;
                    
                case 'equipment':
                    $data = $this->getEquipmentStats($municipalityId);
                    break;
                    
                case 'users':
                    $data = $this->getUserStats($municipalityId);
                    break;
                    
                case 'activities':
                    $data = $this->getRecentActivities($municipalityId);
                    break;
                    
                default:
                    $this->jsonResponse(['success' => false, 'message' => 'Tipo de estatística inválido'], 400);
                    return;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $data
            ]);

        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Widgets específicos para diferentes perfis
     */
    public function getWidget(): void
    {
        try {
            if (!$this->authService->isValidSession()) {
                $this->jsonResponse(['success' => false, 'message' => 'Sessão inválida'], 401);
                return;
            }

            $widget = $_GET['widget'] ?? '';
            $user = $_SESSION['user'];

            $data = [];

            switch ($widget) {
                case 'alerts':
                    $data = $this->getAlertsWidget($user);
                    break;
                    
                case 'quick_stats':
                    $data = $this->getQuickStatsWidget($user);
                    break;
                    
                case 'recent_forms':
                    $data = $this->getRecentFormsWidget($user);
                    break;
                    
                default:
                    $this->jsonResponse(['success' => false, 'message' => 'Widget inválido'], 400);
                    return;
            }

            $this->jsonResponse([
                'success' => true,
                'widget' => $widget,
                'data' => $data
            ]);

        } catch (Exception $e) {
            error_log("Erro ao obter widget: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Widget de alertas
     */
    private function getAlertsWidget(array $user): array
    {
        $alerts = [];

        try {
            // Alertas baseados no perfil
            $profile = $user['current_profile'];
            
            if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                $executiveDashboard = $this->reportService->generateExecutiveDashboard();
                $alerts = $executiveDashboard['dashboard']['alerts'] ?? [];
            }

            return $alerts;

        } catch (Exception $e) {
            error_log("Erro ao obter alertas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Widget de estatísticas rápidas
     */
    private function getQuickStatsWidget(array $user): array
    {
        try {
            $profile = $user['current_profile'];
            $municipalityId = $profile['municipality_id'] ?? null;

            return [
                'health_forms' => $this->getHealthFormsStats($municipalityId),
                'equipment' => $this->getEquipmentStats($municipalityId),
                'users' => $this->getUserStats($municipalityId)
            ];

        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas rápidas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Widget de formulários recentes
     */
    private function getRecentFormsWidget(array $user): array
    {
        try {
            $profile = $user['current_profile'];
            $municipalityId = $profile['municipality_id'] ?? null;

            $healthFormModel = new HealthForm();
            $filters = [
                'date_from' => date('Y-m-d', strtotime('-7 days'))
            ];

            if ($municipalityId) {
                $filters['municipality_id'] = $municipalityId;
            }

            $result = $healthFormModel->search($filters, 1, 5);
            return $result['data'] ?? [];

        } catch (Exception $e) {
            error_log("Erro ao obter formulários recentes: " . $e->getMessage());
            return [];
        }
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