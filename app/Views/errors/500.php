<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro do Servidor - APS Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
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
            color: #6c757d;
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
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h1 class="display-4 fw-bold text-secondary mb-3">500</h1>
                            <h2 class="h4 mb-3">Erro Interno do Servidor</h2>
                            <p class="lead text-muted mb-4">
                                Ocorreu um erro interno no servidor. Nossa equipe técnica foi notificada 
                                e está trabalhando para resolver o problema o mais rápido possível.
                            </p>
                            
                            <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                                <a href="/" class="btn btn-primary btn-lg">
                                    <i class="fas fa-home me-2"></i>
                                    Voltar ao Início
                                </a>
                                <button onclick="location.reload()" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    Tentar Novamente
                                </button>
                            </div>

                            <hr class="my-4">
                            
                            <div class="row text-start">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-clock text-warning me-2"></i>O que fazer agora?</h5>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success me-2"></i>Aguarde alguns minutos</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Recarregue a página</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Tente acessar novamente</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="fas fa-headset text-info me-2"></i>Suporte Técnico</h5>
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

                            <div class="alert alert-info mt-4" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Código do Erro:</strong> 500 - Internal Server Error<br>
                                <strong>Horário:</strong> <?= date('d/m/Y H:i:s') ?>
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