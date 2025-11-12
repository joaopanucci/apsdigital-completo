<?php

namespace App\Models;

use App\Config\Database;
use App\Helpers\Sanitizer;

/**
 * Model de Perfis de Usuário
 * 
 * @package App\Models
 * @author SES-MS
 * @version 2.0.0
 */
class UserProfile
{
    private int $id;
    private int $idPerfil;
    private string $perfil;
    private int $idUsuario;
    private ?string $ibge;
    private ?string $cnes;
    private ?string $ine;
    private ?int $microarea;
    private bool $ativo;
    private \DateTime $dtCriacao;

    /**
     * Encontra perfil por ID
     * 
     * @param int $id
     * @return self|null
     */
    public static function find(int $id): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_perfil_usuario WHERE id = ?",
            [$id]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Obtém perfis de um usuário
     * 
     * @param int $userId
     * @return array
     */
    public static function getByUserId(int $userId): array
    {
        $profiles = Database::fetchAll(
            "SELECT p.*, m.municipio 
             FROM tb_perfil_usuario p
             LEFT JOIN tb_dim_municipio m ON p.ibge = m.ibge
             WHERE p.id_usuario = ?
             ORDER BY p.id_perfil, p.dt_criacao",
            [$userId]
        );

        return array_map([self::class, 'fromArray'], $profiles);
    }

    /**
     * Obtém perfis ativos de um usuário
     * 
     * @param int $userId
     * @return array
     */
    public static function getActiveByUserId(int $userId): array
    {
        $profiles = Database::fetchAll(
            "SELECT p.*, m.municipio 
             FROM tb_perfil_usuario p
             LEFT JOIN tb_dim_municipio m ON p.ibge = m.ibge
             WHERE p.id_usuario = ? AND p.ativo = true
             ORDER BY p.id_perfil, p.dt_criacao",
            [$userId]
        );

        return array_map([self::class, 'fromArray'], $profiles);
    }

    /**
     * Verifica se usuário tem perfil específico
     * 
     * @param int $userId
     * @param int $profileId
     * @param string $ibge
     * @return bool
     */
    public static function hasProfile(int $userId, int $profileId, string $ibge = null): bool
    {
        $query = "SELECT id FROM tb_perfil_usuario WHERE id_usuario = ? AND id_perfil = ? AND ativo = true";
        $params = [$userId, $profileId];

        if ($ibge) {
            $query .= " AND ibge = ?";
            $params[] = $ibge;
        }

        $result = Database::fetch($query, $params);
        return $result !== null;
    }

