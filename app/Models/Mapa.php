<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Mapa extends Model
{
    protected $table = 'regioes';
    protected $primaryKey = 'id';

    /**
     * Obtém dados de uma região específica
     */
    public function getRegiaoData($regiao)
    {
        $regiaoNome = str_replace('-', ' ', strtoupper($regiao));
        
        // Primeiro tentar buscar no banco de dados
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE UPPER(nome) = :nome LIMIT 1");
        $stmt->execute([':nome' => $regiaoNome]);
        $regiaoDb = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se não existir no banco, criar registro básico
        if (!$regiaoDb) {
            $this->criarRegistroRegiao($regiaoNome);
            $regiaoDb = [
                'nome' => $regiaoNome,
                'populacao' => null,
                'area_km2' => null,
                'densidade_demografica' => null,
                'municipios_count' => null
            ];
        }
        
        // Carregar dados do arquivo JSON
        $jsonPath = PUBLIC_PATH . '/assets/mapas/json/' . $regiaoNome . '.json';
        $geoData = null;
        
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            $geoData = json_decode($jsonContent, true);
        }
        
        return [
            'dados_db' => $regiaoDb,
            'geo_data' => $geoData,
            'tem_svg' => file_exists(PUBLIC_PATH . '/assets/mapas/svg/' . $regiaoNome . '.svg'),
            'tem_json' => $geoData !== null
        ];
    }

    /**
     * Lista todas as regiões do banco
     */
    public function getAllRegioes()
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um registro básico de região no banco
     */
    private function criarRegistroRegiao($nome)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (nome, created_at, updated_at) 
                VALUES (:nome, NOW(), NOW())
                ON CONFLICT (nome) DO NOTHING
            ");
            $stmt->execute([':nome' => $nome]);
        } catch (Exception $e) {
            // Silenciosamente falhar se a tabela não existir ainda
            error_log("Erro ao criar registro de região: " . $e->getMessage());
        }
    }

    /**
     * Atualiza dados estatísticos de uma região
     */
    public function updateEstatisticas($id, $dados)
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET populacao = :populacao,
                area_km2 = :area_km2,
                densidade_demografica = :densidade,
                municipios_count = :municipios,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':populacao' => $dados['populacao'] ?? null,
            ':area_km2' => $dados['area_km2'] ?? null,
            ':densidade' => $dados['densidade_demografica'] ?? null,
            ':municipios' => $dados['municipios_count'] ?? null,
            ':id' => $id
        ]);
    }

    /**
     * Busca regiões por termo
     */
    public function buscarRegioes($termo)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE LOWER(nome) LIKE LOWER(:termo)
            ORDER BY nome ASC
        ");
        $stmt->execute([':termo' => '%' . $termo . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém estatísticas gerais de todas as regiões
     */
    public function getEstatisticasGerais()
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_regioes,
                SUM(populacao) as populacao_total,
                SUM(area_km2) as area_total,
                AVG(densidade_demografica) as densidade_media
            FROM {$this->table}
            WHERE populacao IS NOT NULL
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se uma região existe no banco
     */
    public function regiaoExiste($nome)
    {
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE UPPER(nome) = UPPER(:nome) LIMIT 1");
        $stmt->execute([':nome' => $nome]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Cria as tabelas necessárias para o sistema de mapas
     */
    public function criarTabelas()
    {
        // Tabela de regiões
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS regioes (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(100) NOT NULL UNIQUE,
                codigo VARCHAR(20),
                populacao BIGINT,
                area_km2 DECIMAL(10,2),
                densidade_demografica DECIMAL(8,2),
                municipios_count INTEGER DEFAULT 0,
                descricao TEXT,
                ativo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela de municípios por região
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS municipios_regiao (
                id SERIAL PRIMARY KEY,
                regiao_id INTEGER REFERENCES regioes(id) ON DELETE CASCADE,
                codigo_ibge VARCHAR(10) NOT NULL,
                nome VARCHAR(100) NOT NULL,
                populacao BIGINT,
                area_km2 DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Índices para performance
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_regioes_nome ON regioes(nome)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_municipios_regiao_id ON municipios_regiao(regiao_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_municipios_codigo_ibge ON municipios_regiao(codigo_ibge)");

        return true;
    }
}