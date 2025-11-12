<?php

namespace App\Models;

use App\Config\Database;
use App\Helpers\Sanitizer;

/**
 * Model de Formulário Saúde da Mulher
 * 
 * @package App\Models
 * @author SES-MS
 * @version 2.0.0
 */
class HealthForm
{
    private int $id;
    private string $municipio;
    private ?string $cnes;
    private \DateTime $competencia;
    private string $medicacao;
    private int $consumoMensal;
    private int $estoqueAtual;
    private ?string $lote;
    private ?\DateTime $dataVencimento;
    private ?string $observacoes;
    private int $preenchidoPor;
    private \DateTime $dtPreenchimento;

    /**
     * Encontra formulário por ID
     * 
     * @param int $id
     * @return self|null
     */
    public static function find(int $id): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_formulario_saudedamulher WHERE id = ?",
            [$id]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Lista formulários por município e competência
     * 
     * @param string $municipio
     * @param string $competencia
     * @return array
     */
    public static function getByMunicipalityAndPeriod(string $municipio, string $competencia): array
    {
        $forms = Database::fetchAll(
            "SELECT f.*, u.nome as preenchido_por_nome, m.municipio as municipio_nome
             FROM tb_formulario_saudedamulher f
             JOIN tb_usuarios u ON f.preenchido_por = u.id
             JOIN tb_dim_municipio m ON f.municipio = m.ibge
             WHERE f.municipio = ? AND f.competencia = ?
             ORDER BY f.medicacao",
            [$municipio, $competencia]
        );

        return array_map([self::class, 'fromArray'], $forms);
    }

    /**
     * Cria novo formulário
     * 
     * @param array $data
     * @return self
     */
    public static function create(array $data): self
    {
        $data = Sanitizer::form($data, [
            'municipio' => 'ibge',
            'cnes' => 'cnes',
            'medicacao' => 'string',
            'consumo_mensal' => 'integer',
            'estoque_atual' => 'integer',
            'lote' => 'string',
            'observacoes' => 'string'
        ]);

        Database::beginTransaction();
        try {
            $id = Database::query(
                "INSERT INTO tb_formulario_saudedamulher 
                 (municipio, cnes, competencia, medicacao, consumo_mensal, estoque_atual, 
                  lote, data_vencimento, observacoes, preenchido_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [
                    $data['municipio'],
                    $data['cnes'] ?? null,
                    $data['competencia'],
                    $data['medicacao'],
                    $data['consumo_mensal'],
                    $data['estoque_atual'],
                    $data['lote'] ?? null,
                    $data['data_vencimento'] ?? null,
                    $data['observacoes'] ?? null,
                    $data['preenchido_por']
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
     * Atualiza formulário
     * 
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        $data = Sanitizer::form($data, [
            'consumo_mensal' => 'integer',
            'estoque_atual' => 'integer',
            'lote' => 'string',
            'observacoes' => 'string'
        ]);

        $updated = Database::execute(
            "UPDATE tb_formulario_saudedamulher 
             SET consumo_mensal = ?, estoque_atual = ?, lote = ?, 
                 data_vencimento = ?, observacoes = ?, dt_atualizacao = NOW()
             WHERE id = ?",
            [
                $data['consumo_mensal'] ?? $this->consumoMensal,
                $data['estoque_atual'] ?? $this->estoqueAtual,
                $data['lote'] ?? $this->lote,
                $data['data_vencimento'] ?? $this->dataVencimento,
                $data['observacoes'] ?? $this->observacoes,
                $this->id
            ]
        );

        return $updated > 0;
    }

    /**
     * Remove formulário
     * 
     * @return bool
     */
    public function delete(): bool
    {
        return Database::execute(
            "DELETE FROM tb_formulario_saudedamulher WHERE id = ?",
            [$this->id]
        ) > 0;
    }

    /**
     * Relatório consolidado por competência
     * 
     * @param string $competencia
     * @param array $filters
     * @return array
     */
    public static function getConsolidatedReport(string $competencia, array $filters = []): array
    {
        $where = ['f.competencia = ?'];
        $params = [$competencia];

        if (!empty($filters['municipio'])) {
            $where[] = 'f.municipio = ?';
            $params[] = $filters['municipio'];
        }

        if (!empty($filters['medicacao'])) {
            $where[] = 'f.medicacao ILIKE ?';
            $params[] = '%' . $filters['medicacao'] . '%';
        }

        return Database::fetchAll(
            "SELECT f.medicacao, 
                    COUNT(f.id) as total_registros,
                    SUM(f.consumo_mensal) as total_consumo,
                    SUM(f.estoque_atual) as total_estoque,
                    COUNT(CASE WHEN f.data_vencimento < NOW() THEN 1 END) as medicamentos_vencidos,
                    COUNT(CASE WHEN f.data_vencimento BETWEEN NOW() AND NOW() + INTERVAL '90 days' THEN 1 END) as vencendo_90_dias
             FROM tb_formulario_saudedamulher f
             WHERE " . implode(' AND ', $where) . "
             GROUP BY f.medicacao
             ORDER BY f.medicacao",
            $params
        );
    }

    /**
     * Cria objeto HealthForm a partir de array
     * 
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $form = new self();
        $form->id = $data['id'];
        $form->municipio = $data['municipio'];
        $form->cnes = $data['cnes'];
        $form->competencia = new \DateTime($data['competencia']);
        $form->medicacao = $data['medicacao'];
        $form->consumoMensal = $data['consumo_mensal'];
        $form->estoqueAtual = $data['estoque_atual'];
        $form->lote = $data['lote'];
        $form->dataVencimento = $data['data_vencimento'] ? new \DateTime($data['data_vencimento']) : null;
        $form->observacoes = $data['observacoes'];
        $form->preenchidoPor = $data['preenchido_por'];
        $form->dtPreenchimento = new \DateTime($data['dt_preenchimento']);

        return $form;
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
            'municipio' => $this->municipio,
            'cnes' => $this->cnes,
            'competencia' => $this->competencia->format('Y-m-d'),
            'competencia_formatted' => $this->competencia->format('m/Y'),
            'medicacao' => $this->medicacao,
            'consumo_mensal' => $this->consumoMensal,
            'estoque_atual' => $this->estoqueAtual,
            'lote' => $this->lote,
            'data_vencimento' => $this->dataVencimento?->format('Y-m-d'),
            'data_vencimento_formatted' => $this->dataVencimento?->format('d/m/Y'),
            'observacoes' => $this->observacoes,
            'preenchido_por' => $this->preenchidoPor,
            'dt_preenchimento' => $this->dtPreenchimento->format('Y-m-d H:i:s'),
            'dt_preenchimento_formatted' => $this->dtPreenchimento->format('d/m/Y H:i')
        ];
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getMunicipio(): string { return $this->municipio; }
    public function getCnes(): ?string { return $this->cnes; }
    public function getCompetencia(): \DateTime { return $this->competencia; }
    public function getMedicacao(): string { return $this->medicacao; }
    public function getConsumoMensal(): int { return $this->consumoMensal; }
    public function getEstoqueAtual(): int { return $this->estoqueAtual; }
    public function getLote(): ?string { return $this->lote; }
    public function getDataVencimento(): ?\DateTime { return $this->dataVencimento; }
    public function getObservacoes(): ?string { return $this->observacoes; }
    public function getPreenchidoPor(): int { return $this->preenchidoPor; }
    public function getDtPreenchimento(): \DateTime { return $this->dtPreenchimento; }
}