<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\ReportService;
use App\Services\EmailService;
use App\Services\AuditService;
use App\Helpers\Security;
use Exception;

/**
 * Controlador de Relatórios
 * Geração e exportação de relatórios do sistema
 */
class ReportController
{
    private $authService;
    private $reportService;
    private $emailService;
    private $auditService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->reportService = new ReportService();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
    }

    /**
     * Página principal de relatórios
     */
    public function index(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('reports', 'read')) {
                $this->redirect('/dashboard?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Tipos de relatórios disponíveis baseados no perfil
            $availableReports = $this->getAvailableReports($profile['profile_name']);

            $data = [
                'title' => 'Relatórios - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'available_reports' => $availableReports,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('reports/index', $data);

        } catch (Exception $e) {
            error_log("Erro na página de relatórios: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Relatório de fichas de saúde
     */
    public function healthForms(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('reports', 'read')) {
                $this->redirect('/reports?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Filtros baseados no perfil
            $filters = $this->buildFilters($profile);

            // Aplicar filtros da requisição
            $requestFilters = [
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'age_from' => $_GET['age_from'] ?? '',
                'age_to' => $_GET['age_to'] ?? '',
                'gender' => $_GET['gender'] ?? '',
                'municipality_id' => $_GET['municipality_id'] ?? ''
            ];

            foreach ($requestFilters as $key => $value) {
                if (!empty($value)) {
                    $filters[$key] = $value;
                }
            }

            // Gerar relatório
            $report = $this->reportService->generateHealthFormsReport($filters);

            if (!$report['success']) {
                $this->view('errors/500', ['message' => 'Erro ao gerar relatório']);
                return;
            }

            $data = [
                'title' => 'Relatório de Fichas de Saúde - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'report' => $report['report'],
                'filters' => $filters,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('reports/health-forms', $data);

        } catch (Exception $e) {
            error_log("Erro no relatório de fichas de saúde: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Relatório de equipamentos
     */
    public function equipment(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('reports', 'read')) {
                $this->redirect('/reports?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Filtros baseados no perfil
            $filters = $this->buildFilters($profile);

            // Aplicar filtros da requisição
            $requestFilters = [
                'equipment_type' => $_GET['equipment_type'] ?? '',
                'status' => $_GET['status'] ?? '',
                'municipality_id' => $_GET['municipality_id'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? ''
            ];

            foreach ($requestFilters as $key => $value) {
                if (!empty($value)) {
                    $filters[$key] = $value;
                }
            }

            // Gerar relatório
            $report = $this->reportService->generateEquipmentReport($filters);

            if (!$report['success']) {
                $this->view('errors/500', ['message' => 'Erro ao gerar relatório']);
                return;
            }

            $data = [
                'title' => 'Relatório de Equipamentos - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'report' => $report['report'],
                'filters' => $filters,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('reports/equipment', $data);

        } catch (Exception $e) {
            error_log("Erro no relatório de equipamentos: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Relatório de usuários
     */
    public function users(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('users', 'read') || !$this->checkPermission('reports', 'read')) {
                $this->redirect('/reports?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Filtros baseados no perfil
            $filters = $this->buildFilters($profile);

            // Aplicar filtros da requisição
            $requestFilters = [
                'profile_name' => $_GET['profile_name'] ?? '',
                'municipality_id' => $_GET['municipality_id'] ?? '',
                'active' => $_GET['active'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? ''
            ];

            foreach ($requestFilters as $key => $value) {
                if (!empty($value)) {
                    $filters[$key] = $value;
                }
            }

            // Gerar relatório
            $report = $this->reportService->generateUsersReport($filters);

            if (!$report['success']) {
                $this->view('errors/500', ['message' => 'Erro ao gerar relatório']);
                return;
            }

            $data = [
                'title' => 'Relatório de Usuários - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'report' => $report['report'],
                'filters' => $filters,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('reports/users', $data);

        } catch (Exception $e) {
            error_log("Erro no relatório de usuários: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Dashboard executivo
     */
    public function executive(): void
    {
        try {
            // Verificar permissões (apenas Nacional e Regional)
            $profile = $_SESSION['user']['current_profile'];
            if (!in_array($profile['profile_name'], ['Nacional', 'Regional']) || 
                !$this->checkPermission('reports', 'read')) {
                $this->redirect('/reports?error=permission_denied');
                return;
            }

            // Gerar dashboard executivo
            $dashboard = $this->reportService->generateExecutiveDashboard();

            if (!$dashboard['success']) {
                $this->view('errors/500', ['message' => 'Erro ao gerar dashboard']);
                return;
            }

            $data = [
                'title' => 'Dashboard Executivo - APS Digital',
                'user' => $_SESSION['user'],
                'profile' => $profile,
                'dashboard' => $dashboard['dashboard'],
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('reports/executive', $data);

        } catch (Exception $e) {
            error_log("Erro no dashboard executivo: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Exporta relatório
     */
    public function export(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('reports', 'export')) {
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

            $reportType = $_POST['report_type'] ?? '';
            $format = $_POST['format'] ?? 'csv';
            $filters = json_decode($_POST['filters'] ?? '{}', true) ?: [];

            if (!in_array($reportType, ['health_forms', 'equipment', 'users'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Tipo de relatório inválido'], 400);
                return;
            }

            // Aplicar filtros de perfil
            $profile = $_SESSION['user']['current_profile'];
            $filters = array_merge($this->buildFilters($profile), $filters);

            // Gerar relatório
            $reportData = null;
            switch ($reportType) {
                case 'health_forms':
                    $reportData = $this->reportService->generateHealthFormsReport($filters);
                    break;
                case 'equipment':
                    $reportData = $this->reportService->generateEquipmentReport($filters);
                    break;
                case 'users':
                    $reportData = $this->reportService->generateUsersReport($filters);
                    break;
            }

            if (!$reportData || !$reportData['success']) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao gerar relatório'], 500);
                return;
            }

            // Exportar para CSV
            $exportResult = $this->reportService->exportToCSV($reportType, $reportData['report'], $filters);

            if (!$exportResult['success']) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro na exportação'], 500);
                return;
            }

            // Log da exportação
            $this->auditService->logAction(
                'reports',
                0,
                'export',
                [],
                [
                    'report_type' => $reportType,
                    'format' => $format,
                    'filename' => $exportResult['filename'],
                    'filters' => $filters
                ]
            );

            // Enviar arquivo para download
            $this->downloadFile($exportResult['filepath'], $exportResult['filename']);

        } catch (Exception $e) {
            error_log("Erro na exportação de relatório: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Envia relatório por e-mail
     */
    public function sendByEmail(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('reports', 'export')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            $reportType = $_POST['report_type'] ?? '';
            $recipients = json_decode($_POST['recipients'] ?? '[]', true) ?: [];
            $filters = json_decode($_POST['filters'] ?? '{}', true) ?: [];

            if (empty($recipients) || !in_array($reportType, ['health_forms', 'equipment', 'users'])) {
                $this->jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
                return;
            }

            // Aplicar filtros de perfil
            $profile = $_SESSION['user']['current_profile'];
            $filters = array_merge($this->buildFilters($profile), $filters);

            // Gerar e exportar relatório
            switch ($reportType) {
                case 'health_forms':
                    $reportData = $this->reportService->generateHealthFormsReport($filters);
                    break;
                case 'equipment':
                    $reportData = $this->reportService->generateEquipmentReport($filters);
                    break;
                case 'users':
                    $reportData = $this->reportService->generateUsersReport($filters);
                    break;
            }

            if (!$reportData || !$reportData['success']) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro ao gerar relatório'], 500);
                return;
            }

            $exportResult = $this->reportService->exportToCSV($reportType, $reportData['report'], $filters);

            if (!$exportResult['success']) {
                $this->jsonResponse(['success' => false, 'message' => 'Erro na exportação'], 500);
                return;
            }

            // Enviar por e-mail
            $emailResult = $this->emailService->sendReport($recipients, $reportType, $exportResult['filepath'], $reportData);

            // Limpar arquivo temporário
            if (file_exists($exportResult['filepath'])) {
                unlink($exportResult['filepath']);
            }

            // Log da ação
            $this->auditService->logAction(
                'reports',
                0,
                'email_sent',
                [],
                [
                    'report_type' => $reportType,
                    'recipients' => array_keys($recipients),
                    'filters' => $filters
                ]
            );

            $this->jsonResponse($emailResult);

        } catch (Exception $e) {
            error_log("Erro ao enviar relatório por e-mail: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * API para obter dados de relatório (AJAX)
     */
    public function getData(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('reports', 'read')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $reportType = $_GET['type'] ?? '';
            $filters = [];

            // Aplicar filtros baseados no perfil
            $profile = $_SESSION['user']['current_profile'];
            $filters = $this->buildFilters($profile);

            // Aplicar filtros da requisição
            $allowedFilters = [
                'date_from', 'date_to', 'municipality_id', 'equipment_type', 
                'status', 'profile_name', 'active', 'age_from', 'age_to', 'gender'
            ];

            foreach ($allowedFilters as $filter) {
                if (!empty($_GET[$filter])) {
                    $filters[$filter] = $_GET[$filter];
                }
            }

            // Gerar relatório
            $reportData = null;
            switch ($reportType) {
                case 'health_forms':
                    $reportData = $this->reportService->generateHealthFormsReport($filters);
                    break;
                case 'equipment':
                    $reportData = $this->reportService->generateEquipmentReport($filters);
                    break;
                case 'users':
                    $reportData = $this->reportService->generateUsersReport($filters);
                    break;
                case 'executive':
                    if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                        $reportData = $this->reportService->generateExecutiveDashboard();
                    }
                    break;
                default:
                    $this->jsonResponse(['success' => false, 'message' => 'Tipo de relatório inválido'], 400);
                    return;
            }

            if (!$reportData) {
                $this->jsonResponse(['success' => false, 'message' => 'Relatório não disponível'], 400);
                return;
            }

            $this->jsonResponse($reportData);

        } catch (Exception $e) {
            error_log("Erro ao obter dados do relatório: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Obtém relatórios disponíveis baseado no perfil
     */
    private function getAvailableReports(string $profileName): array
    {
        $allReports = [
            'health_forms' => [
                'name' => 'Fichas de Saúde',
                'description' => 'Estatísticas e dados das fichas de saúde coletadas',
                'icon' => 'fas fa-file-medical',
                'url' => '/reports/health-forms'
            ],
            'equipment' => [
                'name' => 'Equipamentos',
                'description' => 'Relatório de estoque, entregas e status dos equipamentos',
                'icon' => 'fas fa-tablet-alt',
                'url' => '/reports/equipment'
            ],
            'users' => [
                'name' => 'Usuários',
                'description' => 'Relatório de usuários, perfis e acessos ao sistema',
                'icon' => 'fas fa-users',
                'url' => '/reports/users'
            ],
            'executive' => [
                'name' => 'Dashboard Executivo',
                'description' => 'Visão executiva com KPIs e métricas principais',
                'icon' => 'fas fa-chart-line',
                'url' => '/reports/executive'
            ]
        ];

        // Filtrar relatórios baseado no perfil
        switch ($profileName) {
            case 'Nacional':
                return $allReports;
                
            case 'Regional':
                return $allReports; // Regional tem acesso a todos
                
            case 'Municipal':
                unset($allReports['executive']); // Municipal não tem dashboard executivo
                return $allReports;
                
            case 'Unidade':
                return [
                    'health_forms' => $allReports['health_forms'],
                    'equipment' => $allReports['equipment']
                ];
                
            default:
                return [];
        }
    }

    /**
     * Constrói filtros baseados no perfil
     */
    private function buildFilters(array $profile): array
    {
        $filters = [];

        // Aplicar filtro de município se necessário
        if (!in_array($profile['profile_name'], ['Nacional', 'Regional']) && $profile['municipality_id']) {
            $filters['municipality_id'] = $profile['municipality_id'];
        }

        return $filters;
    }

    /**
     * Faz download de arquivo
     */
    private function downloadFile(string $filepath, string $filename): void
    {
        if (!file_exists($filepath)) {
            http_response_code(404);
            echo "Arquivo não encontrado";
            return;
        }

        // Headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        // Enviar arquivo
        readfile($filepath);
        
        // Limpar arquivo temporário
        unlink($filepath);
        exit;
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