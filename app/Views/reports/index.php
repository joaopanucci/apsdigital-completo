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
$title = 'Relatórios - APS Digital';
$current_page = 'reports';

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
                <i class="fas fa-chart-bar"></i>
                Relatórios Gerais
            </h1>
            <p class="page-subtitle">
                Geração e exportação de relatórios do sistema APS Digital
            </p>
        </div>
        
        <div class="page-actions">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customReportModal">
                <i class="fas fa-plus me-1"></i>
                Relatório Personalizado
            </button>
        </div>
    </div>
</div>

<!-- Alerts Container -->
<div id="alert-container"></div>

<!-- Quick Reports Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">Usuários Ativos</h5>
                        <p class="card-text small mb-0">Relatório de usuários ativos por município</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-light btn-sm" onclick="generateQuickReport('users')">
                        <i class="fas fa-download me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">Equipamentos</h5>
                        <p class="card-text small mb-0">Relatório de tablets e chips distribuídos</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-tablet-alt fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-light btn-sm" onclick="generateQuickReport('equipment')">
                        <i class="fas fa-download me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">E-Agentes</h5>
                        <p class="card-text small mb-0">Relatório de pagamentos ACS/Supervisores</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-light btn-sm" onclick="generateQuickReport('eagentes')">
                        <i class="fas fa-download me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">Auditoria</h5>
                        <p class="card-text small mb-0">Relatório de atividades do sistema</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-dark btn-sm" onclick="generateQuickReport('audit')">
                        <i class="fas fa-download me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reports Tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="reportsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="generated-tab" data-bs-toggle="tab" data-bs-target="#generated" type="button" role="tab">
                    <i class="fas fa-file-alt me-1"></i>
                    Relatórios Gerados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="scheduled-tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button" role="tab">
                    <i class="fas fa-clock me-1"></i>
                    Relatórios Agendados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                    <i class="fas fa-file-code me-1"></i>
                    Modelos
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content" id="reportsTabsContent">
            <!-- Generated Reports Tab -->
            <div class="tab-pane fade show active" id="generated" role="tabpanel">
                <!-- Filters -->
                <form id="generatedReportsFilter" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="reportSearch" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="reportSearch" name="search" placeholder="Nome do relatório">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="reportType" class="form-label">Tipo</label>
                        <select class="form-select" id="reportType" name="tipo">
                            <option value="">Todos</option>
                            <option value="users">Usuários</option>
                            <option value="equipment">Equipamentos</option>
                            <option value="health">Saúde da Mulher</option>
                            <option value="eagentes">E-Agentes</option>
                            <option value="audit">Auditoria</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="reportStatus" class="form-label">Status</label>
                        <select class="form-select" id="reportStatus" name="status">
                            <option value="">Todos</option>
                            <option value="completed">Concluído</option>
                            <option value="processing">Processando</option>
                            <option value="error">Erro</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="dateFrom" class="form-label">Data Início</label>
                        <input type="date" class="form-control" id="dateFrom" name="data_inicio">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="dateTo" class="form-label">Data Fim</label>
                        <input type="date" class="form-control" id="dateTo" name="data_fim">
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Generated Reports Table -->
                <div class="table-responsive">
                    <table id="generatedReportsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome do Relatório</th>
                                <th>Tipo</th>
                                <th>Gerado por</th>
                                <th>Data/Hora</th>
                                <th>Status</th>
                                <th>Tamanho</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dados carregados via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Scheduled Reports Tab -->
            <div class="tab-pane fade" id="scheduled" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Relatórios Agendados</h5>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleReportModal">
                        <i class="fas fa-plus me-1"></i>
                        Agendar Relatório
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table id="scheduledReportsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Frequência</th>
                                <th>Próxima Execução</th>
                                <th>Status</th>
                                <th>Criado por</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Relatório Mensal de Usuários</td>
                                <td><span class="badge bg-primary">Usuários</span></td>
                                <td>Mensal</td>
                                <td>01/12/2024 08:00</td>
                                <td><span class="badge bg-success">Ativo</span></td>
                                <td>João Silva</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Equipamentos Distribuídos Semanal</td>
                                <td><span class="badge bg-success">Equipamentos</span></td>
                                <td>Semanal</td>
                                <td>25/11/2024 09:00</td>
                                <td><span class="badge bg-success">Ativo</span></td>
                                <td>Maria Santos</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Templates Tab -->
            <div class="tab-pane fade" id="templates" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Modelos de Relatórios</h5>
                    <?php if (in_array('reports.templates', $permissions)): ?>
                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                            <i class="fas fa-plus me-1"></i>
                            Novo Modelo
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-users fa-2x text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0">Usuários por Município</h6>
                                        <small class="text-muted">Relatório padrão</small>
                                    </div>
                                </div>
                                <p class="card-text small">Lista de usuários ativos agrupados por município com detalhes de perfil e último acesso.</p>
                                <button class="btn btn-primary btn-sm" onclick="useTemplate('users_municipality')">
                                    <i class="fas fa-play me-1"></i>
                                    Usar Modelo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-tablet-alt fa-2x text-success me-3"></i>
                                    <div>
                                        <h6 class="mb-0">Equipamentos Distribuídos</h6>
                                        <small class="text-muted">Relatório padrão</small>
                                    </div>
                                </div>
                                <p class="card-text small">Relatório de tablets e chips distribuídos com informações de usuário e município.</p>
                                <button class="btn btn-success btn-sm" onclick="useTemplate('equipment_distributed')">
                                    <i class="fas fa-play me-1"></i>
                                    Usar Modelo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-heartbeat fa-2x text-danger me-3"></i>
                                    <div>
                                        <h6 class="mb-0">Saúde da Mulher</h6>
                                        <small class="text-muted">Relatório especializado</small>
                                    </div>
                                </div>
                                <p class="card-text small">Relatório de medicamentos e insumos da saúde da mulher por município.</p>
                                <button class="btn btn-danger btn-sm" onclick="useTemplate('health_women')">
                                    <i class="fas fa-play me-1"></i>
                                    Usar Modelo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-money-bill-wave fa-2x text-warning me-3"></i>
                                    <div>
                                        <h6 class="mb-0">E-Agentes Pagamentos</h6>
                                        <small class="text-muted">Relatório financeiro</small>
                                    </div>
                                </div>
                                <p class="card-text small">Relatório de pagamentos para ACS e Supervisores por competência.</p>
                                <button class="btn btn-warning btn-sm" onclick="useTemplate('eagentes_payments')">
                                    <i class="fas fa-play me-1"></i>
                                    Usar Modelo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-search fa-2x text-info me-3"></i>
                                    <div>
                                        <h6 class="mb-0">Auditoria de Sistema</h6>
                                        <small class="text-muted">Relatório de segurança</small>
                                    </div>
                                </div>
                                <p class="card-text small">Relatório de atividades e logs do sistema para auditoria.</p>
                                <button class="btn btn-info btn-sm" onclick="useTemplate('system_audit')">
                                    <i class="fas fa-play me-1"></i>
                                    Usar Modelo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-dashed">
                            <div class="card-body text-center">
                                <i class="fas fa-plus fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Criar Novo Modelo</h6>
                                <p class="card-text small text-muted">Crie um modelo personalizado para seus relatórios recorrentes.</p>
                                <?php if (in_array('reports.templates', $permissions)): ?>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                                        <i class="fas fa-plus me-1"></i>
                                        Criar Modelo
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Custom Report -->
<div class="modal fade" id="customReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    Relatório Personalizado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="customReportForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="reportName" class="form-label">Nome do Relatório *</label>
                            <input type="text" class="form-control" id="reportName" name="nome" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="reportTypeCustom" class="form-label">Tipo de Relatório *</label>
                            <select class="form-select" id="reportTypeCustom" name="tipo" required>
                                <option value="">Selecione...</option>
                                <option value="users">Usuários</option>
                                <option value="equipment">Equipamentos</option>
                                <option value="health">Saúde da Mulher</option>
                                <option value="eagentes">E-Agentes</option>
                                <option value="audit">Auditoria</option>
                                <option value="custom">Personalizado</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="reportFormat" class="form-label">Formato *</label>
                            <select class="form-select" id="reportFormat" name="formato" required>
                                <option value="">Selecione...</option>
                                <option value="csv">CSV</option>
                                <option value="xlsx">Excel (XLSX)</option>
                                <option value="pdf">PDF</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dateRangeStart" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="dateRangeStart" name="data_inicio">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dateRangeEnd" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="dateRangeEnd" name="data_fim">
                        </div>
                        
                        <div class="col-md-12">
                            <label for="reportFilters" class="form-label">Filtros Adicionais</label>
                            <div class="row g-2" id="reportFiltersContainer">
                                <div class="col-md-6">
                                    <select class="form-select" name="filtro_municipio">
                                        <option value="">Todos os Municípios</option>
                                        <!-- Será populado via AJAX -->
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" name="filtro_perfil">
                                        <option value="">Todos os Perfis</option>
                                        <option value="1">Nacional</option>
                                        <option value="2">Regional</option>
                                        <option value="3">Municipal</option>
                                        <option value="4">Unidade</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailReport" name="enviar_email">
                                <label class="form-check-label" for="emailReport">
                                    Enviar relatório por email após geração
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-12" id="emailRecipientsContainer" style="display: none;">
                            <label for="emailRecipients" class="form-label">Destinatários (separados por vírgula)</label>
                            <textarea class="form-control" id="emailRecipients" name="email_destinatarios" rows="2" placeholder="email1@exemplo.com, email2@exemplo.com"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-cogs me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let generatedReportsTable;

