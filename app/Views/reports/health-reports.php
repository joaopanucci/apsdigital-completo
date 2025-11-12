<?php
// Verificar autenticação e permissões
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../Middleware/PermissionMiddleware.php';

AuthMiddleware::checkAuth();
PermissionMiddleware::check('reports.view');

// Dados do usuário logado
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_profile = $_SESSION['current_profile_name'] ?? 'Usuário';
$permissions = $_SESSION['permissions'] ?? [];
$csrf_token = $_SESSION['csrf_token'];

// Configurações da página
$title = 'Saúde da Mulher - Relatórios';
$current_page = 'health_reports';

// Simular dados
$user_profiles = $_SESSION['user_profiles'] ?? [];
$notifications = [];
$notifications_count = 0;

// Buffer do conteúdo da página
ob_start();
?>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="page-title">
                <i class="fas fa-heartbeat"></i>
                Saúde da Mulher
            </h1>
            <p class="page-subtitle">
                Relatórios de medicamentos e insumos da saúde da mulher
            </p>
        </div>
        
        <div class="page-actions">
            <?php if (in_array('health_forms.create', $permissions)): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newHealthFormModal">
                    <i class="fas fa-plus me-1"></i>
                    Novo Formulário
                </button>
            <?php endif; ?>
            
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportHealthReportModal">
                <i class="fas fa-download me-1"></i>
                Exportar Relatório
            </button>
        </div>
    </div>
</div>

