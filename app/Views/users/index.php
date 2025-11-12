<?php
// Verificar autenticação e permissões
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../Middleware/PermissionMiddleware.php';

AuthMiddleware::checkAuth();
PermissionMiddleware::check('users.view');

// Dados do usuário logado
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_profile = $_SESSION['current_profile_name'] ?? 'Usuário';
$permissions = $_SESSION['permissions'] ?? [];
$csrf_token = $_SESSION['csrf_token'];

// Configurações da página
$title = 'Gerenciar Usuários - APS Digital';
$current_page = 'users';

// Simular dados (normalmente vem do UserController)
$user_profiles = $_SESSION['user_profiles'] ?? [];
$notifications = [];
$notifications_count = 0;
$pending_users_count = 5; // Exemplo

// Buffer do conteúdo da página
ob_start();
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="page-title">
            <i class="fas fa-users"></i>
            Gerenciar Usuários
        </h1>
        <p class="page-subtitle">
            Visualizar, criar e editar usuários do sistema
        </p>
    </div>
    
    <div class="page-actions">
        <?php if (in_array('users.create', $permissions)): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fas fa-user-plus me-1"></i>
                Novo Usuário
            </button>
        <?php endif; ?>
        
        <?php if (in_array('users.import', $permissions)): ?>
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importUsersModal">
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
                            Usuários Ativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeUsersCount">
                            <span class="spinner-border spinner-border-sm text-success"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-success"></i>
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
                            Aguardando Aprovação
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingUsersCount">
                            <?php echo $pending_users_count; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-clock fa-2x text-warning"></i>
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
                            Usuários Inativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="inactiveUsersCount">
                            <span class="spinner-border spinner-border-sm text-danger"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-times fa-2x text-danger"></i>
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
                            Total de Usuários
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalUsersCount">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Buscar</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Nome, CPF ou email">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
                    <option value="pending">Aguardando</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="profile" class="form-label">Perfil</label>
                <select class="form-select" id="profile" name="profile">
                    <option value="">Todos</option>
                    <option value="1">Nacional</option>
                    <option value="2">Regional</option>
                    <option value="3">Municipal</option>
                    <option value="4">Unidade</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="municipality" class="form-label">Município</label>
                <select class="form-select" id="municipality" name="municipality">
                    <option value="">Todos</option>
                    <!-- Opcões serão carregadas dinamicamente -->
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Buscar
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-times me-1"></i>
                        Limpar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-list"></i>
            Lista de Usuários
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="usersTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Email</th>
                        <th>Perfil</th>
                        <th>Município</th>
                        <th>Status</th>
                        <th>Último Acesso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Os dados serão carregados via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create User -->
<?php if (in_array('users.create', $permissions)): ?>
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Novo Usuário
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="createUserForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="createNome" class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" id="createNome" name="nome" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createCpf" class="form-label">CPF *</label>
                            <input type="text" class="form-control" id="createCpf" name="cpf" data-mask="cpf" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createEmail" class="form-label">E-mail *</label>
                            <input type="email" class="form-control" id="createEmail" name="email" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createTelefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="createTelefone" name="telefone" data-mask="phone">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createCns" class="form-label">CNS Profissional</label>
                            <input type="text" class="form-control" id="createCns" name="cns_profissional">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createPerfil" class="form-label">Perfil *</label>
                            <select class="form-select" id="createPerfil" name="id_perfil" required>
                                <option value="">Selecione...</option>
                                <option value="1">Nacional</option>
                                <option value="2">Regional</option>
                                <option value="3">Municipal</option>
                                <option value="4">Unidade</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createMunicipio" class="form-label">Município</label>
                            <select class="form-select" id="createMunicipio" name="ibge">
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createCnes" class="form-label">CNES</label>
                            <input type="text" class="form-control" id="createCnes" name="cnes">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="createIne" class="form-label">INE</label>
                            <input type="text" class="form-control" id="createIne" name="ine">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="createEnviarEmail" name="enviar_email" checked>
                                <label class="form-check-label" for="createEnviarEmail">
                                    Enviar credenciais por e-mail
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>
                    Editar Usuário
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="editUserForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <!-- Conteúdo será carregado dinamicamente -->
                    <div id="editUserContent">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let usersTable;

document.addEventListener('DOMContentLoaded', function() {
    initUsersTable();
    loadUserStats();
    loadMunicipalities();
    
    // Event listeners
    document.getElementById('filterForm').addEventListener('submit', handleFilterSubmit);
    document.getElementById('createUserForm').addEventListener('submit', handleCreateUser);
    document.getElementById('editUserForm').addEventListener('submit', handleEditUser);
    
    // Máscara para telefone
    document.querySelectorAll('input[data-mask="phone"]').forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            }
            this.value = value;
        });
    });
});