document.addEventListener('DOMContentLoaded', function() {
    initGeneratedReportsTable();
    
    // Event listeners
    document.getElementById('generatedReportsFilter').addEventListener('submit', handleGeneratedReportsFilter);
    document.getElementById('customReportForm').addEventListener('submit', handleCustomReport);
    
    // Show/hide email recipients
    document.getElementById('emailReport').addEventListener('change', function() {
        const container = document.getElementById('emailRecipientsContainer');
        container.style.display = this.checked ? 'block' : 'none';
    });
    
    // Update filters based on report type
    document.getElementById('reportTypeCustom').addEventListener('change', function() {
        updateReportFilters(this.value);
    });
});

function initGeneratedReportsTable() {
    generatedReportsTable = $('#generatedReportsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/api/reports/list',
            type: 'POST',
            data: function(d) {
                const formData = new FormData(document.getElementById('generatedReportsFilter'));
                for (let [key, value] of formData.entries()) {
                    d[key] = value;
                }
                d.csrf_token = window.APP_CONFIG.csrfToken;
                return d;
            }
        },
        columns: [
            { data: 'id' },
            { data: 'nome' },
            {
                data: 'tipo',
                render: function(data) {
                    const badges = {
                        'users': '<span class="badge bg-primary">Usuários</span>',
                        'equipment': '<span class="badge bg-success">Equipamentos</span>',
                        'health': '<span class="badge bg-danger">Saúde da Mulher</span>',
                        'eagentes': '<span class="badge bg-warning">E-Agentes</span>',
                        'audit': '<span class="badge bg-info">Auditoria</span>'
                    };
                    return badges[data] || `<span class="badge bg-secondary">${data}</span>`;
                }
            },
            { data: 'gerado_por' },
            {
                data: 'dt_geracao',
                render: function(data) {
                    const date = new Date(data);
                    return date.toLocaleDateString('pt-BR') + '<br><small class="text-muted">' + date.toLocaleTimeString('pt-BR') + '</small>';
                }
            },
            {
                data: 'status',
                render: function(data) {
                    const badges = {
                        'completed': '<span class="badge bg-success">Concluído</span>',
                        'processing': '<span class="badge bg-warning">Processando</span>',
                        'error': '<span class="badge bg-danger">Erro</span>'
                    };
                    return badges[data] || data;
                }
            },
            {
                data: 'tamanho_arquivo',
                render: function(data) {
                    if (!data) return '-';
                    return formatFileSize(data);
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = '<div class="btn-group btn-group-sm">';
                    
                    if (row.status === 'completed') {
                        actions += `<button class="btn btn-outline-success" onclick="downloadReport(${data})" title="Download">
                            <i class="fas fa-download"></i>
                        </button>`;
                        
                        actions += `<button class="btn btn-outline-info" onclick="previewReport(${data})" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </button>`;
                        
                        if (<?php echo in_array('reports.share', $permissions) ? 'true' : 'false'; ?>) {
                            actions += `<button class="btn btn-outline-primary" onclick="shareReport(${data})" title="Compartilhar">
                                <i class="fas fa-share"></i>
                            </button>`;
                        }
                    }
                    
                    if (<?php echo in_array('reports.delete', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-danger" onclick="deleteReport(${data})" title="Excluir">
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

function handleGeneratedReportsFilter(e) {
    e.preventDefault();
    generatedReportsTable.ajax.reload();
}

function handleCustomReport(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Gerando...';
    submitBtn.disabled = true;
    
    fetch('/api/reports/generate', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Relatório iniciado! Você será notificado quando estiver pronto.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('customReportModal')).hide();
            this.reset();
            generatedReportsTable.ajax.reload();
        } else {
            showAlert(data.message || 'Erro ao gerar relatório', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão', 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function generateQuickReport(type) {
    const reportNames = {
        'users': 'Relatório de Usuários Ativos',
        'equipment': 'Relatório de Equipamentos Distribuídos',
        'eagentes': 'Relatório de Pagamentos E-Agentes',
        'audit': 'Relatório de Auditoria do Sistema'
    };
    
    if (confirm(`Deseja gerar o ${reportNames[type]}?`)) {
        const formData = new FormData();
        formData.append('tipo', type);
        formData.append('formato', 'xlsx');
        formData.append('nome', reportNames[type]);
        formData.append('csrf_token', window.APP_CONFIG.csrfToken);
        
        fetch('/api/reports/generate', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Relatório iniciado! Você será notificado quando estiver pronto.', 'success');
                generatedReportsTable.ajax.reload();
            } else {
                showAlert(data.message || 'Erro ao gerar relatório', 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro de conexão', 'danger');
        });
    }
}

function downloadReport(reportId) {
    window.location.href = `/api/reports/${reportId}/download`;
}

function previewReport(reportId) {
    window.open(`/api/reports/${reportId}/preview`, '_blank');
}

function shareReport(reportId) {
    // TODO: Implementar compartilhamento de relatórios
    showAlert('Funcionalidade em desenvolvimento', 'info');
}

function deleteReport(reportId) {
    if (confirm('Tem certeza que deseja excluir este relatório?')) {
        fetch(`/api/reports/${reportId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.APP_CONFIG.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Relatório excluído com sucesso!', 'success');
                generatedReportsTable.ajax.reload();
            } else {
                showAlert(data.message || 'Erro ao excluir relatório', 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro de conexão', 'danger');
        });
    }
}

function useTemplate(templateId) {
    // Preencher modal de relatório personalizado com template
    const modal = new bootstrap.Modal(document.getElementById('customReportModal'));
    
    const templates = {
        'users_municipality': {
            nome: 'Usuários por Município',
            tipo: 'users',
            formato: 'xlsx'
        },
        'equipment_distributed': {
            nome: 'Equipamentos Distribuídos',
            tipo: 'equipment',
            formato: 'xlsx'
        },
        'health_women': {
            nome: 'Saúde da Mulher',
            tipo: 'health',
            formato: 'xlsx'
        },
        'eagentes_payments': {
            nome: 'Pagamentos E-Agentes',
            tipo: 'eagentes',
            formato: 'xlsx'
        },
        'system_audit': {
            nome: 'Auditoria do Sistema',
            tipo: 'audit',
            formato: 'csv'
        }
    };
    
    const template = templates[templateId];
    if (template) {
        document.getElementById('reportName').value = template.nome;
        document.getElementById('reportTypeCustom').value = template.tipo;
        document.getElementById('reportFormat').value = template.formato;
    }
    
    modal.show();
}

function updateReportFilters(reportType) {
    const container = document.getElementById('reportFiltersContainer');
    
    // Limpar filtros existentes
    container.innerHTML = '';
    
    // Adicionar filtros baseados no tipo de relatório
    const commonFilters = `
        <div class="col-md-6">
            <select class="form-select" name="filtro_municipio">
                <option value="">Todos os Municípios</option>
            </select>
        </div>
    `;
    
    switch (reportType) {
        case 'users':
            container.innerHTML = commonFilters + `
                <div class="col-md-6">
                    <select class="form-select" name="filtro_perfil">
                        <option value="">Todos os Perfis</option>
                        <option value="1">Nacional</option>
                        <option value="2">Regional</option>
                        <option value="3">Municipal</option>
                        <option value="4">Unidade</option>
                    </select>
                </div>
            `;
            break;
        case 'equipment':
            container.innerHTML = commonFilters + `
                <div class="col-md-6">
                    <select class="form-select" name="filtro_status">
                        <option value="">Todos os Status</option>
                        <option value="delivered">Entregue</option>
                        <option value="available">Disponível</option>
                        <option value="maintenance">Manutenção</option>
                    </select>
                </div>
            `;
            break;
        case 'health':
            container.innerHTML = commonFilters + `
                <div class="col-md-6">
                    <select class="form-select" name="filtro_medicamento">
                        <option value="">Todos os Medicamentos</option>
                        <option value="anticoncepcional">Anticoncepcional</option>
                        <option value="preservativo">Preservativo</option>
                        <option value="teste_gravidez">Teste de Gravidez</option>
                    </select>
                </div>
            `;
            break;
        default:
            container.innerHTML = commonFilters;
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php
$content = ob_get_clean();

// Incluir layout principal
include __DIR__ . '/../layouts/app.php';
?>