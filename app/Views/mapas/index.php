<?php $this->extend('layouts/main') ?>

<?php $this->section('styles') ?>
<style>
.region-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
}

.region-card:hover {
    border-color: var(--bs-primary);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.region-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.map-preview {
    width: 100%;
    height: 200px;
    background: #f8f9fa;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    overflow: hidden;
}

.map-preview svg {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
}

.stats-card {
    background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-info) 100%);
    color: white;
}

.feature-list {
    list-style: none;
    padding: 0;
}

.feature-list li {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.feature-list li:last-child {
    border-bottom: none;
}

.feature-list li i {
    color: var(--bs-success);
    margin-right: 0.5rem;
}
</style>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Cabeçalho -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="card-title mb-2">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Mapas Regionais - Mato Grosso do Sul
                            </h1>
                            <p class="card-text mb-0">
                                Explore os dados de saúde por região através de mapas interativos e visualizações dinâmicas
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <i class="fas fa-globe-americas" style="font-size: 4rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas Gerais -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-map fa-2x mb-2"></i>
                    <h3><?= count($regioes) ?></h3>
                    <p class="mb-0">Regiões Mapeadas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-city fa-2x mb-2"></i>
                    <h3>79</h3>
                    <p class="mb-0">Municípios</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-chart-bar fa-2x mb-2"></i>
                    <h3>15+</h3>
                    <p class="mb-0">Indicadores</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-database fa-2x mb-2"></i>
                    <h3>Atualizado</h3>
                    <p class="mb-0">Dados Recentes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Funcionalidades Principais -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-eye text-primary me-2"></i>
                        Visualizador Interativo
                    </h5>
                    <p class="card-text">
                        Visualize todos os mapas em uma interface interativa com ferramentas de zoom, 
                        sobreposição de dados e comparações entre regiões.
                    </p>
                    <a href="/mapas/visualizar" class="btn btn-primary">
                        <i class="fas fa-play me-2"></i>Abrir Visualizador
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-chart-line text-success me-2"></i>
                        Indicadores por Região
                    </h5>
                    <p class="card-text">
                        Acesse dados detalhados de indicadores de saúde organizados por região, 
                        com gráficos e relatórios comparativos.
                    </p>
                    <a href="/indicadores" class="btn btn-success">
                        <i class="fas fa-chart-bar me-2"></i>Ver Indicadores
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Regiões -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>
                        Regiões Disponíveis
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($regioes as $regiao): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card region-card h-100" 
                                 onclick="window.location.href='/mapas/regiao/<?= $regiao['slug'] ?>'">
                                <div class="card-body text-center">
                                    <div class="map-preview">
                                        <i class="fas fa-map-marker-alt text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="card-title"><?= htmlspecialchars($regiao['nome']) ?></h5>
                                    <p class="card-text text-muted">
                                        Clique para explorar os dados desta região
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary me-2">
                                            <i class="fas fa-file-image me-1"></i>SVG
                                        </span>
                                        <span class="badge bg-success">
                                            <i class="fas fa-code me-1"></i>GeoJSON
                                        </span>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="/mapas/regiao/<?= $regiao['slug'] ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-map me-1"></i>Ver Mapa
                                        </a>
                                        <a href="/indicadores/regiao/<?= $regiao['slug'] ?>" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-chart-bar me-1"></i>Indicadores
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recursos e Funcionalidades -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Recursos Disponíveis
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="feature-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            Mapas vetoriais interativos (SVG)
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            Dados geográficos detalhados (GeoJSON)
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            Visualização responsiva
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            Sobreposição de indicadores
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            Exportação de dados
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            Comparação entre regiões
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Como Usar
                    </h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li class="mb-2">
                            <strong>Selecione uma região</strong> clicando em um dos cartões acima
                        </li>
                        <li class="mb-2">
                            <strong>Explore o mapa</strong> com zoom e ferramentas interativas
                        </li>
                        <li class="mb-2">
                            <strong>Visualize indicadores</strong> sobrepostos no mapa
                        </li>
                        <li class="mb-2">
                            <strong>Compare regiões</strong> usando o comparativo
                        </li>
                        <li class="mb-2">
                            <strong>Exporte dados</strong> em diferentes formatos
                        </li>
                    </ol>
                    <a href="/indicadores/tutorial" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-graduation-cap me-1"></i>Tutorial Completo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar efeito de hover suave nos cartões
    const regionCards = document.querySelectorAll('.region-card');
    
    regionCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Preview dos mapas SVG (lazy loading)
    const mapPreviews = document.querySelectorAll('.map-preview');
    mapPreviews.forEach((preview, index) => {
        if (index < 3) { // Carregar apenas os 3 primeiros
            loadMapPreview(preview, <?= json_encode($regioes) ?>[index]);
        }
    });
});

function loadMapPreview(container, regiao) {
    fetch(`/api/mapas/svg/${regiao.slug}`)
        .then(response => response.text())
        .then(svg => {
            container.innerHTML = svg;
            // Ajustar tamanho do SVG
            const svgElement = container.querySelector('svg');
            if (svgElement) {
                svgElement.style.maxWidth = '100%';
                svgElement.style.maxHeight = '100%';
            }
        })
        .catch(error => {
            console.log('Erro ao carregar preview do mapa:', error);
        });
}
</script>
<?php $this->endSection() ?>