<!-- Alerts Container -->
<div id="alert-container"></div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Total Formulários
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalFormsCount">
                            <span class="spinner-border spinner-border-sm text-danger"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-medical fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Municípios Ativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeMunicipalitiesCount">
                            <span class="spinner-border spinner-border-sm text-success"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-map-marker-alt fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Medicamentos Vencendo
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="expiringMedsCount">
                            <span class="spinner-border spinner-border-sm text-warning"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Estoque Baixo
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="lowStockCount">
                            <span class="spinner-border spinner-border-sm text-info"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="healthTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms" type="button" role="tab">
                    <i class="fas fa-file-medical me-1"></i>
                    Formulários
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="medications-tab" data-bs-toggle="tab" data-bs-target="#medications" type="button" role="tab">
                    <i class="fas fa-pills me-1"></i>
                    Medicamentos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">
                    <i class="fas fa-warehouse me-1"></i>
                    Controle de Estoque
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                    <i class="fas fa-chart-line me-1"></i>
                    Análises
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content" id="healthTabsContent">
            <!-- Forms Tab -->
            <div class="tab-pane fade show active" id="forms" role="tabpanel">
                <!-- Forms Filters -->
                <form id="formsFilterForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="formSearch" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="formSearch" name="search" placeholder="Município, medicação">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="formMunicipality" class="form-label">Município</label>
                        <select class="form-select" id="formMunicipality" name="municipio">
                            <option value="">Todos</option>
                            <!-- Será populado via AJAX -->
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="formMonth" class="form-label">Competência</label>
                        <input type="month" class="form-control" id="formMonth" name="competencia" value="<?php echo date('Y-m'); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="formMedication" class="form-label">Medicamento</label>
                        <select class="form-select" id="formMedication" name="medicacao">
                            <option value="">Todos</option>
                            <option value="anticoncepcional_oral">Anticoncepcional Oral</option>
                            <option value="anticoncepcional_injetavel">Anticoncepcional Injetável</option>
                            <option value="preservativo_masculino">Preservativo Masculino</option>
                            <option value="preservativo_feminino">Preservativo Feminino</option>
                            <option value="diu">DIU</option>
                            <option value="teste_gravidez">Teste de Gravidez</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>
                                Buscar
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearFormsFilters()">
                                <i class="fas fa-times me-1"></i>
                                Limpar
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Forms Table -->
                <div class="table-responsive">
                    <table id="healthFormsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Município</th>
                                <th>Medicação</th>
                                <th>Consumo Mensal</th>
                                <th>Estoque Atual</th>
                                <th>Lote</th>
                                <th>Data Vencimento</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dados carregados via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Medications Tab -->
            <div class="tab-pane fade" id="medications" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="mb-3">Consumo por Medicamento</h5>
                        <canvas id="medicationsChart" height="400"></canvas>
                    </div>
                    <div class="col-md-4">
                        <h5 class="mb-3">Top 5 Medicamentos</h5>
                        <div id="topMedicationsList">
                            <!-- Será populado via JavaScript -->
                        </div>
                        
                        <h5 class="mb-3 mt-4">Alertas</h5>
                        <div class="list-group" id="medicationAlerts">
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle text-warning me-3"></i>
                                    <div>
                                        <h6 class="mb-1">5 lotes vencendo</h6>
                                        <small class="text-muted">Próximos 30 dias</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-boxes text-danger me-3"></i>
                                    <div>
                                        <h6 class="mb-1">3 medicamentos em baixa</h6>
                                        <small class="text-muted">Estoque crítico</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-line text-info me-3"></i>
                                    <div>
                                        <h6 class="mb-1">Aumento de 15%</h6>
                                        <small class="text-muted">Consumo mensal</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Tab -->
            <div class="tab-pane fade" id="stock" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Controle de Estoque por Município</h5>
                        <canvas id="stockByMunicipalityChart" height="300"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h5>Validade dos Lotes</h5>
                        <canvas id="expirationChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Stock Alert Table -->
                <h5 class="mb-3">Alertas de Estoque</h5>
                <div class="table-responsive">
                    <table id="stockAlertsTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Município</th>
                                <th>Medicamento</th>
                                <th>Estoque Atual</th>
                                <th>Consumo Médio</th>
                                <th>Dias Restantes</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Campo Grande</td>
                                <td>Anticoncepcional Oral</td>
                                <td>250</td>
                                <td>180/mês</td>
                                <td class="text-warning">42 dias</td>
                                <td><span class="badge bg-warning">Atenção</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Dourados</td>
                                <td>Preservativo Masculino</td>
                                <td>150</td>
                                <td>200/mês</td>
                                <td class="text-danger">23 dias</td>
                                <td><span class="badge bg-danger">Crítico</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Três Lagoas</td>
                                <td>Teste de Gravidez</td>
                                <td>80</td>
                                <td>45/mês</td>
                                <td class="text-success">53 dias</td>
                                <td><span class="badge bg-success">Normal</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Analytics Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Análise de Tendências</h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="periodRadio" id="period3m" autocomplete="off" checked>
                                <label class="btn btn-outline-primary" for="period3m">3 meses</label>
                                
                                <input type="radio" class="btn-check" name="periodRadio" id="period6m" autocomplete="off">
                                <label class="btn btn-outline-primary" for="period6m">6 meses</label>
                                
                                <input type="radio" class="btn-check" name="periodRadio" id="period12m" autocomplete="off">
                                <label class="btn btn-outline-primary" for="period12m">12 meses</label>
                            </div>
                        </div>
                        <canvas id="trendsChart" height="400"></canvas>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                                <h5>Crescimento Médio</h5>
                                <h3 class="text-primary">+12.5%</h3>
                                <p class="text-muted mb-0">Últimos 6 meses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-pills fa-3x text-success mb-3"></i>
                                <h5>Medicamento Mais Consumido</h5>
                                <h6 class="text-success">Anticoncepcional Oral</h6>
                                <p class="text-muted mb-0">2.450 unidades/mês</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-map-marker-alt fa-3x text-info mb-3"></i>
                                <h5>Maior Demanda</h5>
                                <h6 class="text-info">Campo Grande</h6>
                                <p class="text-muted mb-0">35% do total</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Health Form -->
