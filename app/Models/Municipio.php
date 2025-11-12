<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Municipio extends Model
{
    protected $table = 'municipios';
    protected $primaryKey = 'id';

    /**
     * Obtém todas as regiões distintas
     */
    public function getRegioesDistintas()
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT regiao 
            FROM {$this->table} 
            WHERE regiao IS NOT NULL 
            ORDER BY regiao
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Obtém municípios por região
     */
    public function getMunicipiosPorRegiao($regiao)
    {
        $regiaoNome = str_replace('-', ' ', strtoupper($regiao));
        
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE UPPER(regiao) = :regiao 
            ORDER BY nome
        ");
        $stmt->execute([':regiao' => $regiaoNome]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca municípios por nome
     */
    public function buscarPorNome($nome)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE LOWER(nome) LIKE LOWER(:nome) 
            ORDER BY nome
        ");
        $stmt->execute([':nome' => '%' . $nome . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém município por código IBGE
     */
    public function getPorIBGE($ibge)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE ibge = :ibge 
            LIMIT 1
        ");
        $stmt->execute([':ibge' => $ibge]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém todos os municípios com estatísticas
     */
    public function getAllComEstatisticas()
    {
        $stmt = $this->db->prepare("
            SELECT 
                m.*,
                COUNT(i.id) as total_indicadores,
                AVG(i.pontuacao) as pontuacao_media
            FROM {$this->table} m
            LEFT JOIN indicadores i ON m.id = i.municipio_id
            GROUP BY m.id, m.ibge, m.nome, m.uf, m.regiao, m.populacao, m.area_km2
            ORDER BY m.nome
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém estatísticas por região
     */
    public function getEstatisticasPorRegiao()
    {
        $stmt = $this->db->prepare("
            SELECT 
                regiao,
                COUNT(*) as total_municipios,
                SUM(populacao) as populacao_total,
                SUM(area_km2) as area_total,
                AVG(populacao) as populacao_media,
                COUNT(DISTINCT i.tipo_indicador_id) as tipos_indicadores
            FROM {$this->table} m
            LEFT JOIN indicadores i ON m.id = i.municipio_id
            WHERE regiao IS NOT NULL
            GROUP BY regiao
            ORDER BY regiao
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria ou atualiza município
     */
    public function criarOuAtualizar($dados)
    {
        // Verificar se já existe
        $existe = $this->getPorIBGE($dados['ibge']);
        
        if ($existe) {
            // Atualizar
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET nome = :nome,
                    uf = :uf,
                    regiao = :regiao,
                    populacao = :populacao,
                    area_km2 = :area_km2,
                    updated_at = NOW()
                WHERE ibge = :ibge
                RETURNING id
            ");
        } else {
            // Criar
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} 
                (ibge, nome, uf, regiao, populacao, area_km2, created_at, updated_at)
                VALUES (:ibge, :nome, :uf, :regiao, :populacao, :area_km2, NOW(), NOW())
                RETURNING id
            ");
        }
        
        $parametros = [
            ':ibge' => $dados['ibge'],
            ':nome' => $dados['nome'],
            ':uf' => $dados['uf'] ?? 'MS',
            ':regiao' => $dados['regiao'] ?? null,
            ':populacao' => $dados['populacao'] ?? null,
            ':area_km2' => $dados['area_km2'] ?? null
        ];
        
        $stmt->execute($parametros);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['id'] ?? ($existe['id'] ?? null);
    }

    /**
     * Importa municípios de dados geográficos
     */
    public function importarDadosGeograficos()
    {
        $jsonPath = PUBLIC_PATH . '/assets/mapas/json/';
        $arquivos = glob($jsonPath . '*.json');
        
        $municipiosImportados = 0;
        
        foreach ($arquivos as $arquivo) {
            $regiao = basename($arquivo, '.json');
            $conteudo = file_get_contents($arquivo);
            $dados = json_decode($conteudo, true);
            
            if (isset($dados['features'])) {
                foreach ($dados['features'] as $feature) {
                    $properties = $feature['properties'] ?? [];
                    
                    if (isset($properties['CD_MUN'], $properties['NM_MUN'])) {
                        $dadosMunicipio = [
                            'ibge' => $properties['CD_MUN'],
                            'nome' => $properties['NM_MUN'],
                            'uf' => $properties['SIGLA_UF'] ?? 'MS',
                            'regiao' => $regiao,
                            'area_km2' => $properties['AREA_KM2'] ?? null
                        ];
                        
                        $this->criarOuAtualizar($dadosMunicipio);
                        $municipiosImportados++;
                    }
                }
            }
        }
        
        return $municipiosImportados;
    }

    /**
     * Obtém municípios com indicadores em determinado período
     */
    public function getComIndicadoresPeriodo($periodo)
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT m.*
            FROM {$this->table} m
            INNER JOIN indicadores i ON m.id = i.municipio_id
            WHERE i.competencia LIKE :periodo
            ORDER BY m.nome
        ");
        $stmt->execute([':periodo' => $periodo . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ranking de municípios por pontuação média
     */
    public function getRanking($tipoIndicadorId = null, $regiao = null)
    {
        $sql = "
            SELECT 
                m.*,
                AVG(i.pontuacao) as pontuacao_media,
                COUNT(i.id) as total_registros,
                MAX(i.competencia) as ultima_competencia
            FROM {$this->table} m
            INNER JOIN indicadores i ON m.id = i.municipio_id
            WHERE i.pontuacao IS NOT NULL
        ";
        
        $params = [];
        
        if ($tipoIndicadorId) {
            $sql .= " AND i.tipo_indicador_id = :tipo_indicador_id";
            $params[':tipo_indicador_id'] = $tipoIndicadorId;
        }
        
        if ($regiao) {
            $sql .= " AND m.regiao = :regiao";
            $params[':regiao'] = $regiao;
        }
        
        $sql .= "
            GROUP BY m.id, m.ibge, m.nome, m.uf, m.regiao, m.populacao, m.area_km2
            ORDER BY pontuacao_media DESC, m.nome
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém densidade populacional dos municípios
     */
    public function getDensidadePopulacional()
    {
        $stmt = $this->db->prepare("
            SELECT 
                *,
                CASE 
                    WHEN area_km2 > 0 THEN populacao / area_km2 
                    ELSE NULL 
                END as densidade_demografica
            FROM {$this->table} 
            WHERE populacao IS NOT NULL 
            AND area_km2 IS NOT NULL 
            AND area_km2 > 0
            ORDER BY densidade_demografica DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se município existe por nome e UF
     */
    public function existePorNomeUF($nome, $uf = 'MS')
    {
        $stmt = $this->db->prepare("
            SELECT id FROM {$this->table} 
            WHERE LOWER(nome) = LOWER(:nome) 
            AND uf = :uf 
            LIMIT 1
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':uf' => $uf
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Atualiza dados populacionais
     */
    public function atualizarPopulacao($ibge, $populacao)
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET populacao = :populacao, 
                updated_at = NOW() 
            WHERE ibge = :ibge
        ");
        return $stmt->execute([
            ':populacao' => $populacao,
            ':ibge' => $ibge
        ]);
    }
}