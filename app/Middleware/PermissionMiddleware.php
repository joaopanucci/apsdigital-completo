<?php

namespace App\Middleware;

use App\Config\Database;
use App\Middleware\AuthMiddleware;

/**
 * Middleware de controle de permissões
 * 
 * @package App\Middleware
 * @author SES-MS
 * @version 2.0.0
 */
class PermissionMiddleware
{
    /**
     * Verifica se usuário tem permissão para funcionalidade
     * 
     * @param int $functionalityId
     * @return bool
     */
    public static function check(int $functionalityId): bool
    {
        // Primeiro verifica se está autenticado
        if (!AuthMiddleware::handle()) {
            return false;
        }

        // Verifica se tem perfil ativo
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return false;
        }

        // Administrador SES tem acesso total
        if ($profile['id'] === 1) {
            return true;
        }

        // Verifica permissão específica no banco
        try {
            $permission = Database::fetch(
                "SELECT ativo FROM tb_permissao WHERE perfil_id = ? AND funcionalidade_id = ?",
                [$profile['id'], $functionalityId]
            );

            return $permission && $permission['ativo'];
        } catch (\Exception $e) {
            error_log("Erro ao verificar permissão: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica múltiplas permissões (OR - pelo menos uma)
     * 
     * @param array $functionalityIds
     * @return bool
     */
    public static function checkAny(array $functionalityIds): bool
    {
        foreach ($functionalityIds as $functionalityId) {
            if (self::check($functionalityId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica múltiplas permissões (AND - todas necessárias)
     * 
     * @param array $functionalityIds
     * @return bool
     */
    public static function checkAll(array $functionalityIds): bool
    {
        foreach ($functionalityIds as $functionalityId) {
            if (!self::check($functionalityId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verifica se pode acessar dados de um município específico
     * 
     * @param string $ibge
     * @return bool
     */
    public static function canAccessMunicipality(string $ibge): bool
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return false;
        }

        // Administrador SES e Gestor Regional podem acessar todos
        if (in_array($profile['id'], [1, 2])) {
            return true;
        }

        // Outros perfis só podem acessar seu próprio município
        return $profile['ibge'] === $ibge;
    }

    /**
     * Verifica se pode acessar dados de uma unidade específica
     * 
     * @param string $cnes
     * @return bool
     */
    public static function canAccessUnit(string $cnes): bool
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return false;
        }

        // Administrador SES, Gestor Regional e Municipal podem acessar todas do município
        if (in_array($profile['id'], [1, 2, 3])) {
            return self::canAccessMunicipality($this->getUnitMunicipality($cnes));
        }

        // Técnico só pode acessar sua própria unidade
        return $profile['cnes'] === $cnes;
    }

    /**
     * Obtém município de uma unidade pelo CNES
     * 
     * @param string $cnes
     * @return string|null
     */
    private static function getUnitMunicipality(string $cnes): ?string
    {
        try {
            $unit = Database::fetch(
                "SELECT ibge FROM tb_dim_municipio WHERE cnes = ?",
                [$cnes]
            );
            return $unit['ibge'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtém todas as permissões do perfil ativo
     * 
     * @return array
     */
    public static function getUserPermissions(): array
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return [];
        }

        // Administrador SES tem todas as permissões
        if ($profile['id'] === 1) {
            return range(1, 10); // Todas as funcionalidades
        }

        try {
            $permissions = Database::fetchAll(
                "SELECT funcionalidade_id FROM tb_permissao 
                 WHERE perfil_id = ? AND ativo = true",
                [$profile['id']]
            );

            return array_column($permissions, 'funcionalidade_id');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Verifica se pode modificar dados (não é auditor)
     * 
     * @return bool
     */
    public static function canModify(): bool
    {
        $profile = AuthMiddleware::getActiveProfile();
        return $profile && $profile['id'] !== 5; // Auditor só tem acesso de leitura
    }

    /**
     * Verifica se pode autorizar outros usuários
     * 
     * @return bool
     */
    public static function canAuthorizeUsers(): bool
    {
        return self::check(2); // Funcionalidade "Autorização de Usuários"
    }

    /**
     * Verifica se pode autorizar equipamentos
     * 
     * @return bool
     */
    public static function canAuthorizeEquipment(): bool
    {
        return self::check(4); // Funcionalidade "Autorização de Equipamentos"
    }

    /**
     * Verifica se pode acessar auditoria do sistema
     * 
     * @return bool
     */
    public static function canAccessAudit(): bool
    {
        return self::check(9); // Funcionalidade "Auditoria do Sistema"
    }

    /**
     * Obtém lista de municípios que o usuário pode acessar
     * 
     * @return array
     */
    public static function getAccessibleMunicipalities(): array
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return [];
        }

        try {
            // Administrador SES pode acessar todos
            if ($profile['id'] === 1) {
                return Database::fetchAll(
                    "SELECT ibge, municipio FROM tb_dim_municipio WHERE ativo = true ORDER BY municipio"
                );
            }

            // Gestor Regional pode acessar municípios de sua regional
            if ($profile['id'] === 2) {
                return Database::fetchAll(
                    "SELECT ibge, municipio FROM tb_dim_municipio 
                     WHERE regional = (SELECT regional FROM tb_dim_municipio WHERE ibge = ?) 
                     AND ativo = true ORDER BY municipio",
                    [$profile['ibge']]
                );
            }

            // Outros perfis só seu município
            return Database::fetchAll(
                "SELECT ibge, municipio FROM tb_dim_municipio 
                 WHERE ibge = ? AND ativo = true",
                [$profile['ibge']]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Middleware handler para rotas protegidas
     * 
     * @param string $permissions Permissões necessárias separadas por vírgula
     * @return bool
     */
    public static function handle(string $permissions): bool
    {
        if (!AuthMiddleware::requireProfile()) {
            return false;
        }

        $requiredPermissions = explode(',', $permissions);
        
        // Se tem múltiplas permissões, verifica se tem pelo menos uma
        if (count($requiredPermissions) > 1) {
            return self::checkAny(array_map('intval', $requiredPermissions));
        }

        // Permissão única
        return self::check((int)$permissions);
    }

    /**
     * Obtém níveis de acesso hierárquico
     * 
     * @return array
     */
    public static function getAccessLevels(): array
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return [];
        }

        $levels = [
            1 => 'Nacional', // Administrador SES
            2 => 'Regional', // Gestor Regional  
            3 => 'Municipal', // Gestor Municipal
            4 => 'Unidade', // Técnico Municipal
            5 => 'Auditoria' // Auditor
        ];

        return [
            'current_level' => $levels[$profile['id']] ?? 'Desconhecido',
            'profile_id' => $profile['id'],
            'can_modify' => self::canModify(),
            'permissions' => self::getUserPermissions()
        ];
    }

    /**
     * Filtra dados baseado nas permissões do usuário
     * 
     * @param array $data
     * @param string $municipalityField Campo que contém o IBGE do município
     * @return array
     */
    public static function filterDataByPermissions(array $data, string $municipalityField = 'ibge'): array
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return [];
        }

        // Administrador SES vê tudo
        if ($profile['id'] === 1) {
            return $data;
        }

        // Filtra baseado nos municípios acessíveis
        $accessibleMunicipalities = array_column(self::getAccessibleMunicipalities(), 'ibge');
        
        return array_filter($data, function($item) use ($municipalityField, $accessibleMunicipalities) {
            return in_array($item[$municipalityField] ?? '', $accessibleMunicipalities);
        });
    }

    /**
     * Gera cláusula WHERE SQL baseada nas permissões
     * 
     * @param string $municipalityColumn Nome da coluna do município na query
     * @return array ['where' => string, 'params' => array]
     */
    public static function getSQLFilter(string $municipalityColumn = 'ibge'): array
    {
        $profile = AuthMiddleware::getActiveProfile();
        if (!$profile) {
            return ['where' => '1 = 0', 'params' => []]; // Bloqueia tudo
        }

        // Administrador SES não tem filtro
        if ($profile['id'] === 1) {
            return ['where' => '1 = 1', 'params' => []];
        }

        $accessibleMunicipalities = array_column(self::getAccessibleMunicipalities(), 'ibge');
        
        if (empty($accessibleMunicipalities)) {
            return ['where' => '1 = 0', 'params' => []];
        }

        $placeholders = implode(',', array_fill(0, count($accessibleMunicipalities), '?'));
        
        return [
            'where' => "{$municipalityColumn} IN ({$placeholders})",
            'params' => $accessibleMunicipalities
        ];
    }

    /**
     * Middleware para páginas administrativas (apenas admin)
     * 
     * @return bool
     */
    public static function requireAdmin(): bool
    {
        $profile = AuthMiddleware::getActiveProfile();
        return $profile && $profile['id'] === 1;
    }

    /**
     * Middleware para gestores (regional ou municipal)
     * 
     * @return bool
     */
    public static function requireManager(): bool
    {
        $profile = AuthMiddleware::getActiveProfile();
        return $profile && in_array($profile['id'], [1, 2, 3]);
    }

    /**
     * Redireciona se não tiver permissão
     * 
     * @param int $functionalityId
     */
    public static function redirectIfNoPermission(int $functionalityId): void
    {
        if (!self::check($functionalityId)) {
            header('HTTP/1.0 403 Forbidden');
            header('Location: /dashboard?error=access_denied');
            exit;
        }
    }
}