<?php
// Verificar autenticação e permissões
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../Middleware/PermissionMiddleware.php';

AuthMiddleware::checkAuth();
PermissionMiddleware::check('equipment.view');

// Dados do usuário logado
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_profile = $_SESSION['current_profile_name'] ?? 'Usuário';
$permissions = $_SESSION['permissions'] ?? [];
$csrf_token = $_SESSION['csrf_token'];

// Configurações da página
$title = 'Equipamentos - APS Digital';
$current_page = 'equipment';

// Simular dados
$user_profiles = $_SESSION['user_profiles'] ?? [];
$notifications = [];
$notifications_count = 0;

// Buffer do conteúdo da página
ob_start();
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="page-title">
            <i class="fas fa-tablet-alt"></i>
            Gerenciar Equipamentos
        </h1>
        <p class="page-subtitle">
            Controle de tablets, chips e acessórios da APS
        </p>
    </div>
    
    <div class="page-actions">
        <?php if (in_array('equipment.create', $permissions)): ?>
            <div class="btn-group">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTabletModal">
                    <i class="fas fa-tablet-alt me-1"></i>
                    Novo Tablet
                </button>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addChipModal">
                    <i class="fas fa-sim-card me-1"></i>
                    Novo Chip
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (in_array('equipment.import', $permissions)): ?>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#importEquipmentModal">
                <i class="fas fa-file-import me-1"></i>
                Importar Planilha
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Alerts Container -->
<div id="alert-container"></div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Tablets Ativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeTabletsCount">
                            <span class="spinner-border spinner-border-sm text-success"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tablet-alt fa-2x text-success"></i>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Chips Ativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeChipsCount">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-sim-card fa-2x text-primary"></i>
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
                            Aguardando Entrega
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingDeliveryCount">
                            <span class="spinner-border spinner-border-sm text-warning"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-warning"></i>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Em Manutenção
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="maintenanceCount">
                            <span class="spinner-border spinner-border-sm text-danger"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Equipment Tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="equipmentTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tablets-tab" data-bs-toggle="tab" data-bs-target="#tablets" type="button" role="tab">
                    <i class="fas fa-tablet-alt me-1"></i>
                    Tablets
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="chips-tab" data-bs-toggle="tab" data-bs-target="#chips" type="button" role="tab">
                    <i class="fas fa-sim-card me-1"></i>
                    Chips
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="deliveries-tab" data-bs-toggle="tab" data-bs-target="#deliveries" type="button" role="tab">
                    <i class="fas fa-shipping-fast me-1"></i>
                    Entregas
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content" id="equipmentTabsContent">
            <!-- Tablets Tab -->
            <div class="tab-pane fade show active" id="tablets" role="tabpanel">
                <!-- Tablets Filters -->
                <form id="tabletsFilterForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="tabletSearch" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="tabletSearch" name="search" placeholder="IMEI, marca, modelo">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="tabletStatus" class="form-label">Status</label>
                        <select class="form-select" id="tabletStatus" name="status">
                            <option value="">Todos</option>
                            <option value="available">Disponível</option>
                            <option value="delivered">Entregue</option>
                            <option value="maintenance">Manutenção</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="tabletBrand" class="form-label">Marca</label>
                        <select class="form-select" id="tabletBrand" name="marca">
                            <option value="">Todas</option>
                            <option value="Samsung">Samsung</option>
                            <option value="Multilaser">Multilaser</option>
                            <option value="Positivo">Positivo</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="tabletBox" class="form-label">Caixa</label>
                        <input type="text" class="form-control" id="tabletBox" name="caixa" placeholder="Número da caixa">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>
                                Buscar
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearTabletFilters()">
                                <i class="fas fa-times me-1"></i>
                                Limpar
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Tablets Table -->
                <div class="table-responsive">
                    <table id="tabletsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>IMEI</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Caixa</th>
                                <th>Status</th>
                                <th>Usuário</th>
                                <th>Município</th>
                                <th>Data Entrega</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dados carregados via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Chips Tab -->
            <div class="tab-pane fade" id="chips" role="tabpanel">
                <!-- Chips Filters -->
                <form id="chipsFilterForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="chipSearch" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="chipSearch" name="search" placeholder="ICCID, operadora">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="chipStatus" class="form-label">Status</label>
                        <select class="form-select" id="chipStatus" name="status">
                            <option value="">Todos</option>
                            <option value="available">Disponível</option>
                            <option value="delivered">Entregue</option>
                            <option value="suspended">Suspenso</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="chipOperator" class="form-label">Operadora</label>
                        <select class="form-select" id="chipOperator" name="operadora">
                            <option value="">Todas</option>
                            <option value="Vivo">Vivo</option>
                            <option value="Claro">Claro</option>
                            <option value="TIM">TIM</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="chipBox" class="form-label">Caixa</label>
                        <input type="text" class="form-control" id="chipBox" name="caixa" placeholder="Número da caixa">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>
                                Buscar
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearChipFilters()">
                                <i class="fas fa-times me-1"></i>
                                Limpar
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Chips Table -->
                <div class="table-responsive">
                    <table id="chipsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ICCID</th>
                                <th>Operadora</th>
                                <th>Caixa</th>
                                <th>Status</th>
                                <th>Usuário</th>
                                <th>Município</th>
                                <th>Data Entrega</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dados carregados via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Deliveries Tab -->
            <div class="tab-pane fade" id="deliveries" role="tabpanel">
                <?php if (in_array('equipment.authorize', $permissions)): ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Autorização de Entregas:</strong> 
                        Revise e autorize as solicitações de entrega de equipamentos para os municípios.
                    </div>
                <?php endif; ?>
                
                <!-- Delivery Filters -->
                <form id="deliveriesFilterForm" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="deliverySearch" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="deliverySearch" name="search" placeholder="Município, responsável">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="deliveryStatus" class="form-label">Status</label>
                        <select class="form-select" id="deliveryStatus" name="status">
                            <option value="">Todos</option>
                            <option value="pending">Pendente</option>
                            <option value="authorized">Autorizado</option>
                            <option value="delivered">Entregue</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="deliveryMunicipality" class="form-label">Município</label>
                        <select class="form-select" id="deliveryMunicipality" name="municipio">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="deliveryDate" class="form-label">Data</label>
                        <input type="date" class="form-control" id="deliveryDate" name="data">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>
                                Buscar
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearDeliveryFilters()">
                                <i class="fas fa-times me-1"></i>
                                Limpar
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Deliveries Table -->
                <div class="table-responsive">
                    <table id="deliveriesTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Município</th>
                                <th>Responsável</th>
                                <th>Equipamentos</th>
                                <th>Data Solicitação</th>
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
        </div>
    </div>
