# PROMPT.md - Reestruturação Completa do Sistema APSDigital

## Objetivo
Reescrever completamente o sistema APSDigital do zero, criando uma aplicação moderna, segura, modular e profissional para a Secretaria de Estado de Saúde de Mato Grosso do Sul (SES-MS).

## Contexto Atual - Análise do Sistema Existente

### Sistema Atual (Problemas Identificados):
- **Arquitetura**: PHP procedural sem padrão MVC
- **Segurança**: Vulnerável a SQL Injection, XSS, CSRF
- **Código**: Repetitivo, sem reutilização, mistura de HTML/PHP/CSS
- **Interface**: Bootstrap 4 desatualizado, não responsivo adequadamente
- **Estrutura**: Arquivos espalhados sem organização lógica
- **Dependências**: Bibliotecas desatualizadas (PHPExcel, FPDF antigos)

### Funcionalidades Principais Identificadas:
1. **Autenticação por CPF** com controle de tentativas
2. **Gestão de Usuários** (tb_usuarios, tb_perfil_usuario)
3. **Controle de Permissões** (tb_permissao por funcionalidade)
4. **Gestão de Equipamentos** (tablets, chips - tb_dim_tablet, tb_dim_iccid)
5. **Relatórios e Formulários** (Saúde da Mulher, E-Agentes, Competências)
6. **Upload de Arquivos** (PDFs, planilhas Excel)
7. **Sistema de Auditoria** (tb_auditoria_login)
8. **Gestão de Municípios** (tb_dim_municipio)

### Tabelas Principais Identificadas:
```sql
-- Usuários e Autenticação
tb_usuarios (id, nome, cpf, email, senha, ativo, foto, dt_cadastro)
tb_perfil_usuario (id, id_perfil, perfil, id_usuario, ibge, cnes, ativo)
tb_auditoria_login (id, cpf, resultado, mensagem, ip_usuario)
tb_permissao (perfil_id, funcionalidade_id, ativo)

-- Equipamentos
tb_dim_tablet (imei, marca, modelo, caixa, ativo)
tb_dim_iccid (iccid, caixa, ativo)
tb_geral_entregue (id, quebra, roubo_furto, ativo)

-- Dados Administrativos
tb_dim_municipio (ibge, municipio, unidade, cnes)
tb_competencia (mes_referencia, fechada)
tb_programa_adesao (id, programa, ativo)

-- Formulários Específicos
tb_formulario_saudedamulher (municipio, medicacao, consumo_mensal, estoque, lote, data_vencimento)
```

## Instruções para Nova Implementação

### 1. Arquitetura e Estrutura de Pastas

```
APSDigital/
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── EquipmentController.php
│   │   ├── ReportController.php
│   │   ├── HealthFormController.php
│   │   └── DashboardController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── UserProfile.php
│   │   ├── Equipment.php
│   │   ├── Municipality.php
│   │   ├── HealthForm.php
│   │   └── AuditLog.php
│   ├── Views/
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   ├── register.php
│   │   │   └── reset-password.php
│   │   ├── dashboard/
│   │   │   ├── index.php
│   │   │   └── profile-selection.php
│   │   ├── equipment/
│   │   │   ├── index.php
│   │   │   ├── authorize.php
│   │   │   └── manage.php
│   │   ├── reports/
│   │   │   ├── index.php
│   │   │   ├── health-reports.php
│   │   │   └── export.php
│   │   ├── users/
│   │   │   ├── index.php
│   │   │   ├── authorize.php
│   │   │   └── profile.php
│   │   └── layouts/
│   │       ├── app.php
│   │       ├── auth.php
│   │       └── partials/
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── PermissionMiddleware.php
│   │   └── CSRFMiddleware.php
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── EquipmentService.php
│   │   ├── ReportService.php
│   │   ├── EmailService.php
│   │   └── AuditService.php
│   ├── Helpers/
│   │   ├── Validator.php
│   │   ├── Sanitizer.php
│   │   ├── FileUpload.php
│   │   └── Security.php
│   └── Config/
│       ├── Database.php
│       ├── Config.php
│       └── Routes.php
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   │   ├── app.css
│   │   │   └── auth.css
│   │   ├── js/
│   │   │   ├── app.js
│   │   │   ├── auth.js
│   │   │   └── dashboard.js
│   │   ├── img/
│   │   └── uploads/
│   │       ├── documents/
│   │       └── user-photos/
├── database/
│   ├── schema.sql
│   ├── migrations/
│   └── seeds/
├── storage/
│   ├── logs/
│   ├── cache/
│   └── temp/
├── vendor/
├── .env.example
├── .env
├── composer.json
├── README.md
├── PROMPT.md
└── docker-compose.yml
```

