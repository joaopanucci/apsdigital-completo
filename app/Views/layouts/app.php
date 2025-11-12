<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $title ?? 'APS Digital - SES/MS'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom App CSS -->
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <style>
        :root {
            --ses-primary: #004F9F;
            --ses-secondary: #2a80dc;
            --ses-success: #28a745;
            --ses-danger: #dc3545;
            --ses-warning: #ffc107;
            --ses-info: #17a2b8;
            --ses-light: #f8f9fa;
            --ses-dark: #343a40;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        /* Header */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #dee2e6;
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            color: var(--ses-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.25rem;
            margin-right: 2rem;
        }
        
        .header-brand:hover {
            color: var(--ses-secondary);
        }
        
        .header-brand i {
            margin-right: 0.5rem;
            font-size: 1.5rem;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--ses-primary);
            font-size: 1.25rem;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            margin-right: 1rem;
        }
        
        .sidebar-toggle:hover {
            background: var(--ses-light);
            color: var(--ses-secondary);
        }
        
        .header-nav {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--ses-dark);
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .user-dropdown .dropdown-toggle:hover {
            background: var(--ses-light);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--ses-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.75rem;
        }
        
        .user-info {
            text-align: left;
        }
        
        .user-name {
            font-weight: 500;
            font-size: 0.875rem;
            line-height: 1.2;
            margin: 0;
        }
        
        .user-profile {
            font-size: 0.75rem;
            color: #6c757d;
            margin: 0;
        }
        
        /* Sidebar */
        .main-sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: white;
            border-right: 1px solid #dee2e6;
            z-index: 1020;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .sidebar-collapsed .main-sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .menu-item {
            margin: 0.25rem 0;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--ses-dark);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .menu-link:hover {
            background: var(--ses-light);
            color: var(--ses-primary);
            border-left-color: var(--ses-secondary);
        }
        
        .menu-link.active {
            background: rgba(0, 79, 159, 0.1);
            color: var(--ses-primary);
            border-left-color: var(--ses-primary);
            font-weight: 500;
        }
        
        .menu-icon {
            width: 20px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1rem;
        }
        
        .menu-text {
            flex: 1;
        }
        
        .menu-badge {
            background: var(--ses-danger);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .menu-header {
            padding: 1rem 1.5rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 0.5px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 1.5rem;
            min-height: calc(100vh - var(--header-height));
            transition: margin-left 0.3s ease;
        }
        
        .sidebar-collapsed .main-content {
            margin-left: 0;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #dee2e6;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--ses-dark);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 0.75rem;
            color: var(--ses-primary);
        }
        
        .page-subtitle {
            color: #6c757d;
            margin: 0.5rem 0 0;
            font-size: 0.875rem;
        }
        
        .page-actions {
            margin-left: auto;
            display: flex;
            gap: 0.75rem;
        }
        
        /* Cards */
        .card {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #dee2e6;
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
        }
        
        .card-title {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--ses-dark);
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.5rem;
            color: var(--ses-primary);
        }
        
        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--ses-primary);
            border-color: var(--ses-primary);
        }
        
        .btn-primary:hover {
            background: var(--ses-secondary);
            border-color: var(--ses-secondary);
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        /* Forms */
        .form-control {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--ses-secondary);
            box-shadow: 0 0 0 0.2rem rgba(42, 128, 220, 0.15);
        }
        
        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar-open .main-sidebar {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .main-content {
                padding: 1rem;
            }
        }
        
        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .spinner-border {
            color: var(--ses-primary);
        }
        
        /* Custom scrollbar */
        .main-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .main-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .main-sidebar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .main-sidebar::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="/dashboard" class="header-brand">
            <i class="fas fa-hospital-alt"></i>
            APS Digital
        </a>
        
        <nav class="header-nav">
            <!-- Notificações -->
            <div class="dropdown">
                <button class="btn btn-light position-relative" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <?php if (isset($notifications_count) && $notifications_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $notifications_count; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Notificações</h6></li>
                    <?php if (isset($notifications) && !empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><span class="dropdown-item text-muted">Nenhuma notificação</span></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Usuário -->
            <div class="dropdown user-dropdown">
                <button class="dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <?php if (isset($user_photo) && $user_photo): ?>
                            <img src="<?php echo htmlspecialchars($user_photo); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_name ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info d-none d-md-block">
                        <p class="user-name"><?php echo htmlspecialchars($user_name ?? 'Usuário'); ?></p>
                        <p class="user-profile"><?php echo htmlspecialchars($user_profile ?? 'Perfil'); ?></p>
                    </div>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/profile">
                        <i class="fas fa-user-circle me-2"></i> Meu Perfil
                    </a></li>
                    <?php if (isset($user_profiles) && count($user_profiles) > 1): ?>
                        <li><a class="dropdown-item" href="/profile/select">
                            <i class="fas fa-users-cog me-2"></i> Trocar Perfil
                        </a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/settings">
                        <i class="fas fa-cog me-2"></i> Configurações
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/auth/logout">
                        <i class="fas fa-sign-out-alt me-2"></i> Sair
                    </a></li>
                </ul>
            </div>
        </nav>
    </header>

    <!-- Sidebar -->
    <aside class="main-sidebar">
        <nav class="sidebar-menu">
            <li class="menu-header">Principal</li>
            <li class="menu-item">
                <a href="/dashboard" class="menu-link <?php echo ($current_page ?? '') === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            
            <?php if (isset($permissions) && in_array('users.view', $permissions)): ?>
                <li class="menu-header">Usuários</li>
                <li class="menu-item">
                    <a href="/users" class="menu-link <?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users menu-icon"></i>
                        <span class="menu-text">Gerenciar Usuários</span>
                        <?php if (isset($pending_users_count) && $pending_users_count > 0): ?>
                            <span class="menu-badge"><?php echo $pending_users_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if (in_array('users.authorize', $permissions)): ?>
                    <li class="menu-item">
                        <a href="/users/authorize" class="menu-link <?php echo ($current_page ?? '') === 'users_authorize' ? 'active' : ''; ?>">
                            <i class="fas fa-user-check menu-icon"></i>
                            <span class="menu-text">Autorizar Usuários</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($permissions) && in_array('equipment.view', $permissions)): ?>
                <li class="menu-header">Equipamentos</li>
                <li class="menu-item">
                    <a href="/equipment" class="menu-link <?php echo ($current_page ?? '') === 'equipment' ? 'active' : ''; ?>">
                        <i class="fas fa-tablet-alt menu-icon"></i>
                        <span class="menu-text">Tablets e Chips</span>
                    </a>
                </li>
                <?php if (in_array('equipment.authorize', $permissions)): ?>
                    <li class="menu-item">
                        <a href="/equipment/authorize" class="menu-link <?php echo ($current_page ?? '') === 'equipment_authorize' ? 'active' : ''; ?>">
                            <i class="fas fa-truck menu-icon"></i>
                            <span class="menu-text">Autorizar Entregas</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($permissions) && in_array('reports.view', $permissions)): ?>
                <li class="menu-header">Relatórios</li>
                <li class="menu-item">
                    <a href="/reports" class="menu-link <?php echo ($current_page ?? '') === 'reports' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar menu-icon"></i>
                        <span class="menu-text">Relatórios Gerais</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="/reports/health" class="menu-link <?php echo ($current_page ?? '') === 'health_reports' ? 'active' : ''; ?>">
                        <i class="fas fa-heartbeat menu-icon"></i>
                        <span class="menu-text">Saúde da Mulher</span>
                    </a>
                </li>
                <?php if (in_array('reports.executive', $permissions)): ?>
                    <li class="menu-item">
                        <a href="/reports/executive" class="menu-link <?php echo ($current_page ?? '') === 'executive_reports' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line menu-icon"></i>
                            <span class="menu-text">Dashboard Executivo</span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            
            <li class="menu-header">Análise Regional</li>
            <li class="menu-item">
                <a href="/mapas" class="menu-link <?php echo ($current_page ?? '') === 'mapas' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marked-alt menu-icon"></i>
                    <span class="menu-text">Mapas Regionais</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="/indicadores" class="menu-link <?php echo ($current_page ?? '') === 'indicadores' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-area menu-icon"></i>
                    <span class="menu-text">Indicadores de Saúde</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="/indicadores/dashboard" class="menu-link <?php echo ($current_page ?? '') === 'indicadores_dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-dashboard menu-icon"></i>
                    <span class="menu-text">Dashboard Regional</span>
                </a>
            </li>
            
            <li class="menu-header">Sistema</li>
            <li class="menu-item">
                <a href="/help" class="menu-link">
                    <i class="fas fa-question-circle menu-icon"></i>
                    <span class="menu-text">Ajuda</span>
                </a>
            </li>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Alert Container -->
        <div id="alert-container"></div>
        
        <?php echo $content; ?>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay d-none" id="loadingOverlay">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom App JS -->
    <script src="/assets/js/app.js"></script>
    
    <script>
        // Configuração global
        window.APP_CONFIG = {
            baseUrl: '<?php echo $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"]; ?>',
            csrfToken: '<?php echo $csrf_token ?? ""; ?>'
        };
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.body.classList.toggle('sidebar-collapsed');
                    
                    // Salvar estado no localStorage
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sidebar-collapsed', isCollapsed);
                });
            }
            
            // Restaurar estado do sidebar
            const savedState = localStorage.getItem('sidebar-collapsed');
            if (savedState === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }
            
            // Responsivo: fechar sidebar em mobile
            if (window.innerWidth <= 768) {
                document.body.classList.add('sidebar-collapsed');
            }
            
            // DataTables configuração padrão
            if (typeof $.fn.DataTable !== 'undefined') {
                $.extend(true, $.fn.dataTable.defaults, {
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    pageLength: 25,
                    responsive: true,
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    order: [[0, 'desc']]
                });
            }
        });
        
        // Função para exibir alertas
        function showAlert(message, type = 'info', timeout = 5000) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            const container = document.getElementById('alert-container');
            if (container) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = alertHtml;
                container.appendChild(tempDiv.firstElementChild);
                
                if (timeout > 0) {
                    setTimeout(() => {
                        const alert = container.querySelector('.alert:last-child');
                        if (alert) {
                            alert.remove();
                        }
                    }, timeout);
                }
            }
        }
        
        // Função para exibir loading
        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('d-none');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('d-none');
        }
        
        // Configuração AJAX global
        if (typeof $ !== 'undefined') {
            $.ajaxSetup({
                beforeSend: function(xhr) {
                    showLoading();
                    xhr.setRequestHeader('X-CSRF-Token', window.APP_CONFIG.csrfToken);
                },
                complete: function() {
                    hideLoading();
                },
                error: function(xhr) {
                    if (xhr.status === 403) {
                        showAlert('Acesso negado. Você não tem permissão para esta ação.', 'danger');
                    } else if (xhr.status === 401) {
                        showAlert('Sessão expirada. Redirecionando para login...', 'warning');
                        setTimeout(() => window.location.href = '/auth/login', 2000);
                    } else {
                        showAlert('Erro interno do servidor. Tente novamente.', 'danger');
                    }
                }
            });
        }
        
        // Verificar mensagens de URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('message')) {
            const messageType = urlParams.get('message');
            const messages = {
                'success': { text: 'Operação realizada com sucesso!', type: 'success' },
                'error': { text: 'Ocorreu um erro. Tente novamente.', type: 'danger' },
                'permission_denied': { text: 'Acesso negado.', type: 'danger' },
                'not_found': { text: 'Registro não encontrado.', type: 'warning' }
            };
            
            const message = messages[messageType];
            if (message) {
                setTimeout(() => showAlert(message.text, message.type), 500);
            }
        }
    </script>
</body>
</html>