</div>

<!-- Modal: Add Tablet -->
<?php if (in_array('equipment.create', $permissions)): ?>
<div class="modal fade" id="addTabletModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tablet-alt me-2"></i>
                    Adicionar Tablet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="addTabletForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="tabletImei" class="form-label">IMEI *</label>
                        <input type="text" class="form-control" id="tabletImei" name="imei" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="tabletMarca" class="form-label">Marca *</label>
                            <input type="text" class="form-control" id="tabletMarca" name="marca" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="tabletModelo" class="form-label">Modelo *</label>
                            <input type="text" class="form-control" id="tabletModelo" name="modelo" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tabletCaixa" class="form-label">Caixa</label>
                        <input type="text" class="form-control" id="tabletCaixa" name="caixa" placeholder="Número da caixa de origem">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        Adicionar Tablet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Add Chip -->
<?php if (in_array('equipment.create', $permissions)): ?>
<div class="modal fade" id="addChipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sim-card me-2"></i>
                    Adicionar Chip
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="addChipForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="chipIccid" class="form-label">ICCID *</label>
                        <input type="text" class="form-control" id="chipIccid" name="iccid" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="chipOperadora" class="form-label">Operadora *</label>
                            <select class="form-select" id="chipOperadora" name="operadora" required>
                                <option value="">Selecione...</option>
                                <option value="Vivo">Vivo</option>
                                <option value="Claro">Claro</option>
                                <option value="TIM">TIM</option>
                                <option value="Oi">Oi</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="chipCaixa" class="form-label">Caixa</label>
                            <input type="text" class="form-control" id="chipCaixa" name="caixa" placeholder="Número da caixa de origem">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        Adicionar Chip
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let tabletsTable, chipsTable, deliveriesTable;