### 2. Tecnologias e Dependências

**Backend:**
- PHP 8.1+ (PSR-12 compliant)
- Composer para gerenciamento de dependências
- PDO para banco de dados PostgreSQL
- PHPMailer para envio de emails
- PhpSpreadsheet para Excel
- TCPDF para geração de PDFs

**Frontend:**
- Bootstrap 5.3+ (responsivo)
- JavaScript ES6+ (modular)
- Font Awesome para ícones
- Chart.js para gráficos
- DataTables para tabelas interativas

**Segurança:**
- password_hash() para senhas
- Tokens CSRF
- Sanitização de inputs
- Validação server-side
- Rate limiting para login

### 3. Funcionalidades Principais

#### 3.1 Sistema de Autenticação
```php
// AuthController.php - Exemplo de estrutura
class AuthController {
    private $authService;
    
    public function login() {
        // Validar CPF (11 dígitos)
        // Verificar rate limiting (máx 3 tentativas)
        // Autenticar com password_verify()
        // Registrar auditoria
        // Redirecionar para seleção de perfil
    }
    
    public function logout() {
        // Destruir sessão
        // Registrar logout na auditoria
    }
    
    public function resetPassword() {
        // Validar CPF
        // Gerar nova senha
        // Enviar por email
        // Registrar tentativa
    }
}
```

#### 3.2 Gestão de Equipamentos
- **Tablet Management**: IMEI, marca, modelo, status
- **Chip Management**: ICCID, operadora, status
- **Authorization Flow**: Gestor municipal autoriza entregas
- **Inventory Control**: Controle de estoque por caixa
- **Status Tracking**: Entregue, defeito, roubo/furto

#### 3.3 Sistema de Relatórios
- **Saúde da Mulher**: Medicamentos, estoque, validades
- **E-Agentes**: Pagamentos ACS/Supervisores por competência
- **Equipamentos**: Relatórios de distribuição
- **Export**: PDF, Excel com filtros dinâmicos

#### 3.4 Controle de Permissões
```php
// PermissionMiddleware.php
class PermissionMiddleware {
    public function check($profileId, $functionalityId) {
        // Verificar tb_permissao
        // Validar se perfil está ativo
        // Autorizar/Negar acesso
    }
}
```

### 4. Interface e UX

