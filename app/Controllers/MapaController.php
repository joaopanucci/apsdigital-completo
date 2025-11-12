<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Mapa;

class MapaController extends Controller
{
    private $mapaModel;

    public function __construct()
    {
        parent::__construct();
        $this->mapaModel = new Mapa();
    }

    /**
     * Exibe a página principal dos mapas
     */
    public function index()
    {
        $regioes = $this->getRegioesDisponiveis();
        
        return $this->render('mapas/index', [
            'title' => 'Mapas Regionais - Mato Grosso do Sul',
            'regioes' => $regioes,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Mapas Regionais']
            ]
        ]);
    }

    /**
     * Exibe o mapa de uma região específica
     */
    public function regiao($regiao = null)
    {
        if (!$regiao) {
            $this->redirect('/mapas');
            return;
        }

        // Verificar se a região existe
        $regioes = $this->getRegioesDisponiveis();
        $regiaoExiste = false;
        
        foreach ($regioes as $r) {
            if ($r['slug'] === $regiao) {
                $regiaoExiste = true;
                break;
            }
        }

        if (!$regiaoExiste) {
            $this->setFlashMessage('Região não encontrada.', 'error');
            $this->redirect('/mapas');
            return;
        }

        $regiaoData = $this->mapaModel->getRegiaoData($regiao);
        
        return $this->render('mapas/regiao', [
            'title' => 'Mapa da Região: ' . ucwords(str_replace('-', ' ', $regiao)),
            'regiao' => $regiao,
            'regiaoData' => $regiaoData,
            'regioes' => $regioes,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Mapas Regionais', 'url' => '/mapas'],
                ['title' => ucwords(str_replace('-', ' ', $regiao))]
            ]
        ]);
    }

    /**
     * API: Retorna o SVG de uma região específica
     */
    public function getSvg($regiao)
    {
        $regiaoNome = str_replace('-', ' ', strtoupper($regiao));
        $svgPath = PUBLIC_PATH . '/assets/mapas/svg/' . $regiaoNome . '.svg';
        
        if (!file_exists($svgPath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'SVG da região não encontrado']);
            return;
        }

        $svgContent = file_get_contents($svgPath);
        
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=3600');
        echo $svgContent;
    }

    /**
     * API: Retorna os dados GeoJSON de uma região específica
     */
    public function getGeoJson($regiao)
    {
        $regiaoNome = str_replace('-', ' ', strtoupper($regiao));
        $jsonPath = PUBLIC_PATH . '/assets/mapas/json/' . $regiaoNome . '.json';
        
        if (!file_exists($jsonPath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Dados da região não encontrados']);
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        
        header('Content-Type: application/json');
        echo $jsonContent;
    }

    /**
     * API: Lista todas as regiões disponíveis
     */
    public function listarRegioes()
    {
        $regioes = $this->getRegioesDisponiveis();
        
        header('Content-Type: application/json');
        echo json_encode($regioes);
    }

    /**
     * Obtém a lista de regiões disponíveis
     */
    private function getRegioesDisponiveis()
    {
        $svgPath = PUBLIC_PATH . '/assets/mapas/svg/';
        $regioes = [];
        
        if (is_dir($svgPath)) {
            $files = glob($svgPath . '*.svg');
            
            foreach ($files as $file) {
                $nome = basename($file, '.svg');
                $slug = $this->createSlug($nome);
                
                $regioes[] = [
                    'nome' => $nome,
                    'slug' => $slug,
                    'arquivo_svg' => $nome . '.svg',
                    'arquivo_json' => $nome . '.json'
                ];
            }
        }
        
        // Ordenar por nome
        usort($regioes, function($a, $b) {
            return strcmp($a['nome'], $b['nome']);
        });
        
        return $regioes;
    }

    /**
     * Cria um slug amigável para URLs
     */
    private function createSlug($text)
    {
        // Converter para minúsculas e substituir espaços por hífens
        $slug = strtolower(str_replace(' ', '-', $text));
        
        // Remover caracteres especiais
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        
        // Remover hífens duplos
        $slug = preg_replace('/\-+/', '-', $slug);
        
        // Remover hífens do início e fim
        $slug = trim($slug, '-');
        
        return $slug;
    }

    /**
     * Visualizador interativo de mapas (página completa)
     */
    public function visualizar($regiao = null)
    {
        $regioes = $this->getRegioesDisponiveis();
        
        return $this->render('mapas/visualizar', [
            'title' => 'Visualizador de Mapas Interativo',
            'regiao' => $regiao,
            'regioes' => $regioes,
            'breadcrumb' => [
                ['title' => 'Início', 'url' => '/'],
                ['title' => 'Mapas Regionais', 'url' => '/mapas'],
                ['title' => 'Visualizador Interativo']
            ]
        ]);
    }
}