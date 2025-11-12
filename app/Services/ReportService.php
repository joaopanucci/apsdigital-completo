<?php

namespace App\Services;

use App\Models\HealthForm;
use App\Models\Municipality;
use App\Models\Equipment;
use App\Models\User;
use App\Config\Database;
use PDO;
use Exception;

/**
 * Serviço de Relatórios
 * Gera relatórios consolidados do sistema APS Digital
 */
class ReportService
{
    private $pdo;
    private $healthFormModel;
    private $municipalityModel;
    private $equipmentModel;
    private $userModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->healthFormModel = new HealthForm();
        $this->municipalityModel = new Municipality();
        $this->equipmentModel = new Equipment();
        $this->userModel = new User();
    }

    /**
     * Gera relatório consolidado de fichas de saúde
     */
    public function generateHealthFormsReport(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['municipality_id'])) {
                $where[] = "hf.municipality_id = ?";
                $params[] = $filters['municipality_id'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "hf.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "hf.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['age_from'])) {
                $where[] = "hf.patient_age >= ?";
                $params[] = $filters['age_from'];
            }

            if (!empty($filters['age_to'])) {
                $where[] = "hf.patient_age <= ?";
                $params[] = $filters['age_to'];
            }

            if (!empty($filters['gender'])) {
                $where[] = "hf.patient_gender = ?";
                $params[] = $filters['gender'];
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            // Estatísticas gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_forms,
                    COUNT(DISTINCT hf.municipality_id) as municipalities_count,
                    AVG(hf.patient_age) as avg_age,
                    COUNT(CASE WHEN hf.patient_gender = 'M' THEN 1 END) as male_count,
                    COUNT(CASE WHEN hf.patient_gender = 'F' THEN 1 END) as female_count,
                    COUNT(CASE WHEN hf.has_chronic_disease THEN 1 END) as chronic_disease_count,
                    COUNT(CASE WHEN hf.takes_medication THEN 1 END) as takes_medication_count,
                    COUNT(CASE WHEN hf.has_allergies THEN 1 END) as has_allergies_count
                FROM health_forms hf
                {$whereClause}
            ");
            $stmt->execute($params);
            $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Por município
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.name as municipality_name,
                    COUNT(hf.id) as forms_count,
                    AVG(hf.patient_age) as avg_age,
                    COUNT(CASE WHEN hf.patient_gender = 'M' THEN 1 END) as male_count,
                    COUNT(CASE WHEN hf.patient_gender = 'F' THEN 1 END) as female_count
                FROM health_forms hf
                JOIN municipalities m ON hf.municipality_id = m.id
                {$whereClause}
                GROUP BY m.id, m.name
                ORDER BY forms_count DESC
            ");
            $stmt->execute($params);
            $by_municipality = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por faixa etária
            $stmt = $this->pdo->prepare("
                SELECT 
                    CASE 
                        WHEN hf.patient_age BETWEEN 0 AND 17 THEN '0-17'
                        WHEN hf.patient_age BETWEEN 18 AND 29 THEN '18-29'
                        WHEN hf.patient_age BETWEEN 30 AND 39 THEN '30-39'
                        WHEN hf.patient_age BETWEEN 40 AND 49 THEN '40-49'
                        WHEN hf.patient_age BETWEEN 50 AND 59 THEN '50-59'
                        WHEN hf.patient_age >= 60 THEN '60+'
                        ELSE 'Não informado'
                    END as age_group,
                    COUNT(*) as count
                FROM health_forms hf
                {$whereClause}
                GROUP BY age_group
                ORDER BY age_group
            ");
            $stmt->execute($params);
            $by_age_group = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Doenças crônicas mais comuns
            $stmt = $this->pdo->prepare("
                SELECT 
                    unnest(string_to_array(hf.chronic_diseases, ',')) as disease,
                    COUNT(*) as count
                FROM health_forms hf
                {$whereClause}
                AND hf.has_chronic_disease = true
                AND hf.chronic_diseases IS NOT NULL
                GROUP BY disease
                HAVING disease != ''
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            $chronic_diseases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Medicamentos mais utilizados
            $stmt = $this->pdo->prepare("
                SELECT 
                    unnest(string_to_array(hf.medications, ',')) as medication,
                    COUNT(*) as count
                FROM health_forms hf
                {$whereClause}
                AND hf.takes_medication = true
                AND hf.medications IS NOT NULL
                GROUP BY medication
                HAVING medication != ''
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Evolução temporal (últimos 12 meses)
            $stmt = $this->pdo->prepare("
                SELECT 
                    TO_CHAR(hf.created_at, 'YYYY-MM') as month,
                    COUNT(*) as forms_count
                FROM health_forms hf
                WHERE hf.created_at >= CURRENT_DATE - INTERVAL '12 months'
                " . (!empty($whereClause) ? " AND " . str_replace('WHERE ', '', $whereClause) : '') . "
                GROUP BY month
                ORDER BY month
            ");
            $stmt->execute($params);
            $temporal_evolution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'report' => [
                    'general_statistics' => $general_stats,
                    'by_municipality' => $by_municipality,
                    'by_age_group' => $by_age_group,
                    'chronic_diseases' => $chronic_diseases,
                    'medications' => $medications,
                    'temporal_evolution' => $temporal_evolution
                ],
                'filters_applied' => $filters,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de fichas de saúde: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de fichas de saúde'
            ];
        }
    }

    /**
     * Gera relatório de equipamentos
     */
    public function generateEquipmentReport(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['equipment_type'])) {
                $where[] = "e.equipment_type = ?";
                $params[] = $filters['equipment_type'];
            }

            if (!empty($filters['municipality_id'])) {
                $where[] = "e.municipality_id = ?";
                $params[] = $filters['municipality_id'];
            }

            if (!empty($filters['status'])) {
                $where[] = "e.status = ?";
                $params[] = $filters['status'];
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

            // Estatísticas gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_equipment,
                    COUNT(CASE WHEN e.status = 'available' THEN 1 END) as available_count,
                    COUNT(CASE WHEN e.status = 'delivered' THEN 1 END) as delivered_count,
                    COUNT(CASE WHEN e.status = 'in_use' THEN 1 END) as in_use_count,
                    COUNT(CASE WHEN e.status = 'maintenance' THEN 1 END) as maintenance_count,
                    COUNT(CASE WHEN e.status = 'damaged' THEN 1 END) as damaged_count,
                    COUNT(CASE WHEN e.status = 'disposed' THEN 1 END) as disposed_count,
                    SUM(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE 0 END) as total_investment,
                    AVG(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE NULL END) as avg_cost
                FROM equipments e
                {$whereClause}
            ");
            $stmt->execute($params);
            $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Por tipo de equipamento
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.equipment_type,
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN e.status = 'available' THEN 1 END) as available,
                    COUNT(CASE WHEN e.status = 'delivered' THEN 1 END) as delivered,
                    COUNT(CASE WHEN e.status = 'in_use' THEN 1 END) as in_use,
                    COUNT(CASE WHEN e.status = 'maintenance' THEN 1 END) as maintenance,
                    COUNT(CASE WHEN e.status = 'damaged' THEN 1 END) as damaged,
                    SUM(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE 0 END) as total_cost
                FROM equipments e
                {$whereClause}
                GROUP BY e.equipment_type
                ORDER BY total_count DESC
            ");
            $stmt->execute($params);
            $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por município
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.name as municipality_name,
                    COUNT(e.id) as equipment_count,
                    COUNT(CASE WHEN e.equipment_type = 'tablet' THEN 1 END) as tablets,
                    COUNT(CASE WHEN e.equipment_type = 'chip' THEN 1 END) as chips,
                    COUNT(CASE WHEN e.equipment_type = 'acessorio' THEN 1 END) as accessories
                FROM equipments e
                JOIN municipalities m ON e.municipality_id = m.id
                {$whereClause}
                GROUP BY m.id, m.name
                HAVING COUNT(e.id) > 0
                ORDER BY equipment_count DESC
            ");
            $stmt->execute($params);
            $by_municipality = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por fornecedor
            $stmt = $this->pdo->prepare("
                SELECT 
                    e.supplier,
                    COUNT(*) as equipment_count,
                    SUM(CASE WHEN e.unit_cost IS NOT NULL THEN e.unit_cost ELSE 0 END) as total_cost,
                    MIN(e.purchase_date) as first_purchase,
                    MAX(e.purchase_date) as last_purchase
                FROM equipments e
                {$whereClause}
                GROUP BY e.supplier
                ORDER BY equipment_count DESC
            ");
            $stmt->execute($params);
            $by_supplier = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Evolução de entregas (últimos 12 meses)
            $stmt = $this->pdo->prepare("
                SELECT 
                    TO_CHAR(ed.delivery_date, 'YYYY-MM') as month,
                    COUNT(DISTINCT ed.id) as deliveries_count,
                    COUNT(e.id) as equipment_delivered
                FROM equipment_deliveries ed
                LEFT JOIN equipments e ON e.delivery_id = ed.id
                WHERE ed.delivery_date >= CURRENT_DATE - INTERVAL '12 months'
                GROUP BY month
                ORDER BY month
            ");
            $stmt->execute();
            $delivery_evolution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Equipamentos com garantia vencendo (próximos 90 dias)
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as expiring_warranty_count
                FROM equipments e
                WHERE e.warranty_months IS NOT NULL
                AND (e.purchase_date + INTERVAL '1 month' * e.warranty_months) BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '90 days'
                AND e.status NOT IN ('disposed', 'damaged')
            ");
            $stmt->execute();
            $warranty_expiring = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'report' => [
                    'general_statistics' => $general_stats,
                    'by_type' => $by_type,
                    'by_municipality' => $by_municipality,
                    'by_supplier' => $by_supplier,
                    'delivery_evolution' => $delivery_evolution,
                    'warranty_expiring' => $warranty_expiring
                ],
                'filters_applied' => $filters,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de equipamentos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de equipamentos'
            ];
        }
    }

    /**
     * Gera relatório de usuários e acessos
     */
    public function generateUsersReport(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['profile_name'])) {
                $where[] = "p.profile_name = ?";
                $params[] = $filters['profile_name'];
            }

            if (!empty($filters['municipality_id'])) {
                $where[] = "up.municipality_id = ?";
                $params[] = $filters['municipality_id'];
            }

            if (!empty($filters['active'])) {
                $where[] = "u.active = ?";
                $params[] = $filters['active'] === 'true';
            }

            if (!empty($filters['date_from'])) {
                $where[] = "u.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "u.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            // Estatísticas gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(CASE WHEN u.active THEN 1 END) as active_users,
                    COUNT(CASE WHEN NOT u.active THEN 1 END) as inactive_users,
                    COUNT(DISTINCT up.municipality_id) as municipalities_with_users
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                LEFT JOIN profiles p ON up.profile_id = p.id
                {$whereClause}
            ");
            $stmt->execute($params);
            $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Por perfil
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.profile_name,
                    p.description,
                    COUNT(DISTINCT up.user_id) as users_count,
                    COUNT(CASE WHEN u.active THEN 1 END) as active_users
                FROM profiles p
                LEFT JOIN user_profiles up ON p.id = up.profile_id AND up.active = true
                LEFT JOIN users u ON up.user_id = u.id
                " . (!empty($whereClause) ? str_replace('WHERE ', 'WHERE p.id IN (SELECT DISTINCT profile_id FROM user_profiles) AND ', $whereClause) : '') . "
                GROUP BY p.id, p.profile_name, p.description
                ORDER BY users_count DESC
            ");
            $stmt->execute($params);
            $by_profile = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por município
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.name as municipality_name,
                    COUNT(DISTINCT up.user_id) as users_count,
                    COUNT(CASE WHEN u.active THEN 1 END) as active_users,
                    string_agg(DISTINCT p.profile_name, ', ') as profiles
                FROM municipalities m
                LEFT JOIN user_profiles up ON m.id = up.municipality_id AND up.active = true
                LEFT JOIN users u ON up.user_id = u.id
                LEFT JOIN profiles p ON up.profile_id = p.id
                " . (!empty($whereClause) ? str_replace('WHERE ', 'WHERE m.id IN (SELECT DISTINCT municipality_id FROM user_profiles WHERE municipality_id IS NOT NULL) AND ', $whereClause) : '') . "
                GROUP BY m.id, m.name
                HAVING COUNT(DISTINCT up.user_id) > 0
                ORDER BY users_count DESC
            ");
            $stmt->execute($params);
            $by_municipality = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Últimos acessos (baseado em audit_logs)
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.name,
                    u.cpf,
                    p.profile_name,
                    m.name as municipality_name,
                    MAX(al.created_at) as last_access
                FROM users u
                JOIN audit_logs al ON u.id = al.user_id AND al.action = 'login'
                LEFT JOIN user_profiles up ON u.id = up.user_id AND up.active = true
                LEFT JOIN profiles p ON up.profile_id = p.id
                LEFT JOIN municipalities m ON up.municipality_id = m.id
                WHERE al.created_at >= CURRENT_DATE - INTERVAL '30 days'
                GROUP BY u.id, u.name, u.cpf, p.profile_name, m.name
                ORDER BY last_access DESC
                LIMIT 50
            ");
            $stmt->execute();
            $recent_access = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Usuários criados por mês (último ano)
            $stmt = $this->pdo->prepare("
                SELECT 
                    TO_CHAR(u.created_at, 'YYYY-MM') as month,
                    COUNT(*) as users_created
                FROM users u
                WHERE u.created_at >= CURRENT_DATE - INTERVAL '12 months'
                GROUP BY month
                ORDER BY month
            ");
            $stmt->execute();
            $creation_evolution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sessões ativas
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as active_sessions
                FROM user_sessions us
                WHERE us.updated_at >= NOW() - INTERVAL '4 hours'
            ");
            $stmt->execute();
            $active_sessions = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'report' => [
                    'general_statistics' => array_merge($general_stats, $active_sessions),
                    'by_profile' => $by_profile,
                    'by_municipality' => $by_municipality,
                    'recent_access' => $recent_access,
                    'creation_evolution' => $creation_evolution
                ],
                'filters_applied' => $filters,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de usuários: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de usuários'
            ];
        }
    }

    /**
     * Gera dashboard executivo com KPIs principais
     */
    public function generateExecutiveDashboard(): array
    {
        try {
            // KPIs gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM health_forms WHERE created_at >= CURRENT_DATE - INTERVAL '30 days') as forms_last_30_days,
                    (SELECT COUNT(*) FROM health_forms WHERE created_at >= CURRENT_DATE - INTERVAL '7 days') as forms_last_7_days,
                    (SELECT COUNT(*) FROM equipments WHERE status = 'available') as available_equipment,
                    (SELECT COUNT(*) FROM equipments WHERE status = 'delivered') as delivered_equipment,
                    (SELECT COUNT(*) FROM users WHERE active = true) as active_users,
                    (SELECT COUNT(*) FROM municipalities WHERE active = true) as active_municipalities,
                    (SELECT COUNT(*) FROM user_sessions WHERE updated_at >= NOW() - INTERVAL '1 hour') as users_online
            ");
            $stmt->execute();
            $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

            // Crescimento de fichas por semana (últimas 12 semanas)
            $stmt = $this->pdo->prepare("
                SELECT 
                    EXTRACT(week FROM created_at) as week_number,
                    TO_CHAR(created_at, 'YYYY-MM-DD') as week_start,
                    COUNT(*) as forms_count
                FROM health_forms 
                WHERE created_at >= CURRENT_DATE - INTERVAL '12 weeks'
                GROUP BY week_number, week_start
                ORDER BY week_start
            ");
            $stmt->execute();
            $forms_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Top 5 municípios por atividade
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.name,
                    COUNT(hf.id) as forms_count,
                    COUNT(DISTINCT e.id) as equipment_count,
                    COUNT(DISTINCT u.id) as users_count
                FROM municipalities m
                LEFT JOIN health_forms hf ON m.id = hf.municipality_id AND hf.created_at >= CURRENT_DATE - INTERVAL '30 days'
                LEFT JOIN equipments e ON m.id = e.municipality_id
                LEFT JOIN user_profiles up ON m.id = up.municipality_id AND up.active = true
                LEFT JOIN users u ON up.user_id = u.id AND u.active = true
                WHERE m.active = true
                GROUP BY m.id, m.name
                ORDER BY forms_count DESC
                LIMIT 5
            ");
            $stmt->execute();
            $top_municipalities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Alertas e notificações
            $alerts = [];

            // Equipamentos com garantia vencendo
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM equipments e
                WHERE e.warranty_months IS NOT NULL
                AND (e.purchase_date + INTERVAL '1 month' * e.warranty_months) BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'
                AND e.status NOT IN ('disposed', 'damaged')
            ");
            $stmt->execute();
            $warranty_expiring = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($warranty_expiring > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Garantias Vencendo',
                    'message' => "{$warranty_expiring} equipamentos com garantia vencendo em 30 dias",
                    'count' => $warranty_expiring
                ];
            }

            // Equipamentos em manutenção
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM equipments WHERE status = 'maintenance'");
            $stmt->execute();
            $maintenance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($maintenance_count > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Equipamentos em Manutenção',
                    'message' => "{$maintenance_count} equipamentos necessitam manutenção",
                    'count' => $maintenance_count
                ];
            }

            // Usuários inativos há mais de 30 dias
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT u.id) as count
                FROM users u
                LEFT JOIN audit_logs al ON u.id = al.user_id AND al.action = 'login'
                WHERE u.active = true
                AND (al.created_at IS NULL OR al.created_at < CURRENT_DATE - INTERVAL '30 days')
            ");
            $stmt->execute();
            $inactive_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($inactive_users > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Usuários Inativos',
                    'message' => "{$inactive_users} usuários não acessam há mais de 30 dias",
                    'count' => $inactive_users
                ];
            }

            return [
                'success' => true,
                'dashboard' => [
                    'kpis' => $kpis,
                    'forms_growth' => $forms_growth,
                    'top_municipalities' => $top_municipalities,
                    'alerts' => $alerts
                ],
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar dashboard executivo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar dashboard executivo'
            ];
        }
    }

    /**
     * Exporta relatório para CSV
     */
    public function exportToCSV(string $reportType, array $data, array $filters = []): array
    {
        try {
            $filename = $reportType . '_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = __DIR__ . '/../../storage/reports/' . $filename;

            // Criar diretório se não existir
            $directory = dirname($filepath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $file = fopen($filepath, 'w');
            if (!$file) {
                throw new Exception('Não foi possível criar o arquivo CSV');
            }

            // BOM para UTF-8
            fwrite($file, "\xEF\xBB\xBF");

            switch ($reportType) {
                case 'health_forms':
                    $this->writeHealthFormsCSV($file, $data);
                    break;
                case 'equipment':
                    $this->writeEquipmentCSV($file, $data);
                    break;
                case 'users':
                    $this->writeUsersCSV($file, $data);
                    break;
                default:
                    throw new Exception('Tipo de relatório não suportado para exportação');
            }

            fclose($file);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'filesize' => filesize($filepath)
            ];

        } catch (Exception $e) {
            error_log("Erro ao exportar CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Escreve dados de fichas de saúde no CSV
     */
    private function writeHealthFormsCSV($file, array $data): void
    {
        // Cabeçalho
        $headers = [
            'Município', 'Total de Fichas', 'Idade Média', 'Homens', 'Mulheres'
        ];
        fputcsv($file, $headers, ';');

        // Dados por município
        foreach ($data['by_municipality'] as $row) {
            fputcsv($file, [
                $row['municipality_name'],
                $row['forms_count'],
                number_format($row['avg_age'], 1),
                $row['male_count'],
                $row['female_count']
            ], ';');
        }
    }

    /**
     * Escreve dados de equipamentos no CSV
     */
    private function writeEquipmentCSV($file, array $data): void
    {
        // Cabeçalho
        $headers = [
            'Tipo', 'Total', 'Disponível', 'Entregue', 'Em Uso', 'Manutenção', 'Danificado', 'Valor Total'
        ];
        fputcsv($file, $headers, ';');

        // Dados por tipo
        foreach ($data['by_type'] as $row) {
            fputcsv($file, [
                $row['equipment_type'],
                $row['total_count'],
                $row['available'],
                $row['delivered'],
                $row['in_use'],
                $row['maintenance'],
                $row['damaged'],
                number_format($row['total_cost'], 2)
            ], ';');
        }
    }

    /**
     * Escreve dados de usuários no CSV
     */
    private function writeUsersCSV($file, array $data): void
    {
        // Cabeçalho
        $headers = [
            'Perfil', 'Descrição', 'Total Usuários', 'Usuários Ativos'
        ];
        fputcsv($file, $headers, ';');

        // Dados por perfil
        foreach ($data['by_profile'] as $row) {
            fputcsv($file, [
                $row['profile_name'],
                $row['description'],
                $row['users_count'],
                $row['active_users']
            ], ';');
        }
    }
}