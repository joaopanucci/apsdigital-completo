<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - APS Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .error-card {
            max-width: 600px;
            animation: slideInUp 0.6s ease-out;
        }
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card error-card shadow-lg">
                        <div class="card-body text-center p-5">
                            <div class="error-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h1 class="display-4 fw-bold text-danger mb-3">403</h1>
                            <h2 class="h4 mb-3">Acesso Proibido</h2>
                            <p class="lead text-muted mb-4">
                                Você não tem permissão para acessar este recurso. 
                                Entre em contato com o administrador do sistema se acredita que isso é um erro.
                            </p>
                            
                            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                                <a href="/" class="btn btn-primary btn-lg">
                                    <i class="fas fa-home me-2"></i>
                                    Voltar ao Início
                                </a>
                                <a href="/login" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Fazer Login
                                </a>
                            </div>

                            <hr class="my-4">
                            
                            <div class="row text-start">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-info-circle text-info me-2"></i>Possíveis Causas:</h5>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-times text-danger me-2"></i>Sessão expirada</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Permissões insuficientes</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Conta desativada</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="fas fa-headset text-info me-2"></i>Precisa de Ajuda?</h5>
                                    <p class="mb-2">
                                        <i class="fas fa-envelope me-2"></i>
                                        <a href="mailto:suporte@saude.ms.gov.br">suporte@saude.ms.gov.br</a>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-phone me-2"></i>
                                        (67) 3318-1000
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-light text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                <strong>APS Digital</strong> - Secretaria de Estado de Saúde de Mato Grosso do Sul
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>