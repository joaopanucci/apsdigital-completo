/* ==========================================================================
   APS Digital - Dashboard JavaScript Module
   Secretaria de Estado de Saúde de Mato Grosso do Sul (SES-MS)
   ========================================================================== */

(function() {
    'use strict';

    // Wait for main app to be available
    if (typeof APSDigital === 'undefined') {
        console.warn('APSDigital not found, retrying...');
        setTimeout(arguments.callee, 100);
        return;
    }

    const APP = window.APSDigital;

    // ==========================================================================
    // 1. Dashboard Module
    // ==========================================================================

    APP.modules.Dashboard = {
        config: {
            refreshInterval: 30000, // 30 seconds
            animationDuration: 1000,
            chartColors: {
                primary: '#004F9F',
                secondary: '#2a80dc',
                success: '#28a745',
                warning: '#ffc107',
                danger: '#dc3545',
                info: '#17a2b8'
            },
            endpoints: {
                stats: '/api/dashboard/stats',
                charts: '/api/dashboard/charts',
                notifications: '/api/dashboard/notifications',
                activities: '/api/dashboard/activities'
            }
        },

        charts: {},
        refreshTimer: null,

        /**
         * Initialize Dashboard Module
         */
        init: function() {
            console.log('Dashboard Module - Initializing...');
            
            this.loadInitialData();
            this.setupCharts();
            this.setupRealTimeUpdates();
            this.setupEventHandlers();
            this.setupNotifications();
            this.startAutoRefresh();
            
            console.log('Dashboard Module - Initialized');
        },

        /**
         * Load Initial Dashboard Data
         */
        loadInitialData: function() {
            this.loadStats();
            this.loadChartData();
            this.loadRecentActivities();
            this.loadNotifications();
        },

        /**
         * Load Statistics Cards
         */
        loadStats: function() {
            APP.utils.ajax({
                url: this.config.endpoints.stats,
                method: 'GET'
            })
            .then(response => {
                if (response.success) {
                    this.updateStatsCards(response.data);
                }
            })
            .catch(error => {
                console.error('Error loading stats:', error);
            });
        },

        /**
         * Update Statistics Cards with Animation
         */
        updateStatsCards: function(stats) {
            Object.keys(stats).forEach(key => {
                const card = document.querySelector(`[data-stat="${key}"]`);
                if (card) {
                    const valueElement = card.querySelector('.stat-value');
                    const changeElement = card.querySelector('.stat-change');
                    
                    if (valueElement) {
                        this.animateNumber(
                            valueElement, 
                            parseInt(valueElement.textContent.replace(/\D/g, '')) || 0,
                            stats[key].value
                        );
                    }

                    if (changeElement && stats[key].change !== undefined) {
                        const change = stats[key].change;
                        const isPositive = change >= 0;
                        
                        changeElement.innerHTML = `
                            <i class="fas fa-${isPositive ? 'arrow-up' : 'arrow-down'} me-1"></i>
                            ${Math.abs(change)}%
                        `;
                        changeElement.className = `stat-change ${isPositive ? 'text-success' : 'text-danger'}`;
                    }
                }
            });
        },

        /**
         * Animate Number Counter
         */
        animateNumber: function(element, start, end) {
            const duration = this.config.animationDuration;
            const startTime = Date.now();
            
            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function (ease-out)
                const easeOut = 1 - Math.pow(1 - progress, 3);
                
                const current = Math.round(start + (end - start) * easeOut);
                element.textContent = this.formatStatValue(current);
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            
            animate();
        },

        /**
         * Format Statistical Value
         */
        formatStatValue: function(value) {
            if (value >= 1000000) {
                return (value / 1000000).toFixed(1) + 'M';
            } else if (value >= 1000) {
                return (value / 1000).toFixed(1) + 'K';
            }
            return value.toLocaleString('pt-BR');
        },

        /**
         * Setup Charts
         */
        setupCharts: function() {
            this.setupEquipmentChart();
            this.setupHealthFormsChart();
            this.setupMunicipalityChart();
            this.setupTrendChart();
        },

        /**
         * Setup Equipment Distribution Chart
         */
        setupEquipmentChart: function() {
            const canvas = document.getElementById('equipmentChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            this.charts.equipment = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Tablets Ativos', 'Tablets Inativos', 'Chips Ativos', 'Chips Inativos'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            this.config.chartColors.success,
                            this.config.chartColors.danger,
                            this.config.chartColors.info,
                            this.config.chartColors.warning
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
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Setup Health Forms Chart
         */
        setupHealthFormsChart: function() {
            const canvas = document.getElementById('healthFormsChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            this.charts.healthForms = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Formulários Preenchidos',
                        data: [],
                        backgroundColor: this.config.chartColors.primary,
                        borderColor: this.config.chartColors.primary,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        /**
         * Setup Municipality Chart
         */
        setupMunicipalityChart: function() {
            const canvas = document.getElementById('municipalityChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            this.charts.municipality = new Chart(ctx, {
                type: 'horizontalBar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Unidades Ativas',
                        data: [],
                        backgroundColor: this.config.chartColors.secondary,
                        borderColor: this.config.chartColors.secondary,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        /**
         * Setup Trend Chart
         */
        setupTrendChart: function() {
            const canvas = document.getElementById('trendChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            this.charts.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Formulários',
                            data: [],
                            borderColor: this.config.chartColors.primary,
                            backgroundColor: this.config.chartColors.primary + '20',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Usuários Ativos',
                            data: [],
                            borderColor: this.config.chartColors.success,
                            backgroundColor: this.config.chartColors.success + '20',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        },

        /**
         * Load Chart Data
         */
        loadChartData: function() {
            APP.utils.ajax({
                url: this.config.endpoints.charts,
                method: 'GET'
            })
            .then(response => {
                if (response.success) {
                    this.updateCharts(response.data);
                }
            })
            .catch(error => {
                console.error('Error loading chart data:', error);
            });
        },

        /**
         * Update All Charts
         */
        updateCharts: function(data) {
            // Update Equipment Chart
            if (data.equipment && this.charts.equipment) {
                this.charts.equipment.data.datasets[0].data = [
                    data.equipment.tablets_active,
                    data.equipment.tablets_inactive,
                    data.equipment.chips_active,
                    data.equipment.chips_inactive
                ];
                this.charts.equipment.update();
            }

            // Update Health Forms Chart
            if (data.health_forms && this.charts.healthForms) {
                this.charts.healthForms.data.labels = data.health_forms.labels;
                this.charts.healthForms.data.datasets[0].data = data.health_forms.data;
                this.charts.healthForms.update();
            }

            // Update Municipality Chart
            if (data.municipalities && this.charts.municipality) {
                this.charts.municipality.data.labels = data.municipalities.labels;
                this.charts.municipality.data.datasets[0].data = data.municipalities.data;
                this.charts.municipality.update();
            }

            // Update Trend Chart
            if (data.trends && this.charts.trend) {
                this.charts.trend.data.labels = data.trends.labels;
                this.charts.trend.data.datasets[0].data = data.trends.forms;
                this.charts.trend.data.datasets[1].data = data.trends.users;
                this.charts.trend.update();
            }
        },

        /**
         * Load Recent Activities
         */
        loadRecentActivities: function() {
            APP.utils.ajax({
                url: this.config.endpoints.activities,
                method: 'GET'
            })
            .then(response => {
                if (response.success) {
                    this.updateActivitiesList(response.data);
                }
            })
            .catch(error => {
                console.error('Error loading activities:', error);
            });
        },

        /**
         * Update Activities List
         */
        updateActivitiesList: function(activities) {
            const container = document.getElementById('recent-activities');
            if (!container) return;

            if (activities.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Nenhuma atividade recente</p>
                    </div>
                `;
                return;
            }

            const html = activities.map(activity => `
                <div class="activity-item d-flex align-items-start mb-3">
                    <div class="activity-icon me-3">
                        <i class="fas fa-${this.getActivityIcon(activity.type)} text-${this.getActivityColor(activity.type)}"></i>
                    </div>
                    <div class="activity-content flex-grow-1">
                        <div class="activity-title">${activity.title}</div>
                        <div class="activity-description text-muted">${activity.description}</div>
                        <div class="activity-time">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                ${APP.utils.formatDate(activity.created_at, 'dd/MM/yyyy HH:mm')}
                            </small>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        },

        /**
         * Get Activity Icon
         */
        getActivityIcon: function(type) {
            const icons = {
                'user_login': 'sign-in-alt',
                'form_submitted': 'file-alt',
                'equipment_assigned': 'tablet-alt',
                'user_created': 'user-plus',
                'report_generated': 'chart-bar',
                'system_update': 'sync-alt'
            };
            return icons[type] || 'info-circle';
        },

        /**
         * Get Activity Color
         */
        getActivityColor: function(type) {
            const colors = {
                'user_login': 'success',
                'form_submitted': 'primary',
                'equipment_assigned': 'info',
                'user_created': 'success',
                'report_generated': 'warning',
                'system_update': 'secondary'
            };
            return colors[type] || 'muted';
        },

        /**
         * Load Notifications
         */
        loadNotifications: function() {
            APP.utils.ajax({
                url: this.config.endpoints.notifications,
                method: 'GET'
            })
            .then(response => {
                if (response.success) {
                    this.updateNotifications(response.data);
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
        },

        /**
         * Update Notifications
         */
        updateNotifications: function(notifications) {
            const badge = document.querySelector('.notification-badge');
            const dropdown = document.querySelector('.notification-dropdown');

            if (badge) {
                if (notifications.unread > 0) {
                    badge.textContent = notifications.unread > 99 ? '99+' : notifications.unread;
                    badge.style.display = 'inline';
                } else {
                    badge.style.display = 'none';
                }
            }

            if (dropdown && notifications.items) {
                const html = notifications.items.length > 0 
                    ? notifications.items.map(notification => `
                        <a class="dropdown-item ${notification.read ? '' : 'fw-bold'}" href="#" 
                           data-notification-id="${notification.id}">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-${this.getNotificationIcon(notification.type)} text-${this.getNotificationColor(notification.type)}"></i>
                                </div>
                                <div class="flex-grow-1 ms-2">
                                    <div class="notification-title">${notification.title}</div>
                                    <div class="notification-text text-muted small">${notification.message}</div>
                                    <div class="notification-time text-muted small">
                                        ${APP.utils.formatDate(notification.created_at, 'dd/MM/yyyy HH:mm')}
                                    </div>
                                </div>
                            </div>
                        </a>
                    `).join('')
                    : '<div class="dropdown-item text-center text-muted">Nenhuma notificação</div>';

                dropdown.innerHTML = html;
            }
        },

        /**
         * Get Notification Icon
         */
        getNotificationIcon: function(type) {
            const icons = {
                'system': 'cog',
                'warning': 'exclamation-triangle',
                'info': 'info-circle',
                'success': 'check-circle',
                'error': 'times-circle'
            };
            return icons[type] || 'bell';
        },

        /**
         * Get Notification Color
         */
        getNotificationColor: function(type) {
            const colors = {
                'system': 'primary',
                'warning': 'warning',
                'info': 'info',
                'success': 'success',
                'error': 'danger'
            };
            return colors[type] || 'primary';
        },

        /**
         * Setup Real-time Updates
         */
        setupRealTimeUpdates: function() {
            // Listen for server-sent events if available
            if (typeof EventSource !== 'undefined') {
                const eventSource = new EventSource('/api/dashboard/events');
                
                eventSource.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleRealTimeUpdate(data);
                };

                eventSource.onerror = (error) => {
                    console.warn('EventSource error:', error);
                    eventSource.close();
                };

                // Store reference for cleanup
                this.eventSource = eventSource;
            }
        },

        /**
         * Handle Real-time Update
         */
        handleRealTimeUpdate: function(data) {
            switch (data.type) {
                case 'stats_update':
                    this.updateStatsCards(data.stats);
                    break;
                case 'new_notification':
                    this.addNotification(data.notification);
                    break;
                case 'new_activity':
                    this.addActivity(data.activity);
                    break;
                case 'chart_update':
                    this.updateCharts(data.charts);
                    break;
            }
        },

        /**
         * Add New Notification
         */
        addNotification: function(notification) {
            // Update notification count
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                const current = parseInt(badge.textContent) || 0;
                badge.textContent = current + 1;
                badge.style.display = 'inline';
            }

            // Show toast
            APP.utils.showToast(notification.message, this.getNotificationColor(notification.type));
        },

        /**
         * Add New Activity
         */
        addActivity: function(activity) {
            const container = document.getElementById('recent-activities');
            if (!container) return;

            const activityHtml = `
                <div class="activity-item d-flex align-items-start mb-3 new-activity">
                    <div class="activity-icon me-3">
                        <i class="fas fa-${this.getActivityIcon(activity.type)} text-${this.getActivityColor(activity.type)}"></i>
                    </div>
                    <div class="activity-content flex-grow-1">
                        <div class="activity-title">${activity.title}</div>
                        <div class="activity-description text-muted">${activity.description}</div>
                        <div class="activity-time">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                ${APP.utils.formatDate(activity.created_at, 'dd/MM/yyyy HH:mm')}
                            </small>
                        </div>
                    </div>
                </div>
            `;

            // Add to top of list
            container.insertAdjacentHTML('afterbegin', activityHtml);

            // Remove highlight after animation
            setTimeout(() => {
                const newActivity = container.querySelector('.new-activity');
                if (newActivity) {
                    newActivity.classList.remove('new-activity');
                }
            }, 2000);

            // Keep only last 10 activities
            const activities = container.querySelectorAll('.activity-item');
            if (activities.length > 10) {
                activities[activities.length - 1].remove();
            }
        },

        /**
         * Setup Event Handlers
         */
        setupEventHandlers: function() {
            // Refresh button
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-action="refresh"]')) {
                    e.preventDefault();
                    this.refreshDashboard();
                }
            });

            // Export buttons
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-action="export"]')) {
                    e.preventDefault();
                    const type = e.target.getAttribute('data-type');
                    this.exportData(type);
                }
            });

            // Chart period selection
            document.addEventListener('change', (e) => {
                if (e.target.matches('[data-chart-period]')) {
                    const chartId = e.target.getAttribute('data-chart-period');
                    const period = e.target.value;
                    this.updateChartPeriod(chartId, period);
                }
            });
        },

        /**
         * Setup Notifications Event Handlers
         */
        setupNotifications: function() {
            // Mark notification as read
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-notification-id]')) {
                    e.preventDefault();
                    const notificationId = e.target.closest('[data-notification-id]').getAttribute('data-notification-id');
                    this.markNotificationAsRead(notificationId);
                }
            });
        },

        /**
         * Mark Notification as Read
         */
        markNotificationAsRead: function(notificationId) {
            APP.utils.ajax({
                url: `/api/notifications/${notificationId}/read`,
                method: 'POST'
            })
            .then(response => {
                if (response.success) {
                    const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notification) {
                        notification.classList.remove('fw-bold');
                    }

                    // Update badge count
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const current = parseInt(badge.textContent) || 0;
                        const newCount = Math.max(0, current - 1);
                        if (newCount === 0) {
                            badge.style.display = 'none';
                        } else {
                            badge.textContent = newCount;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        },

        /**
         * Refresh Dashboard
         */
        refreshDashboard: function() {
            const refreshBtn = document.querySelector('[data-action="refresh"]');
            
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i>';
                refreshBtn.disabled = true;
            }

            this.loadInitialData();

            setTimeout(() => {
                if (refreshBtn) {
                    refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                    refreshBtn.disabled = false;
                }
                APP.utils.showToast('Dashboard atualizado com sucesso!', 'success', 3000);
            }, 1000);
        },

        /**
         * Export Data
         */
        exportData: function(type) {
            const url = `/api/dashboard/export/${type}`;
            
            // Create temporary download link
            const link = document.createElement('a');
            link.href = url;
            link.download = `dashboard_${type}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            APP.utils.showToast(`Exportando dados de ${type}...`, 'info', 3000);
        },

        /**
         * Update Chart Period
         */
        updateChartPeriod: function(chartId, period) {
            APP.utils.ajax({
                url: `/api/dashboard/charts/${chartId}`,
                method: 'GET',
                data: { period: period }
            })
            .then(response => {
                if (response.success && this.charts[chartId]) {
                    const chart = this.charts[chartId];
                    chart.data = response.data;
                    chart.update();
                }
            })
            .catch(error => {
                console.error('Error updating chart period:', error);
            });
        },

        /**
         * Start Auto Refresh
         */
        startAutoRefresh: function() {
            this.refreshTimer = setInterval(() => {
                this.loadStats();
                this.loadNotifications();
            }, this.config.refreshInterval);
        },

        /**
         * Stop Auto Refresh
         */
        stopAutoRefresh: function() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        },

        /**
         * Cleanup
         */
        destroy: function() {
            this.stopAutoRefresh();
            
            if (this.eventSource) {
                this.eventSource.close();
            }

            // Destroy charts
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.destroy === 'function') {
                    chart.destroy();
                }
            });
        }
    };

    // ==========================================================================
    // 2. Auto-Initialize
    // ==========================================================================

    function init() {
        APP.modules.Dashboard.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (APP.modules.Dashboard) {
            APP.modules.Dashboard.destroy();
        }
    });

})();