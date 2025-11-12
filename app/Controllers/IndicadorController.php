<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Indicador;
use App\Models\Municipio;

class IndicadorController extends Controller
{
    private $indicadorModel;
    private $municipioModel;

    public function __construct()
    {
        parent::__construct();
        $this->indicadorModel = new Indicador();
        $this->municipioModel = new Municipio();
    }

    /**
     * Página principal de indicadores
     */
    public function index()
    {
        $categorias = $this->indicadorModel->getCategorias();
        $regioes = $this->municipioModel->getRegioesDistintas();
        
        return $this->render('indicadores/index', [
            'title' => 'Indicadores de Saúde por Região',
            'categorias' => $categorias,
            'regioes' => $regioes,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Indicadores de Saúde']
            ]
        ]);
    }

    /**
     * Página de tutorial sobre indicadores
     */
    public function tutorial()
    {
        return $this->render('indicadores/tutorial', [
            'title' => 'Tutorial - Indicadores de Saúde',
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Indicadores', 'url' => '/indicadores'],
                ['title' => 'Tutorial']
            ]
        ]);
    }

    /**
     * API: Retorna todas as categorias de indicadores
     */
    public function getCategorias()
    {
        $categorias = $this->indicadorModel->getCategorias();
        
        header('Content-Type: application/json');
        echo json_encode($categorias);
    }

    /**
     * API: Retorna tipos de indicadores por categoria
     */
    public function getTiposIndicadores($categoriaId)
    {
        $tipos = $this->indicadorModel->getTiposPorCategoria($categoriaId);
        
        header('Content-Type: application/json');
        echo json_encode($tipos);
    }

    /**
     * API: Obtém dados de indicadores por região
     */
    public function getDadosPorRegiao()
    {
        $tipoIndicadorId = $_GET['tipo_indicador_id'] ?? null;
        $regiao = $_GET['regiao'] ?? null;
        $competencia = $_GET['competencia'] ?? null;
        $quadrimestre = $_GET['quadrimestre'] ?? null;

        if (!$tipoIndicadorId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Tipo de indicador é obrigatório']);
            return;
        }

        $filtros = [
            'tipo_indicador_id' => $tipoIndicadorId,
            'regiao' => $regiao,
            'competencia' => $competencia,
            'quadrimestre' => $quadrimestre
        ];

        $dados = $this->indicadorModel->getDadosPorRegiao($filtros);
        
        header('Content-Type: application/json');
        echo json_encode($dados);
    }

    /**
     * API: Obtém dados de um município específico
     */
    public function getDadosMunicipio($municipioId)
    {
        $tipoIndicadorId = $_GET['tipo_indicador_id'] ?? null;
        $competencia = $_GET['competencia'] ?? null;
        $quadrimestre = $_GET['quadrimestre'] ?? null;

        if (!$tipoIndicadorId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Tipo de indicador é obrigatório']);
            return;
        }

        $filtros = [
            'tipo_indicador_id' => $tipoIndicadorId,
            'municipio_id' => $municipioId,
            'competencia' => $competencia,
            'quadrimestre' => $quadrimestre
        ];

        $dados = $this->indicadorModel->getDadosMunicipio($filtros);
        
        header('Content-Type: application/json');
        echo json_encode($dados);
    }

    /**
     * Página de visualização de indicadores por região
     */
    public function porRegiao($regiao = null)
    {
        if (!$regiao) {
            $this->redirect('/indicadores');
            return;
        }

        $regiaoData = $this->municipioModel->getMunicipiosPorRegiao($regiao);
        $categorias = $this->indicadorModel->getCategorias();
        
        if (empty($regiaoData)) {
            $this->setFlashMessage('Região não encontrada.', 'error');
            $this->redirect('/indicadores');
            return;
        }

        return $this->render('indicadores/regiao', [
            'title' => 'Indicadores - ' . ucwords(str_replace('-', ' ', $regiao)),
            'regiao' => $regiao,
            'municipios' => $regiaoData,
            'categorias' => $categorias,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Indicadores', 'url' => '/indicadores'],
                ['title' => ucwords(str_replace('-', ' ', $regiao))]
            ]
        ]);
    }

    /**
     * Página de dashboard com gráficos e estatísticas
     */
    public function dashboard()
    {
        $estatisticas = $this->indicadorModel->getEstatisticasGerais();
        $regioes = $this->municipioModel->getRegioesDistintas();
        
        return $this->render('indicadores/dashboard', [
            'title' => 'Dashboard - Indicadores de Saúde',
            'estatisticas' => $estatisticas,
            'regioes' => $regioes,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Indicadores', 'url' => '/indicadores'],
                ['title' => 'Dashboard']
            ]
        ]);
    }

    /**
     * API: Relatório de indicadores
     */
    public function relatorio()
    {
        $tipoIndicadorId = $_GET['tipo_indicador_id'] ?? null;
        $regiao = $_GET['regiao'] ?? null;
        $periodo = $_GET['periodo'] ?? null;

        $filtros = [
            'tipo_indicador_id' => $tipoIndicadorId,
            'regiao' => $regiao,
            'periodo' => $periodo
        ];

        $relatorio = $this->indicadorModel->gerarRelatorio($filtros);
        
        header('Content-Type: application/json');
        echo json_encode($relatorio);
    }

    /**
     * API: Comparativo entre regiões
     */
    public function comparativo()
    {
        $tipoIndicadorId = $_GET['tipo_indicador_id'] ?? null;
        $regioes = $_GET['regioes'] ?? []; // Array de regiões para comparar
        $periodo = $_GET['periodo'] ?? null;

        if (!$tipoIndicadorId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Tipo de indicador é obrigatório']);
            return;
        }

        if (!is_array($regioes)) {
            $regioes = explode(',', $regioes);
        }

        $filtros = [
            'tipo_indicador_id' => $tipoIndicadorId,
            'regioes' => $regioes,
            'periodo' => $periodo
        ];

        $comparativo = $this->indicadorModel->getComparativoRegioes($filtros);
        
        header('Content-Type: application/json');
        echo json_encode($comparativo);
    }

    /**
     * Página de comparação entre regiões
     */
    public function comparar()
    {
        $regioes = $this->municipioModel->getRegioesDistintas();
        $categorias = $this->indicadorModel->getCategorias();
        
        return $this->render('indicadores/comparar', [
            'title' => 'Comparativo entre Regiões',
            'regioes' => $regioes,
            'categorias' => $categorias,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Indicadores', 'url' => '/indicadores'],
                ['title' => 'Comparativo']
            ]
        ]);
    }

    /**
     * Exportação de dados em diferentes formatos
     */
    public function exportar()
    {
        $formato = $_GET['formato'] ?? 'json'; // json, csv, excel
        $tipoIndicadorId = $_GET['tipo_indicador_id'] ?? null;
        $regiao = $_GET['regiao'] ?? null;
        
        if (!$tipoIndicadorId) {
            http_response_code(400);
            echo 'Tipo de indicador é obrigatório';
            return;
        }

        $filtros = [
            'tipo_indicador_id' => $tipoIndicadorId,
            'regiao' => $regiao
        ];

        $dados = $this->indicadorModel->getDadosPorRegiao($filtros);

        switch ($formato) {
            case 'csv':
                $this->exportarCSV($dados);
                break;
            case 'excel':
                $this->exportarExcel($dados);
                break;
            default:
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="indicadores.json"');
                echo json_encode($dados);
                break;
        }
    }

    /**
     * Exporta dados para CSV
     */
    private function exportarCSV($dados)
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="indicadores.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalhos
        fputcsv($output, ['Municipio', 'IBGE', 'Regiao', 'Pontuacao_Media', 'Total_Registros']);
        
        // Dados
        foreach ($dados as $item) {
            fputcsv($output, [
                $item['municipio']['nome'],
                $item['municipio']['ibge'],
                $item['municipio']['regiao'],
                $item['pontuacao_media'],
                $item['total_registros']
            ]);
        }
        
        fclose($output);
    }

    /**
     * Exporta dados para Excel (usando CSV com separador ;)
     */
    private function exportarExcel($dados)
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="indicadores.xls"');
        
        echo "Municipio;IBGE;Regiao;Pontuacao_Media;Total_Registros\n";
        
        foreach ($dados as $item) {
            echo implode(';', [
                $item['municipio']['nome'],
                $item['municipio']['ibge'],
                $item['municipio']['regiao'],
                $item['pontuacao_media'],
                $item['total_registros']
            ]) . "\n";
        }
    }
}