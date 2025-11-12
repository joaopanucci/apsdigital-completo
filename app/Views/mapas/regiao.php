<?php $this->extend('layouts/main') ?>

<?php $this->section('styles') ?>
<style>
.map-container {
    background: #fff;
    border-radius: 0.375rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    overflow: hidden;
    position: relative;
}

.map-viewer {
    width: 100%;
    height: 600px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
    position: relative;
}

.map-viewer svg {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    cursor: move;
    transition: all 0.3s ease;
}

.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
}

.map-controls .btn {
    margin-bottom: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.region-info {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.info-card {
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.loading-spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 999;
}

.zoom-info {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
}

.municipality-list {
    max-height: 400px;
    overflow-y: auto;
}

.municipality-item {
    padding: 0.75rem;
    border-bottom: 1px solid rgba(0,0,0,0.1);
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.municipality-item:hover {
    background-color: rgba(0,123,255,0.1);
}

.municipality-item:last-child {
    border-bottom: none;
}
</style>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Cabeçalho da Região -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="region-info">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">
                            <i class="fas fa-map-marked-alt text-primary me-2"></i>
                            <?= htmlspecialchars(ucwords(str_replace('-', ' ', $regiao))) ?>
                        </h1>
                        <p class="text-muted mb-3">
                            Explore os dados geográficos e indicadores de saúde desta região
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="/mapas" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Voltar aos Mapas
                            </a>
                            <a href="/indicadores/regiao/<?= $regiao ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chart-bar me-1"></i>Ver Indicadores
                            </a>
                            <button id="fullscreenBtn" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-expand me-1"></i>Tela Cheia
                            </button>
                            <button id="downloadBtn" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-download me-1"></i>Download SVG
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <span class="badge bg-primary fs-6">
                                <i class="fas fa-file-image me-1"></i>SVG
                            </span>
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-code me-1"></i>GeoJSON
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Visualizador do Mapa -->
        <div class="col-lg-8">
            <div class="card map-container">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Visualizador de Mapa
                    </h5>
                    <div class="zoom-info" id="zoomInfo">
                        Zoom: 100%
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="map-viewer" id="mapViewer">
                        <div class="loading-spinner">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando mapa...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Controles do Mapa -->
                    <div class="map-controls">
                        <div class="btn-group-vertical" role="group">
                            <button type="button" class="btn btn-light btn-sm" id="zoomIn">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-light btn-sm" id="zoomOut">
                                <i class="fas fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-light btn-sm" id="resetZoom">
                                <i class="fas fa-home"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Painel Lateral -->
        <div class="col-lg-4">
            <!-- Informações Técnicas -->
            <div class="card info-card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Informações Técnicas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary mb-0" id="municipiosCount">-</h4>
                                <small class="text-muted">Municípios</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success mb-0" id="areaTotal">-</h4>
                            <small class="text-muted">Área (km²)</small>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Formato:</small>
                        <small>SVG Vetorial</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Projeção:</small>
                        <small>WGS84</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Atualizado:</small>
                        <small id="lastUpdate">Recentemente</small>
                    </div>
                </div>
            </div>

            <!-- Lista de Municípios -->
            <div class="card info-card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-city me-2"></i>Municípios
                    </h6>
                    <span class="badge bg-primary" id="municipioBadge">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="municipality-list" id="municipalityList">
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-muted" role="status">
                                <span class="visually-hidden">Carregando municípios...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegação Rápida -->
            <div class="card info-card mt-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-compass me-2"></i>Navegação Rápida
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php 
                        $todasRegioes = ['BAIXO PANTANAL', 'CENTRO SUL', 'CENTRO', 'LESTE', 'NORDESTE', 'NORTE', 'PANTANAL', 'SUDESTE', 'SUL FRONTEIRA'];
                        $regioesLimitadas = array_slice($todasRegioes, 0, 4);
                        foreach ($regioesLimitadas as $r): 
                            $slug = strtolower(str_replace(' ', '-', $r));
                            $isAtual = ($slug === $regiao);
                        ?>
                        <a href="/mapas/regiao/<?= $slug ?>" 
                           class="btn <?= $isAtual ? 'btn-primary' : 'btn-outline-secondary' ?> btn-sm text-start">
                            <?= $isAtual ? '<i class="fas fa-map-marker-alt me-2"></i>' : '<i class="fas fa-map me-2"></i>' ?>
                            <?= htmlspecialchars($r) ?>
                        </a>
                        <?php endforeach; ?>
                        <a href="/mapas/visualizar" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-eye me-2"></i>Visualizador Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
class MapViewer {
    constructor() {
        this.currentZoom = 1;
        this.minZoom = 0.1;
        this.maxZoom = 5;
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.currentX = 0;
        this.currentY = 0;
        
        this.init();
    }
    
    async init() {
        try {
            await this.loadMap();
            await this.loadGeoData();
            this.setupEventListeners();
        } catch (error) {
            console.error('Erro ao inicializar o visualizador:', error);
            this.showError('Erro ao carregar o mapa');
        }
    }
    
    async loadMap() {
        const regiao = '<?= $regiao ?>';
        const response = await fetch(`/api/mapas/svg/${regiao}`);
        
        if (!response.ok) {
            throw new Error('Erro ao carregar o SVG');
        }
        
        const svgContent = await response.text();
        const mapViewer = document.getElementById('mapViewer');
        mapViewer.innerHTML = svgContent;
        
        const svg = mapViewer.querySelector('svg');
        if (svg) {
            svg.style.cursor = 'grab';
            svg.addEventListener('mousedown', this.startDrag.bind(this));
            svg.addEventListener('wheel', this.handleZoom.bind(this));
        }
    }
    
    async loadGeoData() {
        try {
            const regiao = '<?= $regiao ?>';
            const response = await fetch(`/api/mapas/geojson/${regiao}`);
            
            if (response.ok) {
                const geoData = await response.json();
                this.processGeoData(geoData);
            }
        } catch (error) {
            console.log('Dados geográficos não disponíveis:', error);
        }
    }
    
    processGeoData(geoData) {
        if (geoData.features) {
            const municipios = geoData.features.map(feature => ({
                nome: feature.properties.NM_MUN,
                ibge: feature.properties.CD_MUN,
                area: feature.properties.AREA_KM2
            }));
            
            this.updateMunicipalityList(municipios);
            this.updateStats(municipios);
        }
    }
    
    updateMunicipalityList(municipios) {
        const listContainer = document.getElementById('municipalityList');
        const badge = document.getElementById('municipioBadge');
        
        badge.textContent = municipios.length;
        
        if (municipios.length === 0) {
            listContainer.innerHTML = '<div class="text-center py-3 text-muted">Nenhum município encontrado</div>';
            return;
        }
        
        const html = municipios.map(municipio => `
            <div class="municipality-item" onclick="selectMunicipality('${municipio.ibge}')">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${municipio.nome}</strong>
                        <br>
                        <small class="text-muted">IBGE: ${municipio.ibge}</small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">${municipio.area ? Math.round(municipio.area) + ' km²' : '-'}</small>
                    </div>
                </div>
            </div>
        `).join('');
        
        listContainer.innerHTML = html;
    }
    
    updateStats(municipios) {
        document.getElementById('municipiosCount').textContent = municipios.length;
        
        const areaTotal = municipios.reduce((sum, m) => sum + (parseFloat(m.area) || 0), 0);
        document.getElementById('areaTotal').textContent = areaTotal > 0 ? 
            new Intl.NumberFormat('pt-BR').format(Math.round(areaTotal)) : '-';
    }
    
    setupEventListeners() {
        // Controles de zoom
        document.getElementById('zoomIn').addEventListener('click', () => this.zoom(1.2));
        document.getElementById('zoomOut').addEventListener('click', () => this.zoom(0.8));
        document.getElementById('resetZoom').addEventListener('click', () => this.resetZoom());
        
        // Outros controles
        document.getElementById('fullscreenBtn').addEventListener('click', this.toggleFullscreen.bind(this));
        document.getElementById('downloadBtn').addEventListener('click', this.downloadSVG.bind(this));
        
        // Mouse events
        document.addEventListener('mousemove', this.drag.bind(this));
        document.addEventListener('mouseup', this.endDrag.bind(this));
    }
    
    startDrag(e) {
        this.isDragging = true;
        this.startX = e.clientX - this.currentX;
        this.startY = e.clientY - this.currentY;
        
        const svg = e.target.closest('svg');
        if (svg) {
            svg.style.cursor = 'grabbing';
        }
    }
    
    drag(e) {
        if (!this.isDragging) return;
        
        e.preventDefault();
        this.currentX = e.clientX - this.startX;
        this.currentY = e.clientY - this.startY;
        
        this.updateTransform();
    }
    
    endDrag() {
        this.isDragging = false;
        const svg = document.querySelector('#mapViewer svg');
        if (svg) {
            svg.style.cursor = 'grab';
        }
    }
    
    handleZoom(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        this.zoom(delta);
    }
    
    zoom(factor) {
        const newZoom = Math.max(this.minZoom, Math.min(this.maxZoom, this.currentZoom * factor));
        this.currentZoom = newZoom;
        this.updateTransform();
        this.updateZoomInfo();
    }
    
    resetZoom() {
        this.currentZoom = 1;
        this.currentX = 0;
        this.currentY = 0;
        this.updateTransform();
        this.updateZoomInfo();
    }
    
    updateTransform() {
        const svg = document.querySelector('#mapViewer svg');
        if (svg) {
            svg.style.transform = `translate(${this.currentX}px, ${this.currentY}px) scale(${this.currentZoom})`;
        }
    }
    
    updateZoomInfo() {
        const zoomPercent = Math.round(this.currentZoom * 100);
        document.getElementById('zoomInfo').textContent = `Zoom: ${zoomPercent}%`;
    }
    
    toggleFullscreen() {
        const mapContainer = document.querySelector('.map-container');
        
        if (!document.fullscreenElement) {
            mapContainer.requestFullscreen().then(() => {
                document.getElementById('fullscreenBtn').innerHTML = 
                    '<i class="fas fa-compress me-1"></i>Sair Tela Cheia';
            });
        } else {
            document.exitFullscreen().then(() => {
                document.getElementById('fullscreenBtn').innerHTML = 
                    '<i class="fas fa-expand me-1"></i>Tela Cheia';
            });
        }
    }
    
    downloadSVG() {
        const svg = document.querySelector('#mapViewer svg');
        if (!svg) return;
        
        const svgContent = new XMLSerializer().serializeToString(svg);
        const blob = new Blob([svgContent], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `mapa-${<?= json_encode($regiao) ?>}.svg`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    showError(message) {
        const mapViewer = document.getElementById('mapViewer');
        mapViewer.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                <h5>${message}</h5>
                <button class="btn btn-primary mt-2" onclick="location.reload()">
                    <i class="fas fa-refresh me-1"></i>Tentar Novamente
                </button>
            </div>
        `;
    }
}

// Funções globais
function selectMunicipality(ibge) {
    console.log('Município selecionado:', ibge);
    // Aqui você pode implementar destaque no mapa
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    new MapViewer();
});
</script>
<?php $this->endSection() ?>