document.addEventListener('DOMContentLoaded', function() {
    initTables();
    loadEquipmentStats();
    
    // Event listeners para abas
    document.querySelectorAll('#equipmentTabs button').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target');
            if (target === '#tablets' && tabletsTable) {
                tabletsTable.columns.adjust().draw();
            } else if (target === '#chips' && chipsTable) {
                chipsTable.columns.adjust().draw();
            } else if (target === '#deliveries' && deliveriesTable) {
                deliveriesTable.columns.adjust().draw();
            }
        });
    });
    
    // Event listeners para formulários
    document.getElementById('tabletsFilterForm').addEventListener('submit', handleTabletsFilter);
    document.getElementById('chipsFilterForm').addEventListener('submit', handleChipsFilter);
    document.getElementById('deliveriesFilterForm').addEventListener('submit', handleDeliveriesFilter);
    
    <?php if (in_array('equipment.create', $permissions)): ?>
        document.getElementById('addTabletForm').addEventListener('submit', handleAddTablet);
        document.getElementById('addChipForm').addEventListener('submit', handleAddChip);
    <?php endif; ?>
});

function initTables() {
    // Tablets Table
    tabletsTable = $('#tabletsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/api/equipment/tablets/list',
            type: 'POST',
            data: function(d) {
                const formData = new FormData(document.getElementById('tabletsFilterForm'));
                for (let [key, value] of formData.entries()) {
                    d[key] = value;
                }
                d.csrf_token = window.APP_CONFIG.csrfToken;
                return d;
            }
        },
        columns: [
            { data: 'imei' },
            { data: 'marca' },
            { data: 'modelo' },
            { data: 'caixa' },
            {
                data: 'status',
                render: function(data) {
                    const badges = {
                        'available': '<span class="badge bg-success">Disponível</span>',
                        'delivered': '<span class="badge bg-info">Entregue</span>',
                        'maintenance': '<span class="badge bg-warning">Manutenção</span>',
                        'inactive': '<span class="badge bg-secondary">Inativo</span>'
                    };
                    return badges[data] || data;
                }
            },
            { data: 'usuario_nome' },
            { data: 'municipio' },
            {
                data: 'dt_entrega',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString('pt-BR') : '-';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = '<div class="btn-group btn-group-sm">';
                    
                    if (<?php echo in_array('equipment.edit', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-primary" onclick="editTablet(${data})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>`;
                    }
                    
                    actions += `<button class="btn btn-outline-info" onclick="viewTabletHistory(${data})" title="Histórico">
                        <i class="fas fa-history"></i>
                    </button>`;
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ]
    });
    
    // Chips Table
    chipsTable = $('#chipsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/api/equipment/chips/list',
            type: 'POST',
            data: function(d) {
                const formData = new FormData(document.getElementById('chipsFilterForm'));
                for (let [key, value] of formData.entries()) {
                    d[key] = value;
                }
                d.csrf_token = window.APP_CONFIG.csrfToken;
                return d;
            }
        },
        columns: [
            { data: 'iccid' },
            { data: 'operadora' },
            { data: 'caixa' },
            {
                data: 'status',
                render: function(data) {
                    const badges = {
                        'available': '<span class="badge bg-success">Disponível</span>',
                        'delivered': '<span class="badge bg-info">Entregue</span>',
                        'suspended': '<span class="badge bg-warning">Suspenso</span>',
                        'inactive': '<span class="badge bg-secondary">Inativo</span>'
                    };
                    return badges[data] || data;
                }
            },
            { data: 'usuario_nome' },
            { data: 'municipio' },
            {
                data: 'dt_entrega',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString('pt-BR') : '-';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = '<div class="btn-group btn-group-sm">';
                    
                    if (<?php echo in_array('equipment.edit', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-primary" onclick="editChip(${data})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>`;
                    }
                    
                    actions += `<button class="btn btn-outline-info" onclick="viewChipHistory(${data})" title="Histórico">
                        <i class="fas fa-history"></i>
                    </button>`;
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ]
    });
    
    // Deliveries Table
    deliveriesTable = $('#deliveriesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/api/equipment/deliveries/list',
            type: 'POST',
            data: function(d) {
                const formData = new FormData(document.getElementById('deliveriesFilterForm'));
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
            { data: 'responsavel' },
            {
                data: 'equipamentos_count',
                render: function(data, type, row) {
                    return `${row.tablets_count || 0} tablets, ${row.chips_count || 0} chips`;
                }
            },
            {
                data: 'dt_solicitacao',
                render: function(data) {
                    return new Date(data).toLocaleDateString('pt-BR');
                }
            },
            {
                data: 'status',
                render: function(data) {
                    const badges = {
                        'pending': '<span class="badge bg-warning">Pendente</span>',
                        'authorized': '<span class="badge bg-success">Autorizado</span>',
                        'delivered': '<span class="badge bg-info">Entregue</span>',
                        'cancelled': '<span class="badge bg-danger">Cancelado</span>'
                    };
                    return badges[data] || data;
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = '<div class="btn-group btn-group-sm">';
                    
                    actions += `<button class="btn btn-outline-info" onclick="viewDelivery(${data})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>`;
                    
                    if (row.status === 'pending' && <?php echo in_array('equipment.authorize', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-success" onclick="authorizeDelivery(${data})" title="Autorizar">
                            <i class="fas fa-check"></i>
                        </button>`;
                    }
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ]
    });
}

function loadEquipmentStats() {
    fetch('/api/equipment/stats', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('activeTabletsCount').textContent = data.stats.active_tablets || 0;
            document.getElementById('activeChipsCount').textContent = data.stats.active_chips || 0;
            document.getElementById('pendingDeliveryCount').textContent = data.stats.pending_deliveries || 0;
            document.getElementById('maintenanceCount').textContent = data.stats.maintenance || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar estatísticas:', error);
    });
}

// Event Handlers
function handleTabletsFilter(e) {
    e.preventDefault();
    tabletsTable.ajax.reload();
}

function handleChipsFilter(e) {
    e.preventDefault();
    chipsTable.ajax.reload();
}

function handleDeliveriesFilter(e) {
    e.preventDefault();
    deliveriesTable.ajax.reload();
}

function clearTabletFilters() {
    document.getElementById('tabletsFilterForm').reset();
    tabletsTable.ajax.reload();
}

function clearChipFilters() {
    document.getElementById('chipsFilterForm').reset();
    chipsTable.ajax.reload();
}

function clearDeliveryFilters() {
    document.getElementById('deliveriesFilterForm').reset();
    deliveriesTable.ajax.reload();
}

<?php if (in_array('equipment.create', $permissions)): ?>
function handleAddTablet(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/equipment/tablets/create', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Tablet adicionado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addTabletModal')).hide();
            this.reset();
            tabletsTable.ajax.reload();
            loadEquipmentStats();
        } else {
            showAlert(data.message || 'Erro ao adicionar tablet', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão', 'danger');
    });
}

function handleAddChip(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/equipment/chips/create', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Chip adicionado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addChipModal')).hide();
            this.reset();
            chipsTable.ajax.reload();
            loadEquipmentStats();
        } else {
            showAlert(data.message || 'Erro ao adicionar chip', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão', 'danger');
    });
}
<?php endif; ?>

// Equipment actions
function editTablet(tabletId) {
    // TODO: Implementar edição de tablet
    showAlert('Funcionalidade em desenvolvimento', 'info');
}

function editChip(chipId) {
    // TODO: Implementar edição de chip
    showAlert('Funcionalidade em desenvolvimento', 'info');
}

function viewTabletHistory(tabletId) {
    window.open(`/equipment/tablets/${tabletId}/history`, '_blank');
}

function viewChipHistory(chipId) {
    window.open(`/equipment/chips/${chipId}/history`, '_blank');
}

function viewDelivery(deliveryId) {
    window.open(`/equipment/deliveries/${deliveryId}/view`, '_blank');
}

function authorizeDelivery(deliveryId) {
    if (confirm('Deseja autorizar esta entrega?')) {
        fetch(`/api/equipment/deliveries/${deliveryId}/authorize`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': window.APP_CONFIG.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Entrega autorizada com sucesso!', 'success');
                deliveriesTable.ajax.reload();
                loadEquipmentStats();
            } else {
                showAlert(data.message || 'Erro ao autorizar entrega', 'danger');
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