#### 4.1 Design System
- **Cores**: Azul SES-MS (#004F9F, #2a80dc)
- **Typography**: Inter ou Roboto
- **Components**: Cards, modais, alerts responsivos
- **Layout**: Sidebar collapsible, header fixo
- **Mobile-First**: Bootstrap Grid System

#### 4.2 Páginas Principais
1. **Login** - CPF/Senha com reset
2. **Dashboard** - Resumo de permissões e ações
3. **Seleção de Perfil** - Múltiplos perfis por usuário
4. **Gestão de Usuários** - CRUD com autorização
5. **Equipamentos** - Autorização e controle
6. **Relatórios** - Filtros e exportação
7. **Formulários** - Saúde da Mulher, E-Agentes

### 5. Segurança Implementada

```php
// Security.php - Helpers de segurança
class Security {
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateCSRFToken() {
        return bin2hex(random_bytes(32));
    }
    
    public static function validateCPF($cpf) {
        // Validação completa de CPF
    }
    
    public static function rateLimit($identifier, $maxAttempts = 3, $timeWindow = 600) {
        // Rate limiting implementation
    }
}
```

### 6. Banco de Dados - Schema Limpo

```sql
-- Schema principal (PostgreSQL)
CREATE SCHEMA apsdigital;

-- Tabelas de Usuários
CREATE TABLE tb_usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf CHAR(11) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    cns_profissional VARCHAR(15),
    foto VARCHAR(255),
    ativo BOOLEAN DEFAULT false,
    dt_cadastro TIMESTAMP DEFAULT NOW(),
    dt_ultimo_acesso TIMESTAMP,
    profissional_cadastrante VARCHAR(255),
    cpf_profissional_cadastrante CHAR(11)
);

CREATE TABLE tb_perfil_usuario (
    id SERIAL PRIMARY KEY,
    id_perfil INTEGER NOT NULL,
    perfil VARCHAR(100) NOT NULL,
    id_usuario INTEGER REFERENCES tb_usuarios(id),
    ibge CHAR(7),
    cnes VARCHAR(7),
    ine VARCHAR(10),
    microarea INTEGER,
    ativo BOOLEAN DEFAULT false,
    dt_criacao TIMESTAMP DEFAULT NOW()
);

-- Tabelas de Auditoria
CREATE TABLE tb_auditoria_login (
    id SERIAL PRIMARY KEY,
    cpf CHAR(11) NOT NULL,
    resultado VARCHAR(50) NOT NULL, -- 'Sucesso', 'Falha', 'Erro'
    mensagem TEXT,
    ip_usuario INET,
    dt_tentativa TIMESTAMP DEFAULT NOW()
);

-- Tabelas de Equipamentos
CREATE TABLE tb_dim_tablet (
    id SERIAL PRIMARY KEY,
    imei VARCHAR(15) UNIQUE NOT NULL,
    marca VARCHAR(50),
    modelo VARCHAR(100),
    caixa VARCHAR(20),
    ativo BOOLEAN DEFAULT true,
    dt_cadastro TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tb_dim_iccid (
    id SERIAL PRIMARY KEY,
    iccid VARCHAR(20) UNIQUE NOT NULL,
    operadora VARCHAR(50),
    caixa VARCHAR(20),
    ativo BOOLEAN DEFAULT true,
    dt_cadastro TIMESTAMP DEFAULT NOW()
);

-- Tabelas de Controle
CREATE TABLE tb_permissao (
    id SERIAL PRIMARY KEY,
    perfil_id INTEGER NOT NULL,
    funcionalidade_id INTEGER NOT NULL,
    ativo BOOLEAN DEFAULT true,
    UNIQUE(perfil_id, funcionalidade_id)
);

-- Índices para performance
CREATE INDEX idx_usuarios_cpf ON tb_usuarios(cpf);
CREATE INDEX idx_auditoria_cpf_data ON tb_auditoria_login(cpf, dt_tentativa);
CREATE INDEX idx_perfil_usuario_ativo ON tb_perfil_usuario(ativo, id_usuario);
```

### 7. Configuração e Deploy

#### 7.1 Arquivo .env.example
```env
# Banco de Dados
DB_HOST=localhost
DB_PORT=5432
DB_NAME=apsdigital
DB_USER=apsdigital
DB_PASS=sua_senha_aqui

# Aplicação
APP_NAME="APS Digital"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://apsdigital.ses.ms.gov.br

# Email
MAIL_HOST=smtp.ses.ms.gov.br
MAIL_PORT=587
MAIL_USERNAME=apsdigital@ses.ms.gov.br
MAIL_PASSWORD=sua_senha_email
MAIL_FROM_NAME="APS Digital - SES/MS"

# Segurança
CSRF_SECRET=sua_chave_csrf_32_chars_aqui
SESSION_LIFETIME=7200
MAX_LOGIN_ATTEMPTS=3
RATE_LIMIT_WINDOW=600

# Upload
MAX_FILE_SIZE=10485760
ALLOWED_EXTENSIONS=pdf,xlsx,xls,jpg,png
UPLOAD_PATH=/var/www/apsdigital/public/uploads
```

#### 7.2 Docker-compose.yml (Opcional)
```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:80"
    environment:
      - DB_HOST=postgres
    depends_on:
      - postgres
    volumes:
      - ./public/uploads:/var/www/html/public/uploads

  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: apsdigital
      POSTGRES_USER: apsdigital
      POSTGRES_PASSWORD: secretariaestadualdesaude2024
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
```

### 8. Melhorias de Performance

1. **Caching**: Redis ou arquivo para sessões e queries
2. **Compressão**: Gzip para assets CSS/JS
3. **Minificação**: Assets otimizados para produção
4. **Lazy Loading**: Carregamento sob demanda de módulos
5. **Database**: Índices otimizados, queries preparadas
6. **CDN**: Bootstrap e bibliotecas via CDN

### 9. Logs e Monitoramento

```php
// AuditService.php
class AuditService {
    public static function logUserAction($userId, $action, $details = null) {
        // Registrar ações de usuários
        // Login, logout, alterações, acessos
        // Timestamp, IP, user-agent
    }
    
    public static function logSystemError($error, $context = []) {
        // Registrar erros do sistema
        // Falhas de DB, uploads, emails
    }
}
```

### 10. Testes e Validação

#### 10.1 Testes Essenciais
- **Login/Logout**: Autenticação e autorização
- **Permissões**: Controle de acesso por funcionalidade
- **Upload**: Validação de arquivos e segurança
- **Formulários**: Validação e sanitização
- **Relatórios**: Geração e exportação
- **Responsividade**: Mobile e desktop

#### 10.2 Checklist de Segurança
- [ ] Senhas criptografadas com password_hash()
- [ ] Proteção CSRF em todos os formulários
- [ ] Sanitização de inputs (XSS)
- [ ] Queries preparadas (SQL Injection)
- [ ] Rate limiting no login
- [ ] Validação de upload de arquivos
- [ ] Logs de auditoria completos
- [ ] Sessões seguras (httpOnly, secure)

## Documentação Final

### README.md
```markdown
# APS Digital - Sistema SES/MS

## Requisitos
- PHP 8.1+
- PostgreSQL 12+
- Composer
- Web Server (Apache/Nginx)

## Instalação
1. Clone o repositório
2. Execute `composer install`
3. Copie `.env.example` para `.env`
4. Configure banco de dados
5. Execute `php database/schema.sql`
6. Configure permissões de upload

## Estrutura
- `/app` - Lógica da aplicação
- `/public` - Assets e ponto de entrada
- `/database` - Esquemas e migrações
- `/storage` - Logs e cache

## Funcionalidades
- Autenticação por CPF
- Gestão de equipamentos (tablets/chips)
- Relatórios personalizáveis
- Sistema de permissões granular
- Interface responsiva
```

## Resultado Esperado

**Sistema moderno, seguro e profissional** que atenda completamente às necessidades da SES-MS, com:

✅ **Arquitetura MVC limpa e organizadas**  
✅ **Segurança robusta** (CSRF, XSS, SQL Injection)  
✅ **Interface responsiva** e profissional  
✅ **Performance otimizada** com cache e índices  
✅ **Logs de auditoria** completos  
✅ **Documentação técnica** detalhada  
✅ **Facilidade de manutenção** e extensão  
✅ **Deploy simplificado** no aaPanel  

Este prompt deve ser usado como base completa para reescrever o sistema APSDigital do zero, seguindo as melhores práticas de desenvolvimento web moderno.