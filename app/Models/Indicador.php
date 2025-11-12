<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Indicador extends Model
{
    protected $table = 'indicadores';
    protected $primaryKey = 'id';

    /**
     * Obtém todas as categorias de indicadores com seus tipos
     */
    public function getCategorias()
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                JSON_AGG(
                    JSON_BUILD_OBJECT(
                        'id', t.id,
                        'nome', t.nome,
                        'slug', t.slug,
                        'descricao', t.descricao,
                        'unidade', t.unidade,
                        'ativo', t.ativo
                    ) ORDER BY t.nome
                ) FILTER (WHERE t.id IS NOT NULL) as tipos
            FROM categorias_indicadores c
            LEFT JOIN tipos_indicadores t ON c.id = t.categoria_id AND t.ativo = true
            WHERE c.ativo = true
            GROUP BY c.id, c.nome, c.slug, c.descricao, c.ativo, c.created_at
            ORDER BY c.nome
        ");
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON dos tipos
        foreach ($categorias as &$categoria) {
            $categoria['tipos'] = json_decode($categoria['tipos'] ?? '[]', true) ?: [];
        }
        
        return $categorias;
    }

    /**
     * Obtém tipos de indicadores por categoria
     */
    public function getTiposPorCategoria($categoriaId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tipos_indicadores 
            WHERE categoria_id = :categoria_id AND ativo = true
            ORDER BY nome
        ");
        $stmt->execute([':categoria_id' => $categoriaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém dados de indicadores por região com agregações
     */
    public function getDadosPorRegiao($filtros)
    {
        $sql = "
            SELECT 
                i.*,
                m.id as municipio_id,
                m.ibge,
                m.nome as municipio_nome,
                m.uf,
                m.regiao,
                t.nome as tipo_nome,
                t.unidade
            FROM {$this->table} i
            JOIN municipios m ON i.municipio_id = m.id
            JOIN tipos_indicadores t ON i.tipo_indicador_id = t.id
            WHERE i.tipo_indicador_id = :tipo_indicador_id
        ";
        
        $params = [':tipo_indicador_id' => $filtros['tipo_indicador_id']];
        
        if (!empty($filtros['regiao'])) {
            $sql .= " AND m.regiao = :regiao";
            $params[':regiao'] = $filtros['regiao'];
        }
        
        if (!empty($filtros['competencia'])) {
            $sql .= " AND i.competencia = :competencia";
            $params[':competencia'] = $filtros['competencia'];
        }
        
        $sql .= " ORDER BY m.nome, i.competencia";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar quadrimestre se especificado
        if (!empty($filtros['quadrimestre']) && empty($filtros['competencia'])) {
            $indicadores = $this->filtrarPorQuadrimestre($indicadores, $filtros['quadrimestre']);
        }
        
        // Agrupar por município e agregar dados
        return $this->agruparPorMunicipio($indicadores);
    }

    /**
     * Obtém dados de um município específico
     */
    public function getDadosMunicipio($filtros)
    {
        $sql = "
            SELECT 
                i.*,
                m.nome as municipio_nome,
                m.ibge,
                m.uf,
                m.regiao,
                t.nome as tipo_nome,
                t.unidade
            FROM {$this->table} i
            JOIN municipios m ON i.municipio_id = m.id
            JOIN tipos_indicadores t ON i.tipo_indicador_id = t.id
            WHERE i.municipio_id = :municipio_id 
            AND i.tipo_indicador_id = :tipo_indicador_id
        ";
        
        $params = [
            ':municipio_id' => $filtros['municipio_id'],
            ':tipo_indicador_id' => $filtros['tipo_indicador_id']
        ];
        
        if (!empty($filtros['competencia'])) {
            $sql .= " AND i.competencia = :competencia";
            $params[':competencia'] = $filtros['competencia'];
        }
        
        $sql .= " ORDER BY i.competencia DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar quadrimestre se especificado
        if (!empty($filtros['quadrimestre']) && empty($filtros['competencia'])) {
            $indicadores = $this->filtrarPorQuadrimestre($indicadores, $filtros['quadrimestre']);
        }
        
        // Decodificar dados JSON
        foreach ($indicadores as &$indicador) {
            $indicador['dados'] = json_decode($indicador['dados'] ?? '{}', true);
        }
        
        return $indicadores;
    }

    /**
     * Gera estatísticas gerais dos indicadores
     */
    public function getEstatisticasGerais()
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_registros,
                COUNT(DISTINCT municipio_id) as municipios_com_dados,
                COUNT(DISTINCT tipo_indicador_id) as tipos_indicadores_utilizados,
                AVG(pontuacao) as pontuacao_media,
                MIN(pontuacao) as pontuacao_minima,
                MAX(pontuacao) as pontuacao_maxima,
                COUNT(DISTINCT competencia) as competencias_registradas
            FROM {$this->table}
            WHERE pontuacao IS NOT NULL
        ");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Estatísticas por região
        $stmt = $this->db->prepare("
            SELECT 
                m.regiao,
                COUNT(*) as total_registros,
                COUNT(DISTINCT i.municipio_id) as municipios_com_dados,
                AVG(i.pontuacao) as pontuacao_media
            FROM {$this->table} i
            JOIN municipios m ON i.municipio_id = m.id
            WHERE i.pontuacao IS NOT NULL
            GROUP BY m.regiao
            ORDER BY pontuacao_media DESC
        ");
        $stmt->execute();
        $statsPorRegiao = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'geral' => $stats,
            'por_regiao' => $statsPorRegiao
        ];
    }

    /**
     * Gera relatório de indicadores
     */
    public function gerarRelatorio($filtros)
    {
        $sql = "
            SELECT 
                m.regiao,
                m.nome as municipio,
                t.nome as tipo_indicador,
                i.competencia,
                i.pontuacao,
                i.estabelecimento,
                i.dados
            FROM {$this->table} i
            JOIN municipios m ON i.municipio_id = m.id
            JOIN tipos_indicadores t ON i.tipo_indicador_id = t.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filtros['tipo_indicador_id'])) {
            $sql .= " AND i.tipo_indicador_id = :tipo_indicador_id";
            $params[':tipo_indicador_id'] = $filtros['tipo_indicador_id'];
        }
        
        if (!empty($filtros['regiao'])) {
            $sql .= " AND m.regiao = :regiao";
            $params[':regiao'] = $filtros['regiao'];
        }
        
        if (!empty($filtros['periodo'])) {
            $sql .= " AND i.competencia LIKE :periodo";
            $params[':periodo'] = $filtros['periodo'] . '%';
        }
        
        $sql .= " ORDER BY m.regiao, m.nome, i.competencia";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar dados JSON
        foreach ($dados as &$item) {
            $item['dados'] = json_decode($item['dados'] ?? '{}', true);
        }
        
        return $dados;
    }

    /**
     * Comparativo entre regiões
     */
    public function getComparativoRegioes($filtros)
    {
        $regioesList = "'" . implode("','", $filtros['regioes']) . "'";
        
        $sql = "
            SELECT 
                m.regiao,
                COUNT(*) as total_registros,
                AVG(i.pontuacao) as pontuacao_media,
                MIN(i.pontuacao) as pontuacao_minima,
                MAX(i.pontuacao) as pontuacao_maxima,
                STDDEV(i.pontuacao) as desvio_padrao
            FROM {$this->table} i
            JOIN municipios m ON i.municipio_id = m.id
            WHERE i.tipo_indicador_id = :tipo_indicador_id
            AND m.regiao IN ({$regioesList})
        ";
        
        $params = [':tipo_indicador_id' => $filtros['tipo_indicador_id']];
        
        if (!empty($filtros['periodo'])) {
            $sql .= " AND i.competencia LIKE :periodo";
            $params[':periodo'] = $filtros['periodo'] . '%';
        }
        
        $sql .= " GROUP BY m.regiao ORDER BY pontuacao_media DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filtra indicadores por quadrimestre
     */
    private function filtrarPorQuadrimestre($indicadores, $quadrimestre)
    {
        $mapeamento = [
            1 => [1, 2, 3, 4],
            2 => [5, 6, 7, 8],
            3 => [9, 10, 11, 12]
        ];
        
        $mesesValidos = $mapeamento[(int)$quadrimestre] ?? [];
        
        return array_filter($indicadores, function($indicador) use ($mesesValidos) {
            $mes = $this->extrairMesCompetencia($indicador['competencia']);
            return in_array($mes, $mesesValidos);
        });
    }

    /**
     * Extrai o mês da competência (formato: YYYYMM)
     */
    private function extrairMesCompetencia($competencia)
    {
        if (strlen($competencia) >= 6) {
            return (int)substr($competencia, 4, 2);
        }
        return null;
    }

    /**
     * Agrupa indicadores por município
     */
    private function agruparPorMunicipio($indicadores)
    {
        $agrupados = [];
        
        foreach ($indicadores as $indicador) {
            $municipioId = $indicador['municipio_id'];
            
            if (!isset($agrupados[$municipioId])) {
                $agrupados[$municipioId] = [
                    'municipio' => [
                        'id' => $indicador['municipio_id'],
                        'ibge' => $indicador['ibge'],
                        'nome' => $indicador['municipio_nome'],
                        'uf' => $indicador['uf'],
                        'regiao' => $indicador['regiao']
                    ],
                    'indicadores' => [],
                    'pontuacao_media' => 0,
                    'total_registros' => 0
                ];
            }
            
            $agrupados[$municipioId]['indicadores'][] = [
                'competencia' => $indicador['competencia'],
                'pontuacao' => $indicador['pontuacao'],
                'estabelecimento' => $indicador['estabelecimento'],
                'dados' => json_decode($indicador['dados'] ?? '{}', true)
            ];
            
            $agrupados[$municipioId]['total_registros']++;
        }
        
        // Calcular pontuação média
        foreach ($agrupados as &$grupo) {
            if ($grupo['total_registros'] > 0) {
                $soma = array_sum(array_column($grupo['indicadores'], 'pontuacao'));
                $grupo['pontuacao_media'] = $soma / $grupo['total_registros'];
            }
        }
        
        return array_values($agrupados);
    }

    /**
     * Cria as tabelas necessárias para indicadores
     */
    public function criarTabelas()
    {
        // Tabela de categorias de indicadores
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS categorias_indicadores (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                descricao TEXT,
                ativo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela de tipos de indicadores
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tipos_indicadores (
                id SERIAL PRIMARY KEY,
                categoria_id INTEGER REFERENCES categorias_indicadores(id) ON DELETE CASCADE,
                nome VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL,
                descricao TEXT,
                unidade VARCHAR(50),
                formula TEXT,
                ativo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela de municípios
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS municipios (
                id SERIAL PRIMARY KEY,
                ibge VARCHAR(10) NOT NULL UNIQUE,
                nome VARCHAR(100) NOT NULL,
                uf CHAR(2) NOT NULL,
                regiao VARCHAR(50),
                populacao BIGINT,
                area_km2 DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela principal de indicadores
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id SERIAL PRIMARY KEY,
                municipio_id INTEGER REFERENCES municipios(id) ON DELETE CASCADE,
                tipo_indicador_id INTEGER REFERENCES tipos_indicadores(id) ON DELETE CASCADE,
                competencia VARCHAR(10) NOT NULL,
                cnes VARCHAR(20),
                estabelecimento VARCHAR(200),
                tipo_estabelecimento VARCHAR(100),
                ine VARCHAR(20),
                nome_equipe VARCHAR(200),
                sigla_equipe VARCHAR(50),
                dados JSONB,
                pontuacao DECIMAL(8,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Índices para performance
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_indicadores_municipio ON {$this->table}(municipio_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_indicadores_tipo ON {$this->table}(tipo_indicador_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_indicadores_competencia ON {$this->table}(competencia)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_indicadores_dados ON {$this->table} USING GIN(dados)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_municipios_regiao ON municipios(regiao)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_tipos_categoria ON tipos_indicadores(categoria_id)");

        return true;
    }
}