<?php

namespace App\Models;

use App\Config\Database;
use App\Helpers\Sanitizer;

/**
 * Model de Equipamentos
 * 
 * @package App\Models
 * @author SES-MS
 * @version 2.0.0
 */
class Equipment
{
    private int $id;
    private string $imei;
    private ?string $marca;
    private ?string $modelo;
    private ?string $numeroSerie;
    private ?string $caixa;
    private ?string $lote;
    private bool $ativo;
    private \DateTime $dtCadastro;

    /**
     * Encontra tablet por ID
     * 
     * @param int $id
     * @return self|null
     */
    public static function findTablet(int $id): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_dim_tablet WHERE id = ?",
            [$id]
        );

        return $data ? self::fromTabletArray($data) : null;
    }

    /**
     * Encontra tablet por IMEI
     * 
     * @param string $imei
     * @return self|null
     */
    public static function findTabletByIMEI(string $imei): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_dim_tablet WHERE imei = ?",
            [$imei]
        );

        return $data ? self::fromTabletArray($data) : null;
    }

    /**
     * Lista tablets com paginação
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public static function paginateTablets(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $where = self::buildTabletWhereClause($filters);
        
        // Total de registros
        $total = Database::fetch(
            "SELECT COUNT(*) as count FROM tb_dim_tablet WHERE {$where['clause']}",
            $where['params']
        )['count'];

        // Dados da página
        $tablets = Database::fetchAll(
            "SELECT t.*, 
                    CASE WHEN e.id IS NOT NULL THEN 'Entregue' ELSE 'Disponível' END as status_entrega,
                    e.ibge as municipio_entrega,
                    m.municipio as nome_municipio
             FROM tb_dim_tablet t
             LEFT JOIN tb_geral_entregue e ON t.id = e.id_tablet AND e.ativo = true
             LEFT JOIN tb_dim_municipio m ON e.ibge = m.ibge
             WHERE {$where['clause']}
             ORDER BY t.dt_cadastro DESC
             LIMIT ? OFFSET ?",
            array_merge($where['params'], [$perPage, $offset])
        );

        return [
            'data' => $tablets,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Constrói cláusula WHERE para tablets
     * 
     * @param array $filters
     * @return array
     */
    private static function buildTabletWhereClause(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = "(imei LIKE ? OR marca ILIKE ? OR modelo ILIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }

        if (isset($filters['ativo'])) {
            $conditions[] = "ativo = ?";
            $params[] = (bool)$filters['ativo'];
        }

        if (!empty($filters['caixa'])) {
            $conditions[] = "caixa = ?";
            $params[] = $filters['caixa'];
        }

        if (!empty($filters['marca'])) {
            $conditions[] = "marca ILIKE ?";
            $params[] = '%' . $filters['marca'] . '%';
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'disponivel') {
                $conditions[] = "id NOT IN (SELECT id_tablet FROM tb_geral_entregue WHERE ativo = true)";
            } elseif ($filters['status'] === 'entregue') {
                $conditions[] = "id IN (SELECT id_tablet FROM tb_geral_entregue WHERE ativo = true)";
            }
        }

        return [
            'clause' => implode(' AND ', $conditions),
            'params' => $params
        ];
    }

    /**
     * Cria novo tablet
     * 
     * @param array $data
     * @return self
     * @throws \Exception
     */
    public static function createTablet(array $data): self
    {
        $data = Sanitizer::form($data, [
            'imei' => 'string',
            'marca' => 'string',
            'modelo' => 'string',
            'numero_serie' => 'string',
            'caixa' => 'string',
            'lote' => 'string'
        ]);

        Database::beginTransaction();
        try {
            $id = Database::query(
                "INSERT INTO tb_dim_tablet (imei, marca, modelo, numero_serie, caixa, lote, cadastrado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [
                    $data['imei'],
                    $data['marca'] ?? null,
                    $data['modelo'] ?? null,
                    $data['numero_serie'] ?? null,
                    $data['caixa'] ?? null,
                    $data['lote'] ?? null,
                    $data['cadastrado_por'] ?? null
                ]
            )->fetch()['id'];

            Database::commit();

            return self::findTablet($id);
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Lista chips/ICCID
     * 
     * @param array $filters
     * @return array
     */
    public static function getChips(array $filters = []): array
    {
        $where = self::buildChipWhereClause($filters);
        
        return Database::fetchAll(
            "SELECT i.*, 
                    CASE WHEN e.id IS NOT NULL THEN 'Entregue' ELSE 'Disponível' END as status_entrega,
                    e.ibge as municipio_entrega,
                    m.municipio as nome_municipio
             FROM tb_dim_iccid i
             LEFT JOIN tb_geral_entregue e ON i.id = e.id_iccid AND e.ativo = true
             LEFT JOIN tb_dim_municipio m ON e.ibge = m.ibge
             WHERE {$where['clause']}
             ORDER BY i.dt_cadastro DESC",
            $where['params']
        );
    }

    /**
     * Constrói cláusula WHERE para chips
     * 
     * @param array $filters
     * @return array
     */
    private static function buildChipWhereClause(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = "(iccid LIKE ? OR operadora ILIKE ? OR numero_linha LIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }

        if (isset($filters['ativo'])) {
            $conditions[] = "ativo = ?";
            $params[] = (bool)$filters['ativo'];
        }

        if (!empty($filters['operadora'])) {
            $conditions[] = "operadora ILIKE ?";
            $params[] = '%' . $filters['operadora'] . '%';
        }

        return [
            'clause' => implode(' AND ', $conditions),
            'params' => $params
        ];
    }

    /**
     * Cria novo chip
     * 
     * @param array $data
     * @return array
     */
    public static function createChip(array $data): array
    {
        $data = Sanitizer::form($data, [
            'iccid' => 'string',
            'operadora' => 'string',
            'numero_linha' => 'phone',
            'caixa' => 'string',
            'lote' => 'string'
        ]);

        $id = Database::query(
            "INSERT INTO tb_dim_iccid (iccid, operadora, numero_linha, caixa, lote, cadastrado_por)
             VALUES (?, ?, ?, ?, ?, ?) RETURNING id",
            [
                $data['iccid'],
                $data['operadora'] ?? null,
                $data['numero_linha'] ?? null,
                $data['caixa'] ?? null,
                $data['lote'] ?? null,
                $data['cadastrado_por'] ?? null
            ]
        )->fetch();

        return Database::fetch("SELECT * FROM tb_dim_iccid WHERE id = ?", [$id['id']]);
    }

    /**
     * Registra entrega de equipamento
     * 
     * @param array $data
     * @return array
     */
    public static function registerDelivery(array $data): array
    {
        $data = Sanitizer::form($data, [
            'ibge' => 'ibge',
            'cnes' => 'cnes',
            'profissional_destino' => 'name',
            'cpf_profissional' => 'cpf'
        ]);

        Database::beginTransaction();
        try {
            $id = Database::query(
                "INSERT INTO tb_geral_entregue 
                 (id_tablet, id_iccid, ibge, cnes, profissional_destino, cpf_profissional, 
                  dt_entrega, autorizado_por, observacoes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [
                    $data['id_tablet'] ?? null,
                    $data['id_iccid'] ?? null,
                    $data['ibge'],
                    $data['cnes'] ?? null,
                    $data['profissional_destino'],
                    $data['cpf_profissional'],
                    $data['dt_entrega'] ?? date('Y-m-d'),
                    $data['autorizado_por'] ?? null,
                    $data['observacoes'] ?? null
                ]
            )->fetch()['id'];

            Database::commit();

            return Database::fetch(
                "SELECT e.*, m.municipio, t.imei, i.iccid
                 FROM tb_geral_entregue e
                 LEFT JOIN tb_dim_municipio m ON e.ibge = m.ibge
                 LEFT JOIN tb_dim_tablet t ON e.id_tablet = t.id
                 LEFT JOIN tb_dim_iccid i ON e.id_iccid = i.id
                 WHERE e.id = ?",
                [$id]
            );
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Lista entregas de equipamentos
     * 
     * @param array $filters
     * @return array
     */
    public static function getDeliveries(array $filters = []): array
    {
        $where = self::buildDeliveryWhereClause($filters);
        
        return Database::fetchAll(
            "SELECT e.*, m.municipio, t.imei, t.marca, t.modelo, i.iccid, i.operadora
             FROM tb_geral_entregue e
             LEFT JOIN tb_dim_municipio m ON e.ibge = m.ibge
             LEFT JOIN tb_dim_tablet t ON e.id_tablet = t.id
             LEFT JOIN tb_dim_iccid i ON e.id_iccid = i.id
             WHERE {$where['clause']}
             ORDER BY e.dt_entrega DESC",
            $where['params']
        );
    }

    /**
     * Constrói cláusula WHERE para entregas
     * 
     * @param array $filters
     * @return array
     */
    private static function buildDeliveryWhereClause(array $filters): array
    {
        $conditions = ['e.ativo = true'];
        $params = [];

        if (!empty($filters['municipio'])) {
            $conditions[] = "e.ibge = ?";
            $params[] = $filters['municipio'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = "e.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = "e.dt_entrega >= ?";
            $params[] = $filters['data_inicio'];
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = "e.dt_entrega <= ?";
            $params[] = $filters['data_fim'];
        }

        return [
            'clause' => implode(' AND ', $conditions),
            'params' => $params
        ];
    }

    /**
     * Atualiza status de equipamento entregue
     * 
     * @param int $deliveryId
     * @param string $status
     * @param array $data
     * @return bool
     */
    public static function updateDeliveryStatus(int $deliveryId, string $status, array $data = []): bool
    {
        $fields = ['status = ?'];
        $params = [$status];

        if ($status === 'Quebrado') {
            $fields[] = 'quebra = true';
            if (!empty($data['dt_ocorrencia'])) {
                $fields[] = 'dt_ocorrencia = ?';
                $params[] = $data['dt_ocorrencia'];
            }
        } elseif ($status === 'Roubado/Furtado') {
            $fields[] = 'roubo_furto = true';
            if (!empty($data['dt_ocorrencia'])) {
                $fields[] = 'dt_ocorrencia = ?';
                $params[] = $data['dt_ocorrencia'];
            }
            if (!empty($data['boletim_ocorrencia'])) {
                $fields[] = 'boletim_ocorrencia = ?';
                $params[] = $data['boletim_ocorrencia'];
            }
        }

        if (!empty($data['observacoes'])) {
            $fields[] = 'observacoes = ?';
            $params[] = $data['observacoes'];
        }

        $params[] = $deliveryId;

        return Database::execute(
            "UPDATE tb_geral_entregue SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        ) > 0;
    }

    /**
     * Estatísticas de equipamentos
     * 
     * @return array
     */
    public static function getStats(): array
    {
        $stats = [];

        // Tablets
        $tabletStats = Database::fetch(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN ativo = true THEN 1 END) as ativos,
                COUNT(CASE WHEN id IN (SELECT id_tablet FROM tb_geral_entregue WHERE ativo = true) THEN 1 END) as entregues
             FROM tb_dim_tablet"
        );

        // Chips
        $chipStats = Database::fetch(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN ativo = true THEN 1 END) as ativos,
                COUNT(CASE WHEN id IN (SELECT id_iccid FROM tb_geral_entregue WHERE ativo = true) THEN 1 END) as entregues
             FROM tb_dim_iccid"
        );

        // Status das entregas
        $deliveryStats = Database::fetchAll(
            "SELECT status, COUNT(*) as quantidade
             FROM tb_geral_entregue 
             WHERE ativo = true
             GROUP BY status"
        );

        return [
            'tablets' => $tabletStats,
            'chips' => $chipStats,
            'entregas' => array_column($deliveryStats, 'quantidade', 'status')
        ];
    }

    /**
     * Cria objeto Equipment a partir de array de tablet
     * 
     * @param array $data
     * @return self
     */
    public static function fromTabletArray(array $data): self
    {
        $equipment = new self();
        $equipment->id = $data['id'];
        $equipment->imei = $data['imei'];
        $equipment->marca = $data['marca'];
        $equipment->modelo = $data['modelo'];
        $equipment->numeroSerie = $data['numero_serie'];
        $equipment->caixa = $data['caixa'];
        $equipment->lote = $data['lote'];
        $equipment->ativo = $data['ativo'];
        $equipment->dtCadastro = new \DateTime($data['dt_cadastro']);

        return $equipment;
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getImei(): string { return $this->imei; }
    public function getMarca(): ?string { return $this->marca; }
    public function getModelo(): ?string { return $this->modelo; }
    public function getNumeroSerie(): ?string { return $this->numeroSerie; }
    public function getCaixa(): ?string { return $this->caixa; }
    public function getLote(): ?string { return $this->lote; }
    public function isAtivo(): bool { return $this->ativo; }
    public function getDtCadastro(): \DateTime { return $this->dtCadastro; }
}