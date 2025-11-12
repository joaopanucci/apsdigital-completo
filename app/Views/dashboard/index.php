<?php
// Verificar autenticação
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';
AuthMiddleware::checkAuth();

// Dados do usuário logado
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_profile = $_SESSION['current_profile_name'] ?? 'Usuário';
$current_profile_id = $_SESSION['current_profile_id'] ?? null;
$permissions = $_SESSION['permissions'] ?? [];
$csrf_token = $_SESSION['csrf_token'];

// Configurações da página
$title = 'Dashboard - APS Digital';
$current_page = 'dashboard';

// Simular dados de estatísticas (normalmente vem do DashboardController)
$user_profiles = $_SESSION['user_profiles'] ?? [];
$notifications = [];
$notifications_count = 0;

// Buffer do conteúdo da página
ob_start();
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </h1>
        <p class="page-subtitle">
            Bem-vindo(a), <?php echo htmlspecialchars($user_name); ?>! 
            Perfil atual: <strong><?php echo htmlspecialchars($user_profile); ?></strong>
        </p>
    </div>
    
    <div class="page-actions">
        <?php if (count($user_profiles) > 1): ?>
            <a href="/profile/select" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-users-cog me-1"></i>
                Trocar Perfil
            </a>
        <?php endif; ?>
        
        <button class="btn btn-primary btn-sm" onclick="refreshDashboard()">
            <i class="fas fa-sync-alt me-1"></i>
            Atualizar
        </button>
    </div>
</div>

<!-- Alerts Container -->
<div id="alert-container"></div>

<!-- Statistics Cards -->
<div class="row mb-4" id="statsCards">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Usuários Ativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeUsers">
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

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Equipamentos Ativos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeEquipments">
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pendências
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingItems">
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
                            Relatórios Gerados
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="generatedReports">
                            <span class="spinner-border spinner-border-sm text-info"></span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-bar fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-bolt"></i>
                    Ações Rápidas
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3" id="quickActions">
                    <?php if (in_array('users.view', $permissions)): ?>
                        <div class="col-md-6 col-lg-4">
                            <a href="/users" class="text-decoration-none">
                                <div class="card bg-primary text-white h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h6 class="card-title">Gerenciar Usuários</h6>
                                        <p class="card-text small">Visualizar, criar e editar usuários</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('equipment.view', $permissions)): ?>
                        <div class="col-md-6 col-lg-4">
                            <a href="/equipment" class="text-decoration-none">
                                <div class="card bg-success text-white h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-tablet-alt fa-3x mb-3"></i>
                                        <h6 class="card-title">Equipamentos</h6>
                                        <p class="card-text small">Gerenciar tablets e chips</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('reports.view', $permissions)): ?>
                        <div class="col-md-6 col-lg-4">
                            <a href="/reports" class="text-decoration-none">
                                <div class="card bg-info text-white h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                        <h6 class="card-title">Relatórios</h6>
                                        <p class="card-text small">Gerar e exportar relatórios</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('users.authorize', $permissions)): ?>
                        <div class="col-md-6 col-lg-4">
                            <a href="/users/authorize" class="text-decoration-none">
                                <div class="card bg-warning text-dark h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-check fa-3x mb-3"></i>
                                        <h6 class="card-title">Autorizar Usuários</h6>
                                        <p class="card-text small">Aprovar cadastros pendentes</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('equipment.authorize', $permissions)): ?>
                        <div class="col-md-6 col-lg-4">
                            <a href="/equipment/authorize" class="text-decoration-none">
                                <div class="card bg-secondary text-white h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-truck fa-3x mb-3"></i>
                                        <h6 class="card-title">Autorizar Entregas</h6>
                                        <p class="card-text small">Aprovar entregas de equipamentos</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-6 col-lg-4">
                        <a href="/profile" class="text-decoration-none">
                            <div class="card bg-dark text-white h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-cog fa-3x mb-3"></i>
                                    <h6 class="card-title">Meu Perfil</h6>
                                    <p class="card-text small">Alterar dados e configurações</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-history"></i>
                    Atividades Recentes
                </h5>
            </div>
            <div class="card-body">
                <div id="recentActivity">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2 text-muted">Carregando atividades...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    Usuários por Mês
                </h5>
            </div>
            <div class="card-body">
                <canvas id="usersChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-chart-pie"></i>
                    Equipamentos por Status
                </h5>
            </div>
            <div class="card-body">
                <canvas id="equipmentChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar dashboard
    initDashboard();
});