<?php if (in_array('health_forms.create', $permissions)): ?>
<div class="modal fade" id="newHealthFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Novo Formulário Saúde da Mulher
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="newHealthFormForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="formMunicipio" class="form-label">Município *</label>
                            <select class="form-select" id="formMunicipio" name="municipio" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="formCompetencia" class="form-label">Competência *</label>
                            <input type="month" class="form-control" id="formCompetencia" name="competencia" value="<?php echo date('Y-m'); ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="formMedicacao" class="form-label">Medicação *</label>
                            <select class="form-select" id="formMedicacao" name="medicacao" required>
                                <option value="">Selecione...</option>
                                <option value="anticoncepcional_oral">Anticoncepcional Oral</option>
                                <option value="anticoncepcional_injetavel">Anticoncepcional Injetável</option>
                                <option value="preservativo_masculino">Preservativo Masculino</option>
                                <option value="preservativo_feminino">Preservativo Feminino</option>
                                <option value="diu">DIU</option>
                                <option value="teste_gravidez">Teste de Gravidez</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="formConsumo" class="form-label">Consumo Mensal *</label>
                            <input type="number" class="form-control" id="formConsumo" name="consumo_mensal" min="0" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="formEstoque" class="form-label">Estoque Atual *</label>
                            <input type="number" class="form-control" id="formEstoque" name="estoque" min="0" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="formLote" class="form-label">Lote</label>
                            <input type="text" class="form-control" id="formLote" name="lote">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="formVencimento" class="form-label">Data de Vencimento</label>
                            <input type="date" class="form-control" id="formVencimento" name="data_vencimento" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="formObservacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="formObservacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        Salvar Formulário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Export Health Report -->
<div class="modal fade" id="exportHealthReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>
                    Exportar Relatório
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="exportHealthReportForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Formato *</label>
                        <select class="form-select" id="exportFormat" name="formato" required>
                            <option value="">Selecione...</option>
                            <option value="xlsx">Excel (XLSX)</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="exportDateStart" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="exportDateStart" name="data_inicio">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="exportDateEnd" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="exportDateEnd" name="data_fim">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exportMunicipality" class="form-label">Município</label>
                        <select class="form-select" id="exportMunicipality" name="municipio">
                            <option value="">Todos os municípios</option>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="exportEmail" name="enviar_email">
                        <label class="form-check-label" for="exportEmail">
                            Enviar por email
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>
                        Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let healthFormsTable;

document.addEventListener('DOMContentLoaded', function() {
    initHealthFormsTable();
    initCharts();
    loadHealthStats();
    loadMunicipalities();
    
    // Event listeners
    document.getElementById('formsFilterForm').addEventListener('submit', handleFormsFilter);
    
    <?php if (in_array('health_forms.create', $permissions)): ?>
        document.getElementById('newHealthFormForm').addEventListener('submit', handleNewHealthForm);
    <?php endif; ?>
    
    document.getElementById('exportHealthReportForm').addEventListener('submit', handleExportReport);
    
    // Period change for trends chart
    document.querySelectorAll('input[name="periodRadio"]').forEach(radio => {
        radio.addEventListener('change', updateTrendsChart);
    });
});

