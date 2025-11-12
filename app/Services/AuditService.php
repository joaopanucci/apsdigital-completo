<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Config\Database;
use PDO;
use Exception;

/**
 * Serviço de Auditoria
 * Gerencia logs de auditoria e rastreamento de ações do sistema
 */
class AuditService
{
    private $pdo;
    private $auditLogModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->auditLogModel = new AuditLog();
    }

    /**
     * Registra ação de auditoria
     */
    public function logAction(string $tableName, int $recordId, string $action, 
                             array $oldValues = [], array $newValues = [], 
                             ?int $userId = null): bool
    {
        try {
            // Se não foi fornecido usuário, tentar pegar da sessão
            if ($userId === null && isset($_SESSION['user']['user_id'])) {
                $userId = $_SESSION['user']['user_id'];
            }

            $logData = [
                'table_name' => $tableName,
                'record_id' => $recordId,
                'action' => $action,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($newValues),
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];

            return $this->auditLogModel->create($logData) !== false;

        } catch (Exception $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém histórico de auditoria com filtros
     */
    public function getAuditHistory(array $filters = [], int $page = 1, int $limit = 50): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            // Construir filtros
            if (!empty($filters['table_name'])) {
                $where[] = "al.table_name = ?";
                $params[] = $filters['table_name'];
            }

            if (!empty($filters['record_id'])) {
                $where[] = "al.record_id = ?";
                $params[] = $filters['record_id'];
            }

            if (!empty($filters['action'])) {
                $where[] = "al.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['user_id'])) {
                $where[] = "al.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "al.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "al.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['ip_address'])) {
                $where[] = "al.ip_address = ?";
                $params[] = $filters['ip_address'];
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            // Contar total
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM audit_logs al
                {$whereClause}
            ");
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Buscar dados
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.*,
                    u.name as user_name,
                    u.cpf as user_cpf
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar JSON dos valores
            foreach ($audits as &$audit) {
                $audit['old_values'] = json_decode($audit['old_values'], true) ?? [];
                $audit['new_values'] = json_decode($audit['new_values'], true) ?? [];
            }

            return [
                'success' => true,
                'data' => $audits,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'last_page' => ceil($totalCount / $limit)
                ]
            ];

        } catch (Exception $e) {
            error_log("Erro ao buscar histórico de auditoria: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao buscar histórico de auditoria'
            ];
        }
    }

    /**
     * Gera relatório de atividades por usuário
     */
    public function getUserActivityReport(int $userId, int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.action,
                    al.table_name,
                    COUNT(*) as action_count,
                    MAX(al.created_at) as last_action,
                    MIN(al.created_at) as first_action
                FROM audit_logs al
                WHERE al.user_id = ?
                AND al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY al.action, al.table_name
                ORDER BY action_count DESC
            ");
            $stmt->execute([$userId]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estatísticas gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT DATE(al.created_at)) as active_days,
                    COUNT(DISTINCT al.ip_address) as unique_ips,
                    MAX(al.created_at) as last_activity
                FROM audit_logs al
                WHERE al.user_id = ?
                AND al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Atividade por dia
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(al.created_at) as activity_date,
                    COUNT(*) as actions_count
                FROM audit_logs al
                WHERE al.user_id = ?
                AND al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY DATE(al.created_at)
                ORDER BY activity_date DESC
            ");
            $stmt->execute([$userId]);
            $daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'user_id' => $userId,
                'period_days' => $days,
                'statistics' => $stats,
                'activities' => $activities,
                'daily_activity' => $daily_activity
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de atividade do usuário: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de atividade'
            ];
        }
    }

    /**
     * Gera relatório de ações suspeitas
     */
    public function getSuspiciousActivityReport(int $days = 7): array
    {
        try {
            $suspiciousActivities = [];

            // 1. Múltiplos logins falhados do mesmo IP
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.ip_address,
                    COUNT(*) as failed_attempts,
                    COUNT(DISTINCT al.user_id) as affected_users,
                    MAX(al.created_at) as last_attempt
                FROM audit_logs al
                WHERE al.action = 'failed_login'
                AND al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY al.ip_address
                HAVING COUNT(*) >= 5
                ORDER BY failed_attempts DESC
            ");
            $stmt->execute();
            $suspiciousActivities['multiple_failed_logins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Ações em horários não comerciais (fora de 6h às 22h)
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.name as user_name,
                    u.cpf as user_cpf,
                    al.action,
                    al.table_name,
                    al.ip_address,
                    al.created_at
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                AND (EXTRACT(hour FROM al.created_at) < 6 OR EXTRACT(hour FROM al.created_at) > 22)
                AND al.action NOT IN ('login', 'logout', 'failed_login')
                ORDER BY al.created_at DESC
                LIMIT 100
            ");
            $stmt->execute();
            $suspiciousActivities['off_hours_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Usuários com atividade muito alta em pouco tempo
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.name as user_name,
                    u.cpf as user_cpf,
                    COUNT(*) as actions_count,
                    COUNT(DISTINCT al.table_name) as tables_affected,
                    MIN(al.created_at) as first_action,
                    MAX(al.created_at) as last_action
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY al.user_id, u.name, u.cpf
                HAVING COUNT(*) > 1000  -- Mais de 1000 ações no período
                ORDER BY actions_count DESC
            ");
            $stmt->execute();
            $suspiciousActivities['high_activity_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Alterações em massa de dados
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.name as user_name,
                    u.cpf as user_cpf,
                    al.table_name,
                    al.action,
                    COUNT(*) as records_affected,
                    MIN(al.created_at) as started_at,
                    MAX(al.created_at) as ended_at
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                AND al.action IN ('update', 'delete')
                GROUP BY al.user_id, u.name, u.cpf, al.table_name, al.action, 
                         DATE_TRUNC('hour', al.created_at)
                HAVING COUNT(*) > 50  -- Mais de 50 registros afetados por hora
                ORDER BY records_affected DESC
            ");
            $stmt->execute();
            $suspiciousActivities['mass_changes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 5. IPs suspeitos (muitos usuários diferentes)
            $stmt = $this->pdo->prepare("
                SELECT 
                    al.ip_address,
                    COUNT(DISTINCT al.user_id) as unique_users,
                    COUNT(*) as total_actions,
                    MIN(al.created_at) as first_seen,
                    MAX(al.created_at) as last_seen
                FROM audit_logs al
                WHERE al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY al.ip_address
                HAVING COUNT(DISTINCT al.user_id) > 10  -- Mais de 10 usuários diferentes
                ORDER BY unique_users DESC
            ");
            $stmt->execute();
            $suspiciousActivities['suspicious_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'period_days' => $days,
                'suspicious_activities' => $suspiciousActivities,
                'generated_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de atividades suspeitas: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar relatório de atividades suspeitas'
            ];
        }
    }

    /**
     * Obtém estatísticas de auditoria
     */
    public function getAuditStatistics(int $days = 30): array
    {
        try {
            // Estatísticas gerais
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT table_name) as affected_tables
                FROM audit_logs
                WHERE created_at >= CURRENT_DATE - INTERVAL '{$days} days'
            ");
            $stmt->execute();
            $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Por ação
            $stmt = $this->pdo->prepare("
                SELECT 
                    action,
                    COUNT(*) as count
                FROM audit_logs
                WHERE created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY action
                ORDER BY count DESC
            ");
            $stmt->execute();
            $by_action = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por tabela
            $stmt = $this->pdo->prepare("
                SELECT 
                    table_name,
                    COUNT(*) as count
                FROM audit_logs
                WHERE created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY table_name
                ORDER BY count DESC
            ");
            $stmt->execute();
            $by_table = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por usuário (top 10)
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.name as user_name,
                    u.cpf as user_cpf,
                    COUNT(al.*) as actions_count
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY al.user_id, u.name, u.cpf
                ORDER BY actions_count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Atividade por dia
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as activity_date,
                    COUNT(*) as logs_count
                FROM audit_logs
                WHERE created_at >= CURRENT_DATE - INTERVAL '{$days} days'
                GROUP BY DATE(created_at)
                ORDER BY activity_date
            ");
            $stmt->execute();
            $daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'period_days' => $days,
                'statistics' => [
                    'general' => $general_stats,
                    'by_action' => $by_action,
                    'by_table' => $by_table,
                    'top_users' => $top_users,
                    'daily_activity' => $daily_activity
                ]
            ];

        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas de auditoria: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao obter estatísticas de auditoria'
            ];
        }
    }

    /**
     * Limpa logs de auditoria antigos
     */
    public function cleanOldLogs(int $keepDays = 365): array
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM audit_logs 
                WHERE created_at < CURRENT_DATE - INTERVAL '{$keepDays} days'
            ");
            $stmt->execute();
            
            $deletedCount = $stmt->rowCount();

            return [
                'success' => true,
                'message' => "Removidos {$deletedCount} logs de auditoria antigos",
                'deleted_count' => $deletedCount
            ];

        } catch (Exception $e) {
            error_log("Erro ao limpar logs antigos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao limpar logs antigos'
            ];
        }
    }

    /**
     * Exporta logs de auditoria para análise
     */
    public function exportAuditLogs(array $filters = [], string $format = 'csv'): array
    {
        try {
            $where = [];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['table_name'])) {
                $where[] = "table_name = ?";
                $params[] = $filters['table_name'];
            }

            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            $stmt = $this->pdo->prepare("
                SELECT 
                    al.*,
                    u.name as user_name,
                    u.cpf as user_cpf
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                {$whereClause}
                ORDER BY al.created_at DESC
            ");
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($format === 'csv') {
                $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
                $filepath = __DIR__ . '/../../storage/exports/' . $filename;

                // Criar diretório se não existir
                $directory = dirname($filepath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $file = fopen($filepath, 'w');
                fwrite($file, "\xEF\xBB\xBF"); // BOM para UTF-8

                // Cabeçalhos
                $headers = [
                    'ID', 'Tabela', 'ID Registro', 'Ação', 'Usuário', 'CPF', 
                    'IP', 'User Agent', 'Data/Hora'
                ];
                fputcsv($file, $headers, ';');

                // Dados
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log['id'],
                        $log['table_name'],
                        $log['record_id'],
                        $log['action'],
                        $log['user_name'] ?? 'Sistema',
                        $log['user_cpf'] ?? '',
                        $log['ip_address'],
                        $log['user_agent'],
                        $log['created_at']
                    ], ';');
                }

                fclose($file);

                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'records_count' => count($logs),
                    'filesize' => filesize($filepath)
                ];
            }

            return [
                'success' => true,
                'data' => $logs,
                'records_count' => count($logs)
            ];

        } catch (Exception $e) {
            error_log("Erro ao exportar logs de auditoria: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao exportar logs de auditoria'
            ];
        }
    }

    /**
     * Verifica integridade dos logs de auditoria
     */
    public function verifyLogIntegrity(): array
    {
        try {
            $issues = [];

            // Verificar logs órfãos (usuários deletados)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.user_id IS NOT NULL AND u.id IS NULL
            ");
            $stmt->execute();
            $orphanLogs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($orphanLogs > 0) {
                $issues[] = "Encontrados {$orphanLogs} logs de auditoria órfãos (usuários deletados)";
            }

            // Verificar logs com dados JSON inválidos
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE (old_values != '' AND old_values !~ '^[\\s]*[\\[\\{].*[\\}\\]]\\s*$')
                OR (new_values != '' AND new_values !~ '^[\\s]*[\\[\\{].*[\\}\\]]\\s*$')
            ");
            $stmt->execute();
            $invalidJson = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($invalidJson > 0) {
                $issues[] = "Encontrados {$invalidJson} logs com dados JSON inválidos";
            }

            // Verificar logs muito antigos
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE created_at < CURRENT_DATE - INTERVAL '2 years'
            ");
            $stmt->execute();
            $oldLogs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($oldLogs > 0) {
                $issues[] = "Encontrados {$oldLogs} logs com mais de 2 anos (considerar arquivamento)";
            }

            return [
                'success' => true,
                'integrity_check' => [
                    'issues_found' => count($issues),
                    'issues' => $issues,
                    'orphan_logs' => $orphanLogs,
                    'invalid_json_logs' => $invalidJson,
                    'old_logs' => $oldLogs
                ],
                'checked_at' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            error_log("Erro ao verificar integridade dos logs: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao verificar integridade dos logs'
            ];
        }
    }
}