function initUsersTable() {
    usersTable = $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/api/users/list',
            type: 'POST',
            data: function(d) {
                const formData = new FormData(document.getElementById('filterForm'));
                for (let [key, value] of formData.entries()) {
                    d[key] = value;
                }
                d.csrf_token = window.APP_CONFIG.csrfToken;
                return d;
            },
            error: function(xhr) {
                showAlert('Erro ao carregar usuários. Tente novamente.', 'danger');
            }
        },
        columns: [
            {
                data: 'foto',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    if (data) {
                        return `<img src="${data}" class="rounded-circle" width="40" height="40" alt="Avatar">`;
                    } else {
                        const initial = row.nome ? row.nome.charAt(0).toUpperCase() : 'U';
                        return `<div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;font-weight:600;">${initial}</div>`;
                    }
                }
            },
            { data: 'nome' },
            {
                data: 'cpf',
                render: function(data) {
                    return data.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                }
            },
            { data: 'email' },
            { data: 'perfil' },
            { data: 'municipio' },
            {
                data: 'ativo',
                render: function(data, type, row) {
                    if (row.status === 'pending') {
                        return '<span class="badge bg-warning">Aguardando</span>';
                    }
                    return data ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>';
                }
            },
            {
                data: 'dt_ultimo_acesso',
                render: function(data) {
                    if (!data) return '<span class="text-muted">Nunca</span>';
                    const date = new Date(data);
                    return date.toLocaleDateString('pt-BR') + '<br><small class="text-muted">' + date.toLocaleTimeString('pt-BR') + '</small>';
                }
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = '<div class="btn-group btn-group-sm">';
                    
                    if (<?php echo in_array('users.edit', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-primary" onclick="editUser(${data})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>`;
                    }
                    
                    if (<?php echo in_array('users.delete', $permissions) ? 'true' : 'false'; ?>) {
                        actions += `<button class="btn btn-outline-danger" onclick="deleteUser(${data}, '${row.nome}')" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>`;
                    }
                    
                    actions += `<button class="btn btn-outline-info" onclick="viewUser(${data})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>`;
                    
                    actions += '</div>';
                    return actions;
                }
            }
        ],
        order: [[1, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        }
    });
}

function loadUserStats() {
    fetch('/api/users/stats', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('activeUsersCount').textContent = data.stats.active || 0;
            document.getElementById('inactiveUsersCount').textContent = data.stats.inactive || 0;
            document.getElementById('totalUsersCount').textContent = data.stats.total || 0;
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
            const selects = ['municipality', 'createMunicipio'];
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

function handleFilterSubmit(e) {
    e.preventDefault();
    usersTable.ajax.reload();
}

function clearFilters() {
    document.getElementById('filterForm').reset();
    usersTable.ajax.reload();
}

function handleCreateUser(e) {
    e.preventDefault();
    
    // Limpar mensagens de erro
    document.querySelectorAll('#createUserForm .is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    
    const formData = new FormData(this);
    
    fetch('/api/users/create', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Usuário criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
            this.reset();
            usersTable.ajax.reload();
            loadUserStats();
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    const input = document.getElementById('create' + field.charAt(0).toUpperCase() + field.slice(1));
                    if (input) {
                        input.classList.add('is-invalid');
                        input.nextElementSibling.textContent = data.errors[field];
                    }
                });
            } else {
                showAlert(data.message || 'Erro ao criar usuário', 'danger');
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão. Tente novamente.', 'danger');
    });
}

function editUser(userId) {
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    document.getElementById('editUserId').value = userId;
    
    // Carregar dados do usuário
    fetch(`/api/users/${userId}`, {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Popular formulário com os dados
            const content = document.getElementById('editUserContent');
            content.innerHTML = generateEditForm(data.user);
            modal.show();
        } else {
            showAlert('Erro ao carregar dados do usuário', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão', 'danger');
    });
}

function generateEditForm(user) {
    return `
        <div class="row g-3">
            <div class="col-md-12">
                <label for="editNome" class="form-label">Nome Completo *</label>
                <input type="text" class="form-control" id="editNome" name="nome" value="${user.nome || ''}" required>
                <div class="invalid-feedback"></div>
            </div>
            
            <div class="col-md-6">
                <label for="editCpf" class="form-label">CPF *</label>
                <input type="text" class="form-control" id="editCpf" name="cpf" value="${user.cpf || ''}" data-mask="cpf" required readonly>
                <div class="invalid-feedback"></div>
            </div>
            
            <div class="col-md-6">
                <label for="editEmail" class="form-label">E-mail *</label>
                <input type="email" class="form-control" id="editEmail" name="email" value="${user.email || ''}" required>
                <div class="invalid-feedback"></div>
            </div>
            
            <div class="col-md-6">
                <label for="editTelefone" class="form-label">Telefone</label>
                <input type="text" class="form-control" id="editTelefone" name="telefone" value="${user.telefone || ''}" data-mask="phone">
                <div class="invalid-feedback"></div>
            </div>
            
            <div class="col-md-6">
                <label for="editAtivo" class="form-label">Status</label>
                <select class="form-select" id="editAtivo" name="ativo">
                    <option value="1" ${user.ativo ? 'selected' : ''}>Ativo</option>
                    <option value="0" ${!user.ativo ? 'selected' : ''}>Inativo</option>
                </select>
            </div>
        </div>
    `;
}

function handleEditUser(e) {
    e.preventDefault();
    
    const userId = document.getElementById('editUserId').value;
    const formData = new FormData(this);
    
    fetch(`/api/users/${userId}`, {
        method: 'PUT',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Usuário atualizado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            usersTable.ajax.reload();
        } else {
            showAlert(data.message || 'Erro ao atualizar usuário', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro de conexão', 'danger');
    });
}

function deleteUser(userId, userName) {
    if (confirm(`Tem certeza que deseja excluir o usuário "${userName}"?`)) {
        fetch(`/api/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.APP_CONFIG.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Usuário excluído com sucesso!', 'success');
                usersTable.ajax.reload();
                loadUserStats();
            } else {
                showAlert(data.message || 'Erro ao excluir usuário', 'danger');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro de conexão', 'danger');
        });
    }
}

function viewUser(userId) {
    window.open(`/users/${userId}/view`, '_blank');
}
</script>

<?php
$content = ob_get_clean();

// Incluir layout principal
include __DIR__ . '/../layouts/app.php';
?>