function initDashboard() {
    loadDashboardStats();
    loadRecentActivity();
    initCharts();
}

function loadDashboardStats() {
    fetch('/api/dashboard/stats', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('activeUsers').textContent = data.stats.active_users || 0;
            document.getElementById('activeEquipments').textContent = data.stats.active_equipments || 0;
            document.getElementById('pendingItems').textContent = data.stats.pending_items || 0;
            document.getElementById('generatedReports').textContent = data.stats.generated_reports || 0;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar estatísticas:', error);
        // Exibir valores padrão em caso de erro
        document.getElementById('activeUsers').textContent = '-';
        document.getElementById('activeEquipments').textContent = '-';
        document.getElementById('pendingItems').textContent = '-';
        document.getElementById('generatedReports').textContent = '-';
    });
}

function loadRecentActivity() {
    fetch('/api/dashboard/activity', {
        method: 'GET',
        headers: {
            'X-CSRF-Token': window.APP_CONFIG.csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('recentActivity');
        
        if (data.success && data.activities && data.activities.length > 0) {
            let html = '<div class="list-group list-group-flush">';
            
            data.activities.forEach(activity => {
                const timeAgo = formatTimeAgo(activity.created_at);
                const icon = getActivityIcon(activity.action);
                
                html += `
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <i class="fas ${icon} text-muted me-3"></i>
                            <div class="flex-grow-1">
                                <p class="mb-1 small">${activity.description}</p>
                                <small class="text-muted">${timeAgo}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-history fa-2x mb-2 opacity-50"></i>
                    <p>Nenhuma atividade recente</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar atividades:', error);
        document.getElementById('recentActivity').innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
                <p>Erro ao carregar atividades</p>
            </div>
        `;
    });
}

function initCharts() {
    // Gráfico de usuários por mês
    const usersCtx = document.getElementById('usersChart');
    if (usersCtx) {
        const usersChart = new Chart(usersCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Usuários Cadastrados',
                    data: [10, 15, 25, 30, 45, 60],
                    borderColor: '#004F9F',
                    backgroundColor: 'rgba(0, 79, 159, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Gráfico de equipamentos por status
    const equipmentCtx = document.getElementById('equipmentChart');
    if (equipmentCtx) {
        const equipmentChart = new Chart(equipmentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ativos', 'Em Manutenção', 'Disponíveis', 'Defeituosos'],
                datasets: [{
                    data: [150, 20, 80, 15],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#17a2b8',
                        '#dc3545'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

function refreshDashboard() {
    showAlert('Atualizando dashboard...', 'info');
    
    // Recarregar todos os componentes
    loadDashboardStats();
    loadRecentActivity();
    
    setTimeout(() => {
        showAlert('Dashboard atualizado!', 'success');
    }, 1000);
}

function getActivityIcon(action) {
    const icons = {
        'login': 'fa-sign-in-alt',
        'logout': 'fa-sign-out-alt',
        'create': 'fa-plus-circle',
        'update': 'fa-edit',
        'delete': 'fa-trash-alt',
        'authorize': 'fa-check-circle',
        'report': 'fa-file-alt'
    };
    
    return icons[action] || 'fa-info-circle';
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInMinutes = Math.floor((now - date) / (1000 * 60));
    
    if (diffInMinutes < 1) return 'Agora mesmo';
    if (diffInMinutes < 60) return `${diffInMinutes} min atrás`;
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h atrás`;
    
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d atrás`;
    
    return date.toLocaleDateString('pt-BR');
}
</script>

<?php
$content = ob_get_clean();

// Incluir layout principal
include __DIR__ . '/../layouts/app.php';
?>