<?php $this->extend('layouts/main') ?>

<?php $this->section('styles') ?>
<style>
.map-viewer-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
}

.viewer-toolbar {
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.viewer-content {
    flex: 1;
    display: flex;
    overflow: hidden;
}

.map-display {
    flex: 1;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.region-selector {
    width: 300px;
    background: #fff;
    border-left: 1px solid #dee2e6;
    overflow-y: auto;
}

.region-item {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.2s ease;
}

.region-item:hover {
    background-color: #f8f9fa;
}

.region-item.active {
    background-color: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.map-canvas {
    width: 100%;
    height: 100%;
    overflow: hidden;
    position: relative;
}

.map-canvas svg {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    cursor: move;
}

.toolbar-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.viewer-controls {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 1000;
}
</style>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="map-viewer-container">
    <!-- Barra de Ferramentas -->
    <div class="viewer-toolbar">
        <div class="d-flex justify-content-between align-items-center">
            <div class="toolbar-section">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2 text-primary"></i>
                    Visualizador Interativo de Mapas
                </h5>
                <span class="text-muted">|</span>
                <span class="badge bg-info" id="currentRegion">Selecione uma região</span>
            </div>
            
            <div class="toolbar-section">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomIn">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomOut">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="resetView">
                        <i class="fas fa-home"></i>
                    </button>
                </div>
                
                <div class="btn-group ms-2" role="group">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="toggleSidebar">
                        <i class="fas fa-sidebar"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="downloadMap">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
                
                <a href="/mapas" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo Principal -->
    <div class="viewer-content">
        <!-- Área do Mapa -->
        <div class="map-display">
            <div class="map-canvas" id="mapCanvas">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-map fa-4x mb-3" style="opacity: 0.3;"></i>
                    <h4>Selecione uma região para visualizar</h4>
                    <p>Use o painel lateral para escolher uma região do Mato Grosso do Sul</p>
                </div>
            </div>
            
            <!-- Controles Flutuantes -->
            <div class="viewer-controls">
                <div class="btn-group-vertical" role="group">
                    <button type="button" class="btn btn-light btn-sm" onclick="mapViewer.zoom(1.2)">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="btn btn-light btn-sm" onclick="mapViewer.zoom(0.8)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-light btn-sm" onclick="mapViewer.reset()">
                        <i class="fas fa-home"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Seletor de Regiões -->
        <div class="region-selector" id="regionSelector">
            <div class="p-3 border-bottom">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>Regiões Disponíveis
                </h6>
            </div>
            
            <?php foreach ($regioes as $regiao): ?>
            <div class="region-item" data-region="<?= $regiao['slug'] ?>" onclick="loadRegion('<?= $regiao['slug'] ?>')">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-map-marker-alt text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1"><?= htmlspecialchars($regiao['nome']) ?></h6>
                        <small class="text-muted">Clique para visualizar</small>
                    </div>
                    <div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Ações Adicionais -->
            <div class="p-3 border-top mt-3">
                <div class="d-grid gap-2">
                    <a href="/indicadores" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-chart-bar me-2"></i>Ver Indicadores
                    </a>
                    <a href="/mapas" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-th-large me-2"></i>Visão Geral
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
class InteractiveMapViewer {
    constructor() {
        this.currentRegion = null;
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this.isDragging = false;
        
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        // Controles de zoom na toolbar
        document.getElementById('zoomIn').addEventListener('click', () => this.zoom(1.2));
        document.getElementById('zoomOut').addEventListener('click', () => this.zoom(0.8));
        document.getElementById('resetView').addEventListener('click', () => this.reset());
        
        // Toggle sidebar
        document.getElementById('toggleSidebar').addEventListener('click', this.toggleSidebar.bind(this));
        
        // Download
        document.getElementById('downloadMap').addEventListener('click', this.downloadCurrentMap.bind(this));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyboard.bind(this));
    }
    
    async loadRegion(regionSlug) {
        try {
            // Atualizar UI
            this.updateActiveRegion(regionSlug);
            
            // Mostrar loading
            const mapCanvas = document.getElementById('mapCanvas');
            mapCanvas.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <h5>Carregando mapa da região...</h5>
                </div>
            `;
            
            // Carregar SVG
            const response = await fetch(`/api/mapas/svg/${regionSlug}`);
            
            if (!response.ok) {
                throw new Error('Erro ao carregar mapa');
            }
            
            const svgContent = await response.text();
            mapCanvas.innerHTML = svgContent;
            
            // Configurar interatividade
            this.setupMapInteractions();
            
            this.currentRegion = regionSlug;
            document.getElementById('currentRegion').textContent = 
                document.querySelector(`[data-region="${regionSlug}"] h6`).textContent;
                
        } catch (error) {
            console.error('Erro ao carregar região:', error);
            this.showError('Erro ao carregar o mapa da região');
        }
    }
    
    setupMapInteractions() {
        const svg = document.querySelector('#mapCanvas svg');
        if (!svg) return;
        
        svg.style.cursor = 'grab';
        
        // Mouse events
        svg.addEventListener('mousedown', this.startPan.bind(this));
        svg.addEventListener('wheel', this.handleWheel.bind(this));
        
        // Touch events para mobile
        svg.addEventListener('touchstart', this.startPan.bind(this));
    }
    
    startPan(e) {
        this.isDragging = true;
        
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        
        this.startX = clientX - this.panX;
        this.startY = clientY - this.panY;
        
        const svg = e.target.closest('svg');
        if (svg) {
            svg.style.cursor = 'grabbing';
        }
        
        document.addEventListener('mousemove', this.pan.bind(this));
        document.addEventListener('mouseup', this.endPan.bind(this));
        document.addEventListener('touchmove', this.pan.bind(this));
        document.addEventListener('touchend', this.endPan.bind(this));
        
        e.preventDefault();
    }
    
    pan(e) {
        if (!this.isDragging) return;
        
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        
        this.panX = clientX - this.startX;
        this.panY = clientY - this.startY;
        
        this.updateTransform();
        
        e.preventDefault();
    }
    
    endPan() {
        this.isDragging = false;
        
        const svg = document.querySelector('#mapCanvas svg');
        if (svg) {
            svg.style.cursor = 'grab';
        }
        
        document.removeEventListener('mousemove', this.pan.bind(this));
        document.removeEventListener('mouseup', this.endPan.bind(this));
        document.removeEventListener('touchmove', this.pan.bind(this));
        document.removeEventListener('touchend', this.endPan.bind(this));
    }
    
    handleWheel(e) {
        e.preventDefault();
        
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        this.zoom(delta);
    }
    
    zoom(factor) {
        this.zoom = Math.max(0.1, Math.min(5, this.zoom * factor));
        this.updateTransform();
    }
    
    reset() {
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this.updateTransform();
    }
    
    updateTransform() {
        const svg = document.querySelector('#mapCanvas svg');
        if (svg) {
            svg.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
        }
    }
    
    updateActiveRegion(regionSlug) {
        // Remover active de todas as regiões
        document.querySelectorAll('.region-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Adicionar active na região selecionada
        const activeItem = document.querySelector(`[data-region="${regionSlug}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    }
    
    toggleSidebar() {
        const sidebar = document.getElementById('regionSelector');
        const button = document.getElementById('toggleSidebar');
        
        if (sidebar.style.display === 'none') {
            sidebar.style.display = 'block';
            button.innerHTML = '<i class="fas fa-sidebar"></i>';
        } else {
            sidebar.style.display = 'none';
            button.innerHTML = '<i class="fas fa-arrows-alt-h"></i>';
        }
    }
    
    downloadCurrentMap() {
        if (!this.currentRegion) {
            alert('Selecione uma região primeiro');
            return;
        }
        
        const svg = document.querySelector('#mapCanvas svg');
        if (!svg) return;
        
        const svgContent = new XMLSerializer().serializeToString(svg);
        const blob = new Blob([svgContent], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `mapa-${this.currentRegion}.svg`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    handleKeyboard(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        switch(e.key) {
            case '+':
            case '=':
                this.zoom(1.2);
                break;
            case '-':
                this.zoom(0.8);
                break;
            case '0':
                this.reset();
                break;
            case 'h':
                this.toggleSidebar();
                break;
        }
    }
    
    showError(message) {
        const mapCanvas = document.getElementById('mapCanvas');
        mapCanvas.innerHTML = `
            <div class="text-center text-danger py-5">
                <i class="fas fa-exclamation-triangle fa-4x mb-3"></i>
                <h4>${message}</h4>
                <button class="btn btn-primary mt-2" onclick="location.reload()">
                    <i class="fas fa-refresh me-1"></i>Tentar Novamente
                </button>
            </div>
        `;
    }
}

// Funções globais
function loadRegion(regionSlug) {
    mapViewer.loadRegion(regionSlug);
}

// Inicializar
let mapViewer;
document.addEventListener('DOMContentLoaded', function() {
    mapViewer = new InteractiveMapViewer();
    
    // Carregar primeira região se especificada
    const urlRegion = '<?= $regiao ?? "" ?>';
    if (urlRegion) {
        mapViewer.loadRegion(urlRegion);
    }
});
</script>
<?php $this->endSection() ?>