    /**
     * Cria novo perfil de usuário
     * 
     * @param array $data
     * @return self
     * @throws \Exception
     */
    public static function create(array $data): self
    {
        $data = Sanitizer::form($data, [
            'ibge' => 'ibge',
            'cnes' => 'cnes',
            'ine' => 'string',
            'microarea' => 'integer'
        ]);

        // Obtém nome do perfil
        $profileNames = [
            1 => 'Administrador SES',
            2 => 'Gestor Regional',
            3 => 'Gestor Municipal',
            4 => 'Técnico Municipal',
            5 => 'Auditor'
        ];

        $perfilNome = $profileNames[$data['id_perfil']] ?? 'Desconhecido';

        Database::beginTransaction();
        try {
            $id = Database::query(
                "INSERT INTO tb_perfil_usuario (id_perfil, perfil, id_usuario, ibge, cnes, ine, microarea)
                 VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [
                    $data['id_perfil'],
                    $perfilNome,
                    $data['id_usuario'],
                    $data['ibge'] ?? null,
                    $data['cnes'] ?? null,
                    $data['ine'] ?? null,
                    $data['microarea'] ?? null
                ]
            )->fetch()['id'];

            Database::commit();

            return self::find($id);
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Atualiza perfil
     * 
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        $data = Sanitizer::form($data, [
            'ibge' => 'ibge',
            'cnes' => 'cnes',
            'ine' => 'string',
            'microarea' => 'integer'
        ]);

        $updated = Database::execute(
            "UPDATE tb_perfil_usuario 
             SET ibge = ?, cnes = ?, ine = ?, microarea = ?
             WHERE id = ?",
            [
                $data['ibge'] ?? $this->ibge,
                $data['cnes'] ?? $this->cnes,
                $data['ine'] ?? $this->ine,
                $data['microarea'] ?? $this->microarea,
                $this->id
            ]
        );

        if ($updated) {
            $this->ibge = $data['ibge'] ?? $this->ibge;
            $this->cnes = $data['cnes'] ?? $this->cnes;
            $this->ine = $data['ine'] ?? $this->ine;
            $this->microarea = $data['microarea'] ?? $this->microarea;
        }

        return $updated > 0;
    }

    /**
     * Ativa/desativa perfil
     * 
     * @param bool $ativo
     * @param int $autorizadoPor
     * @return bool
     */
    public function setAtivo(bool $ativo, int $autorizadoPor = null): bool
    {
        $fields = ['ativo = ?'];
        $params = [$ativo, $this->id];

        if ($ativo && $autorizadoPor) {
            $fields[] = 'autorizado_por = ?';
            $fields[] = 'dt_autorizacao = NOW()';
            array_splice($params, -1, 0, [$autorizadoPor]);
        }

        $updated = Database::execute(
            "UPDATE tb_perfil_usuario SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        if ($updated) {
            $this->ativo = $ativo;
        }

        return $updated > 0;
    }

    /**
     * Remove perfil
     * 
     * @return bool
     */
    public function delete(): bool
    {
        return Database::execute(
            "DELETE FROM tb_perfil_usuario WHERE id = ?",
            [$this->id]
        ) > 0;
    }

    /**
     * Obtém permissões do perfil
     * 
     * @return array
     */
    public function getPermissoes(): array
    {
        // Administrador SES tem todas as permissões
        if ($this->idPerfil === 1) {
            return range(1, 10);
        }

        $permissions = Database::fetchAll(
            "SELECT funcionalidade_id FROM tb_permissao 
             WHERE perfil_id = ? AND ativo = true",
            [$this->idPerfil]
        );

        return array_column($permissions, 'funcionalidade_id');
    }

    /**
     * Verifica se tem permissão específica
     * 
     * @param int $functionalityId
     * @return bool
     */
    public function hasPermission(int $functionalityId): bool
    {
        if ($this->idPerfil === 1) {
            return true; // Admin tem tudo
        }

        $permission = Database::fetch(
            "SELECT ativo FROM tb_permissao WHERE perfil_id = ? AND funcionalidade_id = ?",
            [$this->idPerfil, $functionalityId]
        );

        return $permission && $permission['ativo'];
    }

    /**
     * Obtém dados do município
     * 
     * @return array|null
     */
    public function getMunicipio(): ?array
    {
        if (!$this->ibge) {
            return null;
        }

        return Database::fetch(
            "SELECT * FROM tb_dim_municipio WHERE ibge = ?",
            [$this->ibge]
        );
    }

    /**
     * Lista perfis pendentes de autorização
     * 
     * @param array $filters
     * @return array
     */
    public static function getPendingAuthorization(array $filters = []): array
    {
        $where = ['p.ativo = false'];
        $params = [];

        if (!empty($filters['perfil_id'])) {
            $where[] = 'p.id_perfil = ?';
            $params[] = $filters['perfil_id'];
        }

        if (!empty($filters['municipio'])) {
            $where[] = 'p.ibge = ?';
            $params[] = $filters['municipio'];
        }

        $profiles = Database::fetchAll(
            "SELECT p.*, u.nome as usuario_nome, u.cpf, m.municipio
             FROM tb_perfil_usuario p
             JOIN tb_usuarios u ON p.id_usuario = u.id
             LEFT JOIN tb_dim_municipio m ON p.ibge = m.ibge
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.dt_criacao DESC",
            $params
        );

        return array_map([self::class, 'fromArray'], $profiles);
    }

    /**
     * Estatísticas de perfis
     * 
     * @return array
     */
    public static function getStats(): array
    {
        return Database::fetchAll(
            "SELECT 
                id_perfil,
                perfil,
                COUNT(*) as total,
                COUNT(CASE WHEN ativo = true THEN 1 END) as ativos,
                COUNT(CASE WHEN ativo = false THEN 1 END) as pendentes
             FROM tb_perfil_usuario
             GROUP BY id_perfil, perfil
             ORDER BY id_perfil"
        );
    }

    /**
     * Cria objeto UserProfile a partir de array
     * 
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $profile = new self();
        $profile->id = $data['id'];
        $profile->idPerfil = $data['id_perfil'];
        $profile->perfil = $data['perfil'];
        $profile->idUsuario = $data['id_usuario'];
        $profile->ibge = $data['ibge'];
        $profile->cnes = $data['cnes'];
        $profile->ine = $data['ine'];
        $profile->microarea = $data['microarea'];
        $profile->ativo = $data['ativo'];
        $profile->dtCriacao = new \DateTime($data['dt_criacao']);

        return $profile;
    }

    /**
     * Converte para array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_perfil' => $this->idPerfil,
            'perfil' => $this->perfil,
            'id_usuario' => $this->idUsuario,
            'ibge' => $this->ibge,
            'cnes' => $this->cnes,
            'ine' => $this->ine,
            'microarea' => $this->microarea,
            'ativo' => $this->ativo,
            'dt_criacao' => $this->dtCriacao->format('Y-m-d H:i:s'),
            'dt_criacao_formatted' => $this->dtCriacao->format('d/m/Y H:i')
        ];
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getIdPerfil(): int { return $this->idPerfil; }
    public function getPerfil(): string { return $this->perfil; }
    public function getIdUsuario(): int { return $this->idUsuario; }
    public function getIbge(): ?string { return $this->ibge; }
    public function getCnes(): ?string { return $this->cnes; }
    public function getIne(): ?string { return $this->ine; }
    public function getMicroarea(): ?int { return $this->microarea; }
    public function isAtivo(): bool { return $this->ativo; }
    public function getDtCriacao(): \DateTime { return $this->dtCriacao; }

    // Setters
    public function setIbge(?string $ibge): void { $this->ibge = $ibge; }
    public function setCnes(?string $cnes): void { $this->cnes = $cnes; }
    public function setIne(?string $ine): void { $this->ine = $ine; }
    public function setMicroarea(?int $microarea): void { $this->microarea = $microarea; }
}