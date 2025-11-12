<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\EquipmentService;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Models\Equipment;
use App\Models\Municipality;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Helpers\Sanitizer;
use Exception;

/**
 * Controlador de Equipamentos
 * Gestão de tablets, chips, entregas e devoluções
 */
class EquipmentController
{
    private $authService;
    private $equipmentService;
    private $auditService;
    private $emailService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->equipmentService = new EquipmentService();
        $this->auditService = new AuditService();
        $this->emailService = new EmailService();
    }

    /**
     * Página principal de equipamentos
     */
    public function index(): void
    {
        try {
            // Verificar autenticação e permissões
            if (!$this->checkPermission('equipment', 'read')) {
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

            // Obter equipamentos
            $page = (int) ($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $type = $_GET['type'] ?? '';

            if ($search) $filters['search'] = $search;
            if ($status) $filters['status'] = $status;
            if ($type) $filters['equipment_type'] = $type;

            $equipments = $this->equipmentService->searchEquipments($filters, $page, 20);
            $stockReport = $this->equipmentService->getStockReport($filters);

            // Obter municípios para filtros (se permitido)
            $municipalities = [];
            if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                $municipalityModel = new Municipality();
                $municipalities = $municipalityModel->getAll(['active' => true]);
            }

            $data = [
                'title' => 'Equipamentos - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'equipments' => $equipments,
                'stock_report' => $stockReport,
                'municipalities' => $municipalities,
                'filters' => $filters,
                'csrf_token' => Security::generateCSRFToken(),
                'can_create' => $this->authService->hasPermission('equipment', 'create'),
                'can_update' => $this->authService->hasPermission('equipment', 'update'),
                'can_delete' => $this->authService->hasPermission('equipment', 'delete')
            ];

            $this->view('equipment/index', $data);

        } catch (Exception $e) {
            error_log("Erro na página de equipamentos: " . $e->getMessage());
            $this->view('errors/500', ['message' => 'Erro interno do servidor']);
        }
    }

    /**
     * Página de registro de lote de equipamentos
     */
    public function register(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'create')) {
                $this->redirect('/equipment?error=permission_denied');
                return;
            }

            $data = [
                'title' => 'Registrar Equipamentos - APS Digital',
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('equipment/register', $data);

        } catch (Exception $e) {
            error_log("Erro na página de registro: " . $e->getMessage());
            $this->redirect('/equipment?error=system_error');
        }
    }

    /**
     * Processa registro de lote de equipamentos
     */
    public function store(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'create')) {
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

            // Sanitizar dados
            $data = [
                'equipment_type' => Sanitizer::sanitizeString($_POST['equipment_type'] ?? ''),
                'quantity' => (int) ($_POST['quantity'] ?? 0),
                'supplier' => Sanitizer::sanitizeString($_POST['supplier'] ?? ''),
                'purchase_date' => $_POST['purchase_date'] ?? '',
                'batch_number' => Sanitizer::sanitizeString($_POST['batch_number'] ?? ''),
                'unit_cost' => !empty($_POST['unit_cost']) ? (float) $_POST['unit_cost'] : null,
                'warranty_months' => !empty($_POST['warranty_months']) ? (int) $_POST['warranty_months'] : null,
                'notes' => Sanitizer::sanitizeString($_POST['notes'] ?? '')
            ];

            $result = $this->equipmentService->registerEquipmentBatch($data);

            if ($result['success']) {
                $this->auditService->logAction(
                    'equipments',
                    0, // Múltiplos IDs
                    'batch_register',
                    [],
                    [
                        'quantity' => $data['quantity'],
                        'type' => $data['equipment_type'],
                        'supplier' => $data['supplier']
                    ]
                );
            }

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro ao registrar equipamentos: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Página de autorização de entrega
     */
    public function authorize(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'update')) {
                $this->redirect('/equipment?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Obter equipamentos disponíveis
            $filters = ['status' => 'available'];
            $availableEquipments = $this->equipmentService->searchEquipments($filters, 1, 100);

            // Obter municípios
            $municipalityModel = new Municipality();
            $municipalities = [];

            if (in_array($profile['profile_name'], ['Nacional', 'Regional'])) {
                $municipalities = $municipalityModel->getAll(['active' => true]);
            } elseif ($profile['municipality_id']) {
                $municipality = $municipalityModel->findById($profile['municipality_id']);
                if ($municipality) {
                    $municipalities = [$municipality];
                }
            }

            $data = [
                'title' => 'Autorizar Entrega - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'available_equipments' => $availableEquipments['data'] ?? [],
                'municipalities' => $municipalities,
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('equipment/authorize', $data);

        } catch (Exception $e) {
            error_log("Erro na página de autorização: " . $e->getMessage());
            $this->redirect('/equipment?error=system_error');
        }
    }

    /**
     * Processa entrega de equipamentos
     */
    public function deliver(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'update')) {
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

            // Sanitizar dados
            $data = [
                'equipment_ids' => array_map('intval', $_POST['equipment_ids'] ?? []),
                'municipality_id' => (int) ($_POST['municipality_id'] ?? 0),
                'recipient_name' => Sanitizer::sanitizeString($_POST['recipient_name'] ?? ''),
                'recipient_cpf' => Sanitizer::sanitizeString($_POST['recipient_cpf'] ?? ''),
                'delivery_date' => $_POST['delivery_date'] ?? date('Y-m-d'),
                'notes' => Sanitizer::sanitizeString($_POST['notes'] ?? '')
            ];

            $result = $this->equipmentService->deliverEquipment($data);

            if ($result['success']) {
                // Enviar notificação por e-mail se configurado
                if (!empty($_POST['recipient_email'])) {
                    $equipmentModel = new Equipment();
                    $equipments = [];
                    foreach ($data['equipment_ids'] as $equipmentId) {
                        $equipment = $equipmentModel->findById($equipmentId);
                        if ($equipment) {
                            $equipments[] = $equipment;
                        }
                    }

                    $deliveryData = $data;
                    $deliveryData['recipient_email'] = $_POST['recipient_email'];
                    
                    $this->emailService->sendEquipmentDeliveryNotification($deliveryData, $equipments);
                }
            }

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro na entrega de equipamentos: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Página de devolução de equipamentos
     */
    public function returnPage(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'update')) {
                $this->redirect('/equipment?error=permission_denied');
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Obter equipamentos entregues/em uso
            $filters = ['status' => ['delivered', 'in_use', 'maintenance']];
            if (!in_array($profile['profile_name'], ['Nacional', 'Regional']) && $profile['municipality_id']) {
                $filters['municipality_id'] = $profile['municipality_id'];
            }

            $deliveredEquipments = $this->equipmentService->searchEquipments($filters, 1, 100);

            $data = [
                'title' => 'Devolução de Equipamentos - APS Digital',
                'user' => $user,
                'profile' => $profile,
                'delivered_equipments' => $deliveredEquipments['data'] ?? [],
                'csrf_token' => Security::generateCSRFToken()
            ];

            $this->view('equipment/return', $data);

        } catch (Exception $e) {
            error_log("Erro na página de devolução: " . $e->getMessage());
            $this->redirect('/equipment?error=system_error');
        }
    }

    /**
     * Processa devolução de equipamentos
     */
    public function processReturn(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'update')) {
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

            // Sanitizar dados
            $data = [
                'equipment_ids' => array_map('intval', $_POST['equipment_ids'] ?? []),
                'return_reason' => Sanitizer::sanitizeString($_POST['return_reason'] ?? ''),
                'return_date' => $_POST['return_date'] ?? date('Y-m-d'),
                'condition' => Sanitizer::sanitizeString($_POST['condition'] ?? 'good'),
                'notes' => Sanitizer::sanitizeString($_POST['notes'] ?? '')
            ];

            $result = $this->equipmentService->returnEquipment($data);

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro na devolução de equipamentos: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Visualiza histórico de um equipamento
     */
    public function history(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'read')) {
                $this->redirect('/equipment?error=permission_denied');
                return;
            }

            $equipmentId = (int) ($_GET['id'] ?? 0);
            if (!$equipmentId) {
                $this->redirect('/equipment?error=invalid_equipment');
                return;
            }

            $history = $this->equipmentService->getEquipmentHistory($equipmentId);

            if (!$history['success']) {
                $this->redirect('/equipment?error=equipment_not_found');
                return;
            }

            $data = [
                'title' => 'Histórico do Equipamento - APS Digital',
                'equipment' => $history['equipment'],
                'deliveries' => $history['deliveries'],
                'returns' => $history['returns'],
                'audit_trail' => $history['audit_trail']
            ];

            $this->view('equipment/history', $data);

        } catch (Exception $e) {
            error_log("Erro ao visualizar histórico: " . $e->getMessage());
            $this->redirect('/equipment?error=system_error');
        }
    }

    /**
     * Atualiza status de equipamento
     */
    public function updateStatus(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'update')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            // Verificar método POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
                return;
            }

            $equipmentId = (int) ($_POST['equipment_id'] ?? 0);
            $status = Sanitizer::sanitizeString($_POST['status'] ?? '');
            $notes = Sanitizer::sanitizeString($_POST['notes'] ?? '');

            if (!$equipmentId || !$status) {
                $this->jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
                return;
            }

            $result = $this->equipmentService->updateEquipmentStatus($equipmentId, $status, $notes);

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro ao atualizar status: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Relatório de estoque (API)
     */
    public function stockReport(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'read')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Aplicar filtros baseados no perfil
            $filters = [];
            if (!in_array($profile['profile_name'], ['Nacional', 'Regional']) && $profile['municipality_id']) {
                $filters['municipality_id'] = $profile['municipality_id'];
            }

            // Aplicar filtros da requisição
            if (!empty($_GET['equipment_type'])) {
                $filters['equipment_type'] = $_GET['equipment_type'];
            }
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['municipality_id'])) {
                $filters['municipality_id'] = (int) $_GET['municipality_id'];
            }

            $report = $this->equipmentService->getStockReport($filters);

            $this->jsonResponse($report);

        } catch (Exception $e) {
            error_log("Erro no relatório de estoque: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Alertas de garantia vencendo
     */
    public function warrantyAlerts(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'read')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $daysAhead = (int) ($_GET['days'] ?? 90);
            $result = $this->equipmentService->getWarrantyExpiringEquipments($daysAhead);

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro nos alertas de garantia: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
    }

    /**
     * Busca equipamentos (AJAX)
     */
    public function search(): void
    {
        try {
            // Verificar permissões
            if (!$this->checkPermission('equipment', 'read')) {
                $this->jsonResponse(['success' => false, 'message' => 'Permissão negada'], 403);
                return;
            }

            $user = $_SESSION['user'];
            $profile = $user['current_profile'];

            // Filtros baseados no perfil
            $filters = [];
            if (!in_array($profile['profile_name'], ['Nacional', 'Regional']) && $profile['municipality_id']) {
                $filters['municipality_id'] = $profile['municipality_id'];
            }

            // Aplicar filtros da busca
            $searchParams = ['search', 'equipment_type', 'status', 'municipality_id', 'date_from', 'date_to'];
            foreach ($searchParams as $param) {
                if (!empty($_GET[$param])) {
                    $filters[$param] = $_GET[$param];
                }
            }

            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 20);

            $result = $this->equipmentService->searchEquipments($filters, $page, $limit);

            $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Erro na busca de equipamentos: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Erro interno do servidor'], 500);
        }
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