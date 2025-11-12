<?php

namespace App\Models;

use App\Config\Database;
use App\Helpers\Sanitizer;

/**
 * Model de Municípios
 * 
 * @package App\Models
 * @author SES-MS
 * @version 2.0.0
 */
class Municipality
{
    private int $id;
    private string $ibge;
    private string $municipio;
    private ?string $unidade;
    private ?string $cnes;
    private ?string $regional;
    private bool $ativo;
    private \DateTime $dtCadastro;

    /**
     * Encontra município por IBGE
     * 
     * @param string $ibge
     * @return self|null
     */
    public static function findByIBGE(string $ibge): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_dim_municipio WHERE ibge = ?",
            [$ibge]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Lista todos os municípios ativos
     * 
     * @return array
     */
    public static function getActive(): array
    {
        $municipalities = Database::fetchAll(
            "SELECT * FROM tb_dim_municipio WHERE ativo = true ORDER BY municipio"
        );

        return array_map([self::class, 'fromArray'], $municipalities);
    }

    /**
     * Lista municípios por regional
     * 
     * @param string $regional
     * @return array
     */
    public static function getByRegional(string $regional): array
    {
        $municipalities = Database::fetchAll(
            "SELECT * FROM tb_dim_municipio WHERE regional = ? AND ativo = true ORDER BY municipio",
            [$regional]
        );

        return array_map([self::class, 'fromArray'], $municipalities);
    }

    /**
     * Cria novo município
     * 
     * @param array $data
     * @return self
     */
    public static function create(array $data): self
    {
        $data = Sanitizer::form($data, [
            'ibge' => 'ibge',
            'municipio' => 'name',
            'unidade' => 'string',
            'cnes' => 'cnes',
            'regional' => 'string'
        ]);

        $id = Database::query(
            "INSERT INTO tb_dim_municipio (ibge, municipio, unidade, cnes, regional)
             VALUES (?, ?, ?, ?, ?) RETURNING id",
            [
                $data['ibge'],
                $data['municipio'],
                $data['unidade'] ?? null,
                $data['cnes'] ?? null,
                $data['regional'] ?? null
            ]
        )->fetch()['id'];

        return Database::fetch("SELECT * FROM tb_dim_municipio WHERE id = ?", [$id]);
    }

    /**
     * Converte array para objeto
     * 
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $municipality = new self();
        $municipality->id = $data['id'];
        $municipality->ibge = $data['ibge'];
        $municipality->municipio = $data['municipio'];
        $municipality->unidade = $data['unidade'];
        $municipality->cnes = $data['cnes'];
        $municipality->regional = $data['regional'];
        $municipality->ativo = $data['ativo'];
        $municipality->dtCadastro = new \DateTime($data['dt_cadastro']);

        return $municipality;
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
            'ibge' => $this->ibge,
            'municipio' => $this->municipio,
            'unidade' => $this->unidade,
            'cnes' => $this->cnes,
            'regional' => $this->regional,
            'ativo' => $this->ativo,
            'dt_cadastro' => $this->dtCadastro->format('Y-m-d H:i:s')
        ];
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getIbge(): string { return $this->ibge; }
    public function getMunicipio(): string { return $this->municipio; }
    public function getUnidade(): ?string { return $this->unidade; }
    public function getCnes(): ?string { return $this->cnes; }
    public function getRegional(): ?string { return $this->regional; }
    public function isAtivo(): bool { return $this->ativo; }
    public function getDtCadastro(): \DateTime { return $this->dtCadastro; }
}

/**
 * Model de Logs de Auditoria
 */
class AuditLog
{
    /**
     * Registra log de auditoria
     * 
     * @param int $userId
     * @param string $acao
     * @param array $data
     * @return bool
     */
    public static function log(int $userId, string $acao, array $data = []): bool
    {
        try {
            Database::execute(
                "INSERT INTO tb_auditoria_sistema 
                 (id_usuario, acao, tabela_afetada, registro_id, dados_novos, ip_usuario, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $acao,
                    $data['tabela'] ?? null,
                    $data['registro_id'] ?? null,
                    json_encode($data['dados'] ?? []),
                    \App\Helpers\Security::getClientIP(),
                    \App\Helpers\Security::getUserAgent()
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista logs de auditoria
     * 
     * @param array $filters
     * @return array
     */
    public static function getLogs(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['usuario_id'])) {
            $where[] = 'a.id_usuario = ?';
            $params[] = $filters['usuario_id'];
        }

        if (!empty($filters['acao'])) {
            $where[] = 'a.acao ILIKE ?';
            $params[] = '%' . $filters['acao'] . '%';
        }

        if (!empty($filters['data_inicio'])) {
            $where[] = 'a.dt_acao >= ?';
            $params[] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $where[] = 'a.dt_acao <= ?';
            $params[] = $filters['data_fim'] . ' 23:59:59';
        }

        return Database::fetchAll(
            "SELECT a.*, u.nome as usuario_nome, u.cpf
             FROM tb_auditoria_sistema a
             LEFT JOIN tb_usuarios u ON a.id_usuario = u.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.dt_acao DESC
             LIMIT 1000",
            $params
        );
    }
}