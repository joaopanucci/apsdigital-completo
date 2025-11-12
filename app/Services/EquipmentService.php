<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Municipality;
use App\Models\User;
use App\Helpers\Validator;
use App\Helpers\Sanitizer;
use App\Config\Database;
use PDO;
use Exception;

/**
 * Serviço de Equipamentos
 * Gerencia tablets, chips, entregas e controle de estoque
 */
class EquipmentService
{
    private $pdo;
    private $equipmentModel;
    private $municipalityModel;
    private $userModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->equipmentModel = new Equipment();
        $this->municipalityModel = new Municipality();
        $this->userModel = new User();
    }

    /**
     * Registra novo lote de equipamentos
     */
    public function registerEquipmentBatch(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Validar dados obrigatórios
            $requiredFields = ['equipment_type', 'quantity', 'supplier', 'purchase_date'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório: {$field}");
                }
            }

            // Sanitizar dados
            $sanitizedData = [
                'equipment_type' => Sanitizer::sanitizeString($data['equipment_type']),
                'quantity' => (int) $data['quantity'],
                'supplier' => Sanitizer::sanitizeString($data['supplier']),
                'purchase_date' => $data['purchase_date'],
                'batch_number' => Sanitizer::sanitizeString($data['batch_number'] ?? ''),
                'unit_cost' => isset($data['unit_cost']) ? (float) $data['unit_cost'] : null,
                'warranty_months' => isset($data['warranty_months']) ? (int) $data['warranty_months'] : null,
                'notes' => Sanitizer::sanitizeString($data['notes'] ?? '')
            ];

            // Validar tipo de equipamento
            if (!in_array($sanitizedData['equipment_type'], ['tablet', 'chip', 'acessorio'])) {
                throw new Exception('Tipo de equipamento inválido');
            }

            // Validar quantidade
            if ($sanitizedData['quantity'] <= 0) {
                throw new Exception('Quantidade deve ser maior que zero');
            }

            // Validar data de compra
            if (!Validator::validateDate($sanitizedData['purchase_date'])) {
                throw new Exception('Data de compra inválida');
            }

            $equipmentIds = [];

            // Criar registros individuais para cada equipamento
            for ($i = 1; $i <= $sanitizedData['quantity']; $i++) {
                $equipmentData = [
                    'equipment_type' => $sanitizedData['equipment_type'],
                    'serial_number' => $this->generateSerialNumber($sanitizedData['equipment_type']),
                    'supplier' => $sanitizedData['supplier'],
                    'purchase_date' => $sanitizedData['purchase_date'],
                    'batch_number' => $sanitizedData['batch_number'],
                    'unit_cost' => $sanitizedData['unit_cost'],
                    'warranty_months' => $sanitizedData['warranty_months'],
                    'status' => 'available',
                    'notes' => $sanitizedData['notes']
                ];

                $equipmentId = $this->equipmentModel->create($equipmentData);
                if (!$equipmentId) {
                    throw new Exception("Erro ao criar equipamento {$i}");
                }
                $equipmentIds[] = $equipmentId;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Lote de {$sanitizedData['quantity']} equipamentos registrado com sucesso",
                'equipment_ids' => $equipmentIds
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao registrar lote de equipamentos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Gera número de série único
     */
    private function generateSerialNumber(string $type): string
    {
        $prefix = match($type) {
            'tablet' => 'TAB',
            'chip' => 'SIM',
            'acessorio' => 'ACC',
            default => 'EQP'
        };

        $year = date('Y');
        $timestamp = time();
        $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return $prefix . $year . $timestamp . $random;
    }

    /**
     * Realiza entrega de equipamentos para município
     */
    public function deliverEquipment(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Validar dados obrigatórios
            $requiredFields = ['equipment_ids', 'municipality_id', 'recipient_name', 'recipient_cpf'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório: {$field}");
                }
            }

            // Sanitizar dados
            $equipmentIds = array_map('intval', (array) $data['equipment_ids']);
            $municipalityId = (int) $data['municipality_id'];
            $recipientName = Sanitizer::sanitizeString($data['recipient_name']);
            $recipientCpf = Sanitizer::sanitizeString($data['recipient_cpf']);
            $deliveryDate = $data['delivery_date'] ?? date('Y-m-d');
            $notes = Sanitizer::sanitizeString($data['notes'] ?? '');

            // Validar CPF do destinatário
            if (!Validator::validateCPF($recipientCpf)) {
                throw new Exception('CPF do destinatário inválido');
            }

            // Validar se município existe
            $municipality = $this->municipalityModel->findById($municipalityId);
            if (!$municipality) {
                throw new Exception('Município não encontrado');
            }

            // Verificar se todos os equipamentos estão disponíveis
            $unavailableEquipments = [];
            foreach ($equipmentIds as $equipmentId) {
                $equipment = $this->equipmentModel->findById($equipmentId);
                if (!$equipment) {
                    throw new Exception("Equipamento ID {$equipmentId} não encontrado");
                }
                if ($equipment['status'] !== 'available') {
                    $unavailableEquipments[] = $equipment['serial_number'];
                }
            }

            if (!empty($unavailableEquipments)) {
                throw new Exception('Equipamentos indisponíveis: ' . implode(', ', $unavailableEquipments));
            }

            // Atualizar status dos equipamentos e registrar entrega
            $deliveryId = $this->createDeliveryRecord([
                'municipality_id' => $municipalityId,
                'recipient_name' => $recipientName,
                'recipient_cpf' => $recipientCpf,
                'delivery_date' => $deliveryDate,
                'notes' => $notes,
                'delivered_by' => $_SESSION['user']['user_id'] ?? null
            ]);

            foreach ($equipmentIds as $equipmentId) {
                // Atualizar equipamento
                $this->equipmentModel->update($equipmentId, [
                    'status' => 'delivered',
                    'municipality_id' => $municipalityId,
                    'delivery_id' => $deliveryId,
                    'delivered_at' => $deliveryDate
                ]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Equipamentos entregues com sucesso',
                'delivery_id' => $deliveryId,
                'equipment_count' => count($equipmentIds)
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro na entrega de equipamentos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Cria registro de entrega
     */
    private function createDeliveryRecord(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO equipment_deliveries 
            (municipality_id, recipient_name, recipient_cpf, delivery_date, 
             notes, delivered_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id
        ");

        $stmt->execute([
            $data['municipality_id'],
            $data['recipient_name'],
            $data['recipient_cpf'],
            $data['delivery_date'],
            $data['notes'],
            $data['delivered_by']
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'];
    }

    /**
     * Registra devolução de equipamentos
     */
    public function returnEquipment(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Validar dados obrigatórios
            $requiredFields = ['equipment_ids', 'return_reason'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório: {$field}");
                }
            }

            $equipmentIds = array_map('intval', (array) $data['equipment_ids']);
            $returnReason = Sanitizer::sanitizeString($data['return_reason']);
            $returnDate = $data['return_date'] ?? date('Y-m-d');
            $condition = Sanitizer::sanitizeString($data['condition'] ?? 'good');
            $notes = Sanitizer::sanitizeString($data['notes'] ?? '');

            // Validar condição
            if (!in_array($condition, ['excellent', 'good', 'fair', 'poor', 'damaged'])) {
                throw new Exception('Condição do equipamento inválida');
            }

            // Verificar se equipamentos podem ser devolvidos
            $invalidEquipments = [];
            foreach ($equipmentIds as $equipmentId) {
                $equipment = $this->equipmentModel->findById($equipmentId);
                if (!$equipment) {
                    throw new Exception("Equipamento ID {$equipmentId} não encontrado");
                }
                if (!in_array($equipment['status'], ['delivered', 'in_use', 'maintenance'])) {
                    $invalidEquipments[] = $equipment['serial_number'];
                }
            }

            if (!empty($invalidEquipments)) {
                throw new Exception('Equipamentos não podem ser devolvidos: ' . implode(', ', $invalidEquipments));
            }

            // Determinar novo status baseado na condição
            $newStatus = match($condition) {
                'excellent', 'good' => 'available',
                'fair' => 'maintenance',
                'poor', 'damaged' => 'damaged'
            };

            // Registrar devolução e atualizar equipamentos
            foreach ($equipmentIds as $equipmentId) {
                // Criar registro de devolução
                $this->createReturnRecord([
                    'equipment_id' => $equipmentId,
                    'return_reason' => $returnReason,
                    'return_date' => $returnDate,
                    'condition' => $condition,
                    'notes' => $notes,
                    'returned_by' => $_SESSION['user']['user_id'] ?? null
                ]);

                // Atualizar equipamento
                $updateData = [
                    'status' => $newStatus,
                    'returned_at' => $returnDate,
                    'condition' => $condition
                ];

                // Se retornou, não está mais com município
                if ($newStatus === 'available') {
                    $updateData['municipality_id'] = null;
                }

                $this->equipmentModel->update($equipmentId, $updateData);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Equipamentos devolvidos com sucesso',
                'equipment_count' => count($equipmentIds),
                'new_status' => $newStatus
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro na devolução de equipamentos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Cria registro de devolução
     */
    private function createReturnRecord(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO equipment_returns 
            (equipment_id, return_reason, return_date, condition, notes, returned_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $data['equipment_id'],
            $data['return_reason'],
            $data['return_date'],
            $data['condition'],
            $data['notes'],
            $data['returned_by']
        ]);
    }

    /**
     * Atualiza status de equipamento
     */
    public function updateEquipmentStatus(int $equipmentId, string $status, string $notes = ''): array
    {
        try {
            // Validar status
            $validStatuses = ['available', 'delivered', 'in_use', 'maintenance', 'damaged', 'disposed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Status inválido');
            }

            $equipment = $this->equipmentModel->findById($equipmentId);
            if (!$equipment) {
                throw new Exception('Equipamento não encontrado');
            }

            $result = $this->equipmentModel->update($equipmentId, [
                'status' => $status,
                'notes' => Sanitizer::sanitizeString($notes)
            ]);

            if (!$result) {
                throw new Exception('Erro ao atualizar status do equipamento');
            }

            return [
                'success' => true,
                'message' => 'Status do equipamento atualizado com sucesso',
                'old_status' => $equipment['status'],
                'new_status' => $status
            ];

        } catch (Exception $e) {
            error_log("Erro ao atualizar status do equipamento: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém relatório de estoque
     */
    public function getStockReport(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['equipment_type'])) {
                $where[] = "e.equipment_type = ?";
                $params[] = $filters['equipment_type'];
            }

            if (!empty($filters['status'])) {
                $where[] = "e.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['municipality_id'])) {
                $where[] = "e.municipality_id = ?";
                $params[] = $filters['municipality_id'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "e.purchase_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "e.purchase_date <= ?";
                $params[] = $filters['date_to'];
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            // Query principal
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.equipment_type,
                    e.status,
                    COUNT(*) as quantity,
                    SUM(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE 0 END) as total_cost,
                    AVG(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE NULL END) as avg_cost
                FROM equipments e
                {$whereClause}
                GROUP BY e.equipment_type, e.status
                ORDER BY e.equipment_type, e.status
            ");

            $stmt->execute($params);
            $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Resumo geral
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_equipments,
                    COUNT(CASE WHEN e.status = 'available' THEN 1 END) as available,
                    COUNT(CASE WHEN e.status = 'delivered' THEN 1 END) as delivered,
                    COUNT(CASE WHEN e.status = 'in_use' THEN 1 END) as in_use,
                    COUNT(CASE WHEN e.status = 'maintenance' THEN 1 END) as maintenance,
                    COUNT(CASE WHEN e.status = 'damaged' THEN 1 END) as damaged,
                    COUNT(CASE WHEN e.status = 'disposed' THEN 1 END) as disposed,
                    SUM(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE 0 END) as total_investment
                FROM equipments e
                {$whereClause}
            ");

            $stmt->execute($params);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'summary' => $summary,
                'details' => $stockData,
                'filters_applied' => $filters
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de estoque: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de estoque'
            ];
        }
    }

    /**
     * Obtém histórico de um equipamento
     */
    public function getEquipmentHistory(int $equipmentId): array
    {
        try {
            $equipment = $this->equipmentModel->findById($equipmentId);
            if (!$equipment) {
                throw new Exception('Equipamento não encontrado');
            }

            // Histórico de entregas
            $stmt = $this->pdo->prepare("
                SELECT 
                    ed.*,
                    m.name as municipality_name,
                    u.name as delivered_by_name
                FROM equipment_deliveries ed
                JOIN municipalities m ON ed.municipality_id = m.id
                LEFT JOIN users u ON ed.delivered_by = u.id
                WHERE ed.id IN (
                    SELECT DISTINCT delivery_id 
                    FROM equipments 
                    WHERE id = ? AND delivery_id IS NOT NULL
                )
                ORDER BY ed.delivery_date DESC
            ");
            $stmt->execute([$equipmentId]);
            $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Histórico de devoluções
            $stmt = $this->pdo->prepare("
                SELECT 
                    er.*,
                    u.name as returned_by_name
                FROM equipment_returns er
                LEFT JOIN users u ON er.returned_by = u.id
                WHERE er.equipment_id = ?
                ORDER BY er.return_date DESC
            ");
            $stmt->execute([$equipmentId]);
            $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Histórico de auditoria
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.*,
                    u.name as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = 'equipments' AND al.record_id = ?
                ORDER BY al.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$equipmentId]);
            $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'equipment' => $equipment,
                'deliveries' => $deliveries,
                'returns' => $returns,
                'audit_trail' => $audits
            ];

        } catch (Exception $e) {
            error_log("Erro ao buscar histórico do equipamento: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Busca equipamentos com filtros avançados
     */
    public function searchEquipments(array $filters, int $page = 1, int $limit = 50): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            // Construir filtros
            if (!empty($filters['search'])) {
                $where[] = "(e.serial_number ILIKE ? OR e.supplier ILIKE ? OR e.notes ILIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (!empty($filters['equipment_type'])) {
                $where[] = "e.equipment_type = ?";
                $params[] = $filters['equipment_type'];
            }

            if (!empty($filters['status'])) {
                $where[] = "e.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['municipality_id'])) {
                $where[] = "e.municipality_id = ?";
                $params[] = $filters['municipality_id'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "e.purchase_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "e.purchase_date <= ?";
                $params[] = $filters['date_to'];
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            // Contar total
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM equipments e
                {$whereClause}
            ");
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Buscar dados
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.*,
                    m.name as municipality_name,
                    ed.recipient_name,
                    ed.delivery_date
                FROM equipments e
                LEFT JOIN municipalities m ON e.municipality_id = m.id
                LEFT JOIN equipment_deliveries ed ON e.delivery_id = ed.id
                {$whereClause}
                ORDER BY e.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $equipments,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'last_page' => ceil($totalCount / $limit)
                ]
            ];

        } catch (Exception $e) {
            error_log("Erro na busca de equipamentos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro na busca de equipamentos'
            ];
        }
    }

    /**
     * Obtém equipamentos próximos ao vencimento da garantia
     */
    public function getWarrantyExpiringEquipments(int $daysAhead = 90): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.*,
                    m.name as municipality_name,
                    (e.purchase_date + INTERVAL '1 month' * e.warranty_months) as warranty_expires_at,
                    EXTRACT(days FROM (e.purchase_date + INTERVAL '1 month' * e.warranty_months) - CURRENT_DATE) as days_to_expiry
                FROM equipments e
                LEFT JOIN municipalities m ON e.municipality_id = m.id
                WHERE e.warranty_months IS NOT NULL
                AND (e.purchase_date + INTERVAL '1 month' * e.warranty_months) BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '{$daysAhead} days'
                AND e.status NOT IN ('disposed', 'damaged')
                ORDER BY warranty_expires_at ASC
            ");
            
            $stmt->execute();
            $equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'equipments' => $equipments,
                'count' => count($equipments)
            ];

        } catch (Exception $e) {
            error_log("Erro ao buscar equipamentos com garantia vencendo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao buscar equipamentos com garantia vencendo'
            ];
        }
    }
}