function initHealthFormsTable() {
    healthFormsTable = $('#healthFormsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/api/health/forms/list',
            type: 'POST',
            data: function(d) {
                const formData = new FormData(document.getElementById('formsFilterForm'));
                for (let [key, value] of formData.entries()) {
                    d[key] = value;
                }
                d.csrf_token = window.APP_CONFIG.csrfToken;
                return d;
            }
        },
        columns: [
            { data: 'id' },
            { data: 'municipio' },
            { 
                data: 'medicacao',
                render: function(data) {
                    const medications = {
                        'anticoncepcional_oral': 'Anticoncepcional Oral',
                        'anticoncepcional_injetavel': 'Anticoncepcional Injetável',
                        'preservativo_masculino': 'Preservativo Masculino',
                        'preservativo_feminino': 'Preservativo Feminino',
                        'diu': 'DIU',
                        'teste_gravidez': 'Teste de Gravidez'
                    };
                    return medications[data] || data;
                }
            },
            {
                data: 'consumo_mensal',
                render: function(data) {
                    return parseInt(data).toLocaleString('pt-BR');
                }
            },
            {
                data: 'estoque',
                render: function(data, type, row) {
                    const estoque = parseInt(data);
                    const consumo = parseInt(row.consumo_mensal);
                    const diasRestantes = Math.floor(estoque / (consumo / 30));
                    
                    let classe = 'text-success';
                    if (diasRestantes < 30) classe = 'text-danger';
                    else if (diasRestantes < 60) classe = 'text-warning';
                    
                    return `<span class="${classe}">${estoque.toLocaleString('pt-BR')}</span>`;
                }
            },
            { data: 'lote' },
            {
                data: 'data_vencimento',
                render: function(data) {
                    if (!data) return '-';
                    
                    const vencimento = new Date(data);
                    const hoje = new Date();
                    const diasParaVencer = Math.ceil((vencimento - hoje) / (1000 * 60 * 60 * 24));
                    
                    let classe = 'text-success';
                    if (diasParaVencer < 30) classe = 'text-danger';
                    else if (diasParaVencer < 90) classe = 'text-warning';
                    
                    return `<span class="${classe}">${vencimento.toLocaleDateString('pt-BR')}</span>`;
                }
            },
            {
                data: 'status',
                render: function(data, type, row) {
                    const estoque = parseInt(row.estoque);
                    const consumo = parseInt(row.consumo_mensal);
                    const diasRestantes = Math.floor(estoque / (consumo / 30));
                    
                    if (diasRestantes < 30) {
                        return '<span class="badge bg-danger">Crítico</span>';
                    } else if (diasRestantes < 60) {
                        return '<span class="badge bg-warning">Atenção</span>';
                    } else {
                        return '<span class="badge bg-success">Normal</span>';
                    }
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = '<div class="btn-group btn-group-sm">';
                    
                    if (<?php echo in_array('health_forms.edit', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-primary" onclick="editHealthForm(${data})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>`;
                    }
                    
                    actions += `<button class="btn btn-outline-info" onclick="viewHealthForm(${data})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>`;
                    
                    if (<?php echo in_array('health_forms.delete', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-danger" onclick="deleteHealthForm(${data})" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>`;
                    }
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        order: [[0, 'desc']]
    });
}

function initCharts() {
    // Medications Chart
    const medicationsCtx = document.getElementById('medicationsChart');
    if (medicationsCtx) {
        new Chart(medicationsCtx, {
            type: 'bar',
            data: {
                labels: ['Anticoncep. Oral', 'Anticoncep. Injetável', 'Preserv. Masculino', 'Preserv. Feminino', 'DIU', 'Teste Gravidez'],
                datasets: [{
                    label: 'Consumo Mensal',
                    data: [2450, 1200, 3200, 800, 150, 500],
                    backgroundColor: [
                        '#ff6b6b',
                        '#4ecdc4',
                        '#45b7d1',
                        '#f9ca24',
                        '#6c5ce7',
                        '#a0e7e5'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Stock by Municipality Chart
    const stockCtx = document.getElementById('stockByMunicipalityChart');
    if (stockCtx) {
        new Chart(stockCtx, {
            type: 'doughnut',
            data: {
                labels: ['Campo Grande', 'Dourados', 'Três Lagoas', 'Corumbá', 'Outros'],
                datasets: [{
                    data: [35, 15, 12, 8, 30],
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    // Expiration Chart
    const expirationCtx = document.getElementById('expirationChart');
    if (expirationCtx) {
        new Chart(expirationCtx, {
            type: 'pie',
            data: {
                labels: ['Vence em 30 dias', 'Vence em 90 dias', 'Vence em 180 dias', 'Mais de 180 dias'],
                datasets: [{
                    data: [5, 12, 25, 58],
                    backgroundColor: ['#dc3545', '#ffc107', '#17a2b8', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    // Trends Chart
    updateTrendsChart();
}

function updateTrendsChart() {
    const trendsCtx = document.getElementById('trendsChart');
    if (!trendsCtx) return;
    
    const period = document.querySelector('input[name="periodRadio"]:checked').id;
    let labels, data1, data2, data3;
    
    switch (period) {
        case 'period3m':
            labels = ['Ago', 'Set', 'Out'];
            data1 = [2200, 2350, 2450];
            data2 = [1100, 1150, 1200];
            data3 = [3000, 3100, 3200];
            break;
        case 'period6m':
            labels = ['Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out'];
            data1 = [2000, 2100, 2150, 2200, 2350, 2450];
            data2 = [1000, 1050, 1080, 1100, 1150, 1200];
            data3 = [2800, 2900, 2950, 3000, 3100, 3200];
            break;
        case 'period12m':
            labels = ['Nov', 'Dez', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out'];
            data1 = [1800, 1850, 1900, 1950, 2000, 2050, 2000, 2100, 2150, 2200, 2350, 2450];
            data2 = [900, 920, 940, 960, 980, 1000, 1020, 1050, 1080, 1100, 1150, 1200];
            data3 = [2500, 2600, 2650, 2700, 2750, 2800, 2850, 2900, 2950, 3000, 3100, 3200];
            break;
    }
    
    // Destruir gráfico anterior se existir
    if (window.trendsChart) {
        window.trendsChart.destroy();
    }
    
    window.trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Anticoncepcional Oral',
                    data: data1,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Anticoncepcional Injetável',
                    data: data2,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Preservativo Masculino',
                    data: data3,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function loadHealthStats() {
    fetch('/api/health/stats', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('totalFormsCount').textContent = data.stats.total_forms || 0;
            document.getElementById('activeMunicipalitiesCount').textContent = data.stats.active_municipalities || 0;
            document.getElementById('expiringMedsCount').textContent = data.stats.expiring_meds || 0;
            document.getElementById('lowStockCount').textContent = data.stats.low_stock || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar estatísticas:', error);
    });
}

function loadMunicipalities() {
    fetch('/api/municipalities/list', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const selects = ['formMunicipality', 'formMunicipio', 'exportMunicipality'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    data.municipalities.forEach(mun => {
                        const option = document.createElement('option');
                        option.value = mun.ibge;
                        option.textContent = mun.municipio;
                        select.appendChild(option);
                    });
                }
            });
        }
    })
    .catch(error => {
        console.error('Erro ao carregar municípios:', error);
    });
}

// Event Handlers
function handleFormsFilter(e) {
    e.preventDefault();
    healthFormsTable.ajax.reload();
}

function clearFormsFilters() {
    document.getElementById('formsFilterForm').reset();
    healthFormsTable.ajax.reload();
}

<?php if (in_array('health_forms.create', $permissions)): ?>
function handleNewHealthForm(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/health/forms/create', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Formulário criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('newHealthFormModal')).hide();
            this.reset();
            healthFormsTable.ajax.reload();
            loadHealthStats();
        } else {
            showAlert(data.message || 'Erro ao criar formulário', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão', 'danger');
    });
}
<?php endif; ?>

function handleExportReport(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/health/export', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Erro na exportação');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `saude_mulher_${new Date().toISOString().split('T')[0]}.xlsx`;
        a.click();
        window.URL.revokeObjectURL(url);
        
        showAlert('Relatório exportado com sucesso!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('exportHealthReportModal')).hide();
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro ao exportar relatório', 'danger');
    });
}

// Form Actions
function editHealthForm(formId) {
    // TODO: Implementar edição de formulário
    showAlert('Funcionalidade em desenvolvimento', 'info');
}

function viewHealthForm(formId) {
    window.open(`/health/forms/${formId}/view`, '_blank');
}

function deleteHealthForm(formId) {
    if (confirm('Tem certeza que deseja excluir este formulário?')) {
        fetch(`/api/health/forms/${formId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.APP_CONFIG.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Formulário excluído com sucesso!', 'success');
                healthFormsTable.ajax.reload();
                loadHealthStats();
            } else {
                showAlert(data.message || 'Erro ao excluir formulário', 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro de conexão', 'danger');
        });
    }
}
</script>

<?php
$content = ob_get_clean();

// Incluir layout principal
include __DIR__ . '/../layouts/app.php';
?>