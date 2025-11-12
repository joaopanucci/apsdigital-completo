-- =====================================================================
-- APS Digital - Sistema SES/MS
-- Schema Principal PostgreSQL
-- Versão: 2.0.0
-- Autor: SES-MS
-- =====================================================================

-- Criação do schema principal
CREATE SCHEMA IF NOT EXISTS apsdigital;
SET search_path TO apsdigital, public;

-- Configurações de encoding e timezone
SET client_encoding TO 'UTF8';
SET timezone TO 'America/Campo_Grande';

-- =====================================================================
-- TABELAS DE USUÁRIOS E AUTENTICAÇÃO
-- =====================================================================

-- Tabela de usuários principal
CREATE TABLE IF NOT EXISTS tb_usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf CHAR(11) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL, -- password_hash() 
    telefone VARCHAR(20),
    cns_profissional VARCHAR(15),
    foto VARCHAR(255),
    ativo BOOLEAN DEFAULT false,
    dt_cadastro TIMESTAMP DEFAULT NOW(),
    dt_ultimo_acesso TIMESTAMP,
    profissional_cadastrante VARCHAR(255),
    cpf_profissional_cadastrante CHAR(11),
    reset_token VARCHAR(64),
    reset_token_expires TIMESTAMP,
    tentativas_login INTEGER DEFAULT 0,
    bloqueado_ate TIMESTAMP,
    CONSTRAINT chk_cpf_length CHECK (LENGTH(cpf) = 11),
    CONSTRAINT chk_cpf_numeric CHECK (cpf ~ '^[0-9]+$'),
    CONSTRAINT chk_email_format CHECK (email ~ '^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$')
);

-- Tabela de perfis de usuários
CREATE TABLE IF NOT EXISTS tb_perfil_usuario (
    id SERIAL PRIMARY KEY,
    id_perfil INTEGER NOT NULL,
    perfil VARCHAR(100) NOT NULL,
    id_usuario INTEGER NOT NULL REFERENCES tb_usuarios(id) ON DELETE CASCADE,
    ibge CHAR(7),
    cnes VARCHAR(7),
    ine VARCHAR(10),
    microarea INTEGER,
    ativo BOOLEAN DEFAULT false,
    dt_criacao TIMESTAMP DEFAULT NOW(),
    dt_ultima_selecao TIMESTAMP,
    autorizado_por INTEGER REFERENCES tb_usuarios(id),
    dt_autorizacao TIMESTAMP,
    CONSTRAINT chk_perfil_valido CHECK (id_perfil BETWEEN 1 AND 5),
    CONSTRAINT chk_ibge_length CHECK (ibge IS NULL OR LENGTH(ibge) = 7),
    UNIQUE (id_usuario, id_perfil, ibge, cnes)
);

-- Tabela de auditoria de login
CREATE TABLE IF NOT EXISTS tb_auditoria_login (
    id SERIAL PRIMARY KEY,
    cpf CHAR(11) NOT NULL,
    resultado VARCHAR(50) NOT NULL, -- 'Sucesso', 'Falha', 'Bloqueado', 'Reset'
    mensagem TEXT,
    ip_usuario INET,
    user_agent TEXT,
    dt_tentativa TIMESTAMP DEFAULT NOW(),
    sessao_id VARCHAR(128),
    CONSTRAINT chk_resultado_valido CHECK (resultado IN ('Sucesso', 'Falha', 'Bloqueado', 'Reset', 'Logout'))
);

-- Tabela de sessões ativas
CREATE TABLE IF NOT EXISTS tb_sessoes (
    id VARCHAR(128) PRIMARY KEY,
    id_usuario INTEGER NOT NULL REFERENCES tb_usuarios(id) ON DELETE CASCADE,
    id_perfil_ativo INTEGER REFERENCES tb_perfil_usuario(id),
    ip_address INET NOT NULL,
    user_agent TEXT,
    dados JSONB,
    ativo BOOLEAN DEFAULT true,
    dt_criacao TIMESTAMP DEFAULT NOW(),
    dt_ultimo_acesso TIMESTAMP DEFAULT NOW(),
    dt_expiracao TIMESTAMP NOT NULL
);

-- =====================================================================
-- TABELAS DE EQUIPAMENTOS
-- =====================================================================

-- Tabela de tablets
CREATE TABLE IF NOT EXISTS tb_dim_tablet (
    id SERIAL PRIMARY KEY,
    imei VARCHAR(15) UNIQUE NOT NULL,
    marca VARCHAR(50),
    modelo VARCHAR(100),
    numero_serie VARCHAR(100),
    caixa VARCHAR(20),
    lote VARCHAR(20),
    ativo BOOLEAN DEFAULT true,
    dt_cadastro TIMESTAMP DEFAULT NOW(),
    dt_atualizacao TIMESTAMP DEFAULT NOW(),
    cadastrado_por INTEGER REFERENCES tb_usuarios(id),
    CONSTRAINT chk_imei_length CHECK (LENGTH(imei) >= 14),
    CONSTRAINT chk_imei_numeric CHECK (imei ~ '^[0-9]+$')
);

-- Tabela de chips/ICCID
CREATE TABLE IF NOT EXISTS tb_dim_iccid (
    id SERIAL PRIMARY KEY,
    iccid VARCHAR(20) UNIQUE NOT NULL,
    operadora VARCHAR(50),
    numero_linha VARCHAR(20),
    caixa VARCHAR(20),
    lote VARCHAR(20),
    ativo BOOLEAN DEFAULT true,
    dt_cadastro TIMESTAMP DEFAULT NOW(),
    dt_atualizacao TIMESTAMP DEFAULT NOW(),
    cadastrado_por INTEGER REFERENCES tb_usuarios(id),
    CONSTRAINT chk_iccid_length CHECK (LENGTH(iccid) >= 18)
);

-- Tabela de controle geral de equipamentos entregues
CREATE TABLE IF NOT EXISTS tb_geral_entregue (
    id SERIAL PRIMARY KEY,
    id_tablet INTEGER REFERENCES tb_dim_tablet(id),
    id_iccid INTEGER REFERENCES tb_dim_iccid(id),
    ibge CHAR(7) NOT NULL,
    cnes VARCHAR(7),
    profissional_destino VARCHAR(255),
    cpf_profissional CHAR(11),
    dt_entrega DATE NOT NULL,
    dt_autorizacao TIMESTAMP,
    autorizado_por INTEGER REFERENCES tb_usuarios(id),
    observacoes TEXT,
    status VARCHAR(20) DEFAULT 'Entregue',
    quebra BOOLEAN DEFAULT false,
    roubo_furto BOOLEAN DEFAULT false,
    dt_ocorrencia DATE,
    boletim_ocorrencia VARCHAR(100),
    ativo BOOLEAN DEFAULT true,
    CONSTRAINT chk_status_valido CHECK (status IN ('Entregue', 'Quebrado', 'Roubado/Furtado', 'Devolvido', 'Substituído'))
);

-- =====================================================================
-- TABELAS ADMINISTRATIVAS
-- =====================================================================

-- Tabela de municípios
CREATE TABLE IF NOT EXISTS tb_dim_municipio (
    id SERIAL PRIMARY KEY,
    ibge CHAR(7) UNIQUE NOT NULL,
    municipio VARCHAR(100) NOT NULL,
    unidade VARCHAR(100),
    cnes VARCHAR(7),
    regional VARCHAR(50),
    ativo BOOLEAN DEFAULT true,
    dt_cadastro TIMESTAMP DEFAULT NOW(),
    populacao INTEGER,
    coordenadas POINT,
    CONSTRAINT chk_ibge_format CHECK (ibge ~ '^[0-9]{7}$')
);

-- Tabela de competências (controle de fechamento mensal)
CREATE TABLE IF NOT EXISTS tb_competencia (
    id SERIAL PRIMARY KEY,
    mes_referencia DATE NOT NULL, -- Primeiro dia do mês
    fechada BOOLEAN DEFAULT false,
    dt_fechamento TIMESTAMP,
    fechada_por INTEGER REFERENCES tb_usuarios(id),
    observacoes TEXT,
    UNIQUE (mes_referencia)
);

-- Tabela de programas de adesão
CREATE TABLE IF NOT EXISTS tb_programa_adesao (
    id SERIAL PRIMARY KEY,
    programa VARCHAR(100) NOT NULL,
    descricao TEXT,
    ativo BOOLEAN DEFAULT true,
    dt_cadastro TIMESTAMP DEFAULT NOW(),
    responsavel INTEGER REFERENCES tb_usuarios(id)
);

-- =====================================================================
-- TABELA DE PERMISSÕES
-- =====================================================================

-- Tabela de permissões por perfil e funcionalidade
CREATE TABLE IF NOT EXISTS tb_permissao (
    id SERIAL PRIMARY KEY,
    perfil_id INTEGER NOT NULL,
    funcionalidade_id INTEGER NOT NULL,
    ativo BOOLEAN DEFAULT true,
    dt_criacao TIMESTAMP DEFAULT NOW(),
    criado_por INTEGER REFERENCES tb_usuarios(id),
    UNIQUE(perfil_id, funcionalidade_id),
    CONSTRAINT chk_perfil_id_valido CHECK (perfil_id BETWEEN 1 AND 5),
    CONSTRAINT chk_funcionalidade_id_valido CHECK (funcionalidade_id BETWEEN 1 AND 10)
);

-- =====================================================================
-- FORMULÁRIOS ESPECÍFICOS
-- =====================================================================

-- Formulário Saúde da Mulher
CREATE TABLE IF NOT EXISTS tb_formulario_saudedamulher (
    id SERIAL PRIMARY KEY,
    municipio CHAR(7) NOT NULL REFERENCES tb_dim_municipio(ibge),
    cnes VARCHAR(7),
    competencia DATE NOT NULL,
    medicacao VARCHAR(255) NOT NULL,
    consumo_mensal INTEGER NOT NULL DEFAULT 0,
    estoque_atual INTEGER NOT NULL DEFAULT 0,
    lote VARCHAR(50),
    data_vencimento DATE,
    observacoes TEXT,
    preenchido_por INTEGER NOT NULL REFERENCES tb_usuarios(id),
    dt_preenchimento TIMESTAMP DEFAULT NOW(),
    dt_atualizacao TIMESTAMP DEFAULT NOW(),
    validado_por INTEGER REFERENCES tb_usuarios(id),
    dt_validacao TIMESTAMP,
    CONSTRAINT chk_consumo_positivo CHECK (consumo_mensal >= 0),
    CONSTRAINT chk_estoque_positivo CHECK (estoque_atual >= 0),
    UNIQUE (municipio, cnes, competencia, medicacao)
);

-- Formulário E-Agentes (pagamentos)
CREATE TABLE IF NOT EXISTS tb_formulario_eagentes (
    id SERIAL PRIMARY KEY,
    municipio CHAR(7) NOT NULL REFERENCES tb_dim_municipio(ibge),
    competencia DATE NOT NULL,
    tipo_profissional VARCHAR(50) NOT NULL, -- 'ACS' ou 'Supervisor'
    nome_profissional VARCHAR(255) NOT NULL,
    cpf_profissional CHAR(11) NOT NULL,
    valor_pagamento DECIMAL(10,2) NOT NULL,
    banco VARCHAR(10),
    agencia VARCHAR(10),
    conta VARCHAR(20),
    observacoes TEXT,
    preenchido_por INTEGER NOT NULL REFERENCES tb_usuarios(id),
    dt_preenchimento TIMESTAMP DEFAULT NOW(),
    dt_atualizacao TIMESTAMP DEFAULT NOW(),
    validado_por INTEGER REFERENCES tb_usuarios(id),
    dt_validacao TIMESTAMP,
    pago BOOLEAN DEFAULT false,
    dt_pagamento DATE,
    CONSTRAINT chk_tipo_profissional CHECK (tipo_profissional IN ('ACS', 'Supervisor')),
    CONSTRAINT chk_valor_positivo CHECK (valor_pagamento > 0),
    CONSTRAINT chk_cpf_eagentes CHECK (LENGTH(cpf_profissional) = 11),
    UNIQUE (municipio, competencia, cpf_profissional)
);

-- =====================================================================
-- TABELA DE LOGS GERAIS
-- =====================================================================

-- Tabela de auditoria geral do sistema
CREATE TABLE IF NOT EXISTS tb_auditoria_sistema (
    id SERIAL PRIMARY KEY,
    id_usuario INTEGER REFERENCES tb_usuarios(id),
    acao VARCHAR(100) NOT NULL,
    tabela_afetada VARCHAR(50),
    registro_id INTEGER,
    dados_anteriores JSONB,
    dados_novos JSONB,
    ip_usuario INET,
    user_agent TEXT,
    dt_acao TIMESTAMP DEFAULT NOW()
);

-- =====================================================================
-- ÍNDICES PARA PERFORMANCE
-- =====================================================================

-- Índices para tb_usuarios
CREATE INDEX IF NOT EXISTS idx_usuarios_cpf ON tb_usuarios(cpf);
CREATE INDEX IF NOT EXISTS idx_usuarios_email ON tb_usuarios(email);
CREATE INDEX IF NOT EXISTS idx_usuarios_ativo ON tb_usuarios(ativo);
CREATE INDEX IF NOT EXISTS idx_usuarios_dt_cadastro ON tb_usuarios(dt_cadastro);

-- Índices para tb_perfil_usuario  
CREATE INDEX IF NOT EXISTS idx_perfil_usuario_usuario ON tb_perfil_usuario(id_usuario);
CREATE INDEX IF NOT EXISTS idx_perfil_usuario_ativo ON tb_perfil_usuario(ativo);
CREATE INDEX IF NOT EXISTS idx_perfil_usuario_ibge ON tb_perfil_usuario(ibge);
CREATE INDEX IF NOT EXISTS idx_perfil_usuario_perfil ON tb_perfil_usuario(id_perfil);

-- Índices para auditoria
CREATE INDEX IF NOT EXISTS idx_auditoria_login_cpf ON tb_auditoria_login(cpf);
CREATE INDEX IF NOT EXISTS idx_auditoria_login_data ON tb_auditoria_login(dt_tentativa);
CREATE INDEX IF NOT EXISTS idx_auditoria_login_resultado ON tb_auditoria_login(resultado);

-- Índices para sessões
CREATE INDEX IF NOT EXISTS idx_sessoes_usuario ON tb_sessoes(id_usuario);
CREATE INDEX IF NOT EXISTS idx_sessoes_ativo ON tb_sessoes(ativo);
CREATE INDEX IF NOT EXISTS idx_sessoes_expiracao ON tb_sessoes(dt_expiracao);

-- Índices para equipamentos
CREATE INDEX IF NOT EXISTS idx_tablet_imei ON tb_dim_tablet(imei);
CREATE INDEX IF NOT EXISTS idx_tablet_ativo ON tb_dim_tablet(ativo);
CREATE INDEX IF NOT EXISTS idx_tablet_caixa ON tb_dim_tablet(caixa);

CREATE INDEX IF NOT EXISTS idx_iccid_numero ON tb_dim_iccid(iccid);
CREATE INDEX IF NOT EXISTS idx_iccid_ativo ON tb_dim_iccid(ativo);
CREATE INDEX IF NOT EXISTS idx_iccid_caixa ON tb_dim_iccid(caixa);

-- Índices para entregas
CREATE INDEX IF NOT EXISTS idx_entregue_ibge ON tb_geral_entregue(ibge);
CREATE INDEX IF NOT EXISTS idx_entregue_data ON tb_geral_entregue(dt_entrega);
CREATE INDEX IF NOT EXISTS idx_entregue_status ON tb_geral_entregue(status);

-- Índices para formulários
CREATE INDEX IF NOT EXISTS idx_saude_mulher_municipio ON tb_formulario_saudedamulher(municipio);
CREATE INDEX IF NOT EXISTS idx_saude_mulher_competencia ON tb_formulario_saudedamulher(competencia);

CREATE INDEX IF NOT EXISTS idx_eagentes_municipio ON tb_formulario_eagentes(municipio);
CREATE INDEX IF NOT EXISTS idx_eagentes_competencia ON tb_formulario_eagentes(competencia);
CREATE INDEX IF NOT EXISTS idx_eagentes_cpf ON tb_formulario_eagentes(cpf_profissional);

-- =====================================================================
-- TRIGGERS PARA AUDITORIA AUTOMÁTICA
-- =====================================================================

-- Função para auditoria automática
CREATE OR REPLACE FUNCTION fn_auditoria_trigger()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO tb_auditoria_sistema (acao, tabela_afetada, registro_id, dados_novos, dt_acao)
        VALUES ('INSERT', TG_TABLE_NAME, NEW.id, to_jsonb(NEW), NOW());
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO tb_auditoria_sistema (acao, tabela_afetada, registro_id, dados_anteriores, dados_novos, dt_acao)
        VALUES ('UPDATE', TG_TABLE_NAME, NEW.id, to_jsonb(OLD), to_jsonb(NEW), NOW());
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO tb_auditoria_sistema (acao, tabela_afetada, registro_id, dados_anteriores, dt_acao)
        VALUES ('DELETE', TG_TABLE_NAME, OLD.id, to_jsonb(OLD), NOW());
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Triggers de auditoria para tabelas críticas
CREATE TRIGGER tr_auditoria_usuarios 
    AFTER INSERT OR UPDATE OR DELETE ON tb_usuarios
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_trigger();

CREATE TRIGGER tr_auditoria_perfil_usuario 
    AFTER INSERT OR UPDATE OR DELETE ON tb_perfil_usuario
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_trigger();

CREATE TRIGGER tr_auditoria_equipamentos 
    AFTER INSERT OR UPDATE OR DELETE ON tb_geral_entregue
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_trigger();

-- =====================================================================
-- VIEWS ÚTEIS PARA RELATÓRIOS
-- =====================================================================

-- View de usuários com perfis
CREATE OR REPLACE VIEW vw_usuarios_perfis AS
SELECT 
    u.id,
    u.nome,
    u.cpf,
    u.email,
    u.ativo as usuario_ativo,
    u.dt_cadastro,
    p.id as perfil_id,
    p.id_perfil,
    p.perfil,
    p.ibge,
    p.cnes,
    p.ativo as perfil_ativo,
    m.municipio
FROM tb_usuarios u
LEFT JOIN tb_perfil_usuario p ON u.id = p.id_usuario
LEFT JOIN tb_dim_municipio m ON p.ibge = m.ibge;

-- View de equipamentos distribuídos
CREATE OR REPLACE VIEW vw_equipamentos_distribuidos AS
SELECT 
    e.id,
    e.ibge,
    m.municipio,
    t.imei,
    t.marca as tablet_marca,
    t.modelo as tablet_modelo,
    i.iccid,
    i.operadora,
    e.profissional_destino,
    e.dt_entrega,
    e.status,
    e.quebra,
    e.roubo_furto
FROM tb_geral_entregue e
JOIN tb_dim_municipio m ON e.ibge = m.ibge
LEFT JOIN tb_dim_tablet t ON e.id_tablet = t.id
LEFT JOIN tb_dim_iccid i ON e.id_iccid = i.id
WHERE e.ativo = true;

-- =====================================================================
-- COMENTÁRIOS DE DOCUMENTAÇÃO
-- =====================================================================

COMMENT ON SCHEMA apsdigital IS 'Schema principal do sistema APS Digital - SES/MS';

COMMENT ON TABLE tb_usuarios IS 'Tabela principal de usuários do sistema';
COMMENT ON COLUMN tb_usuarios.cpf IS 'CPF do usuário (11 dígitos numéricos)';
COMMENT ON COLUMN tb_usuarios.senha IS 'Senha criptografada com password_hash()';

COMMENT ON TABLE tb_perfil_usuario IS 'Perfis de acesso dos usuários por município/unidade';
COMMENT ON COLUMN tb_perfil_usuario.id_perfil IS '1=Admin SES, 2=Gestor Regional, 3=Gestor Municipal, 4=Técnico, 5=Auditor';

COMMENT ON TABLE tb_auditoria_login IS 'Log de todas as tentativas de login no sistema';
COMMENT ON TABLE tb_auditoria_sistema IS 'Log geral de ações no sistema';

COMMENT ON TABLE tb_dim_tablet IS 'Cadastro de tablets disponíveis';
COMMENT ON TABLE tb_dim_iccid IS 'Cadastro de chips/linhas telefônicas';
COMMENT ON TABLE tb_geral_entregue IS 'Controle de equipamentos entregues aos profissionais';

COMMENT ON TABLE tb_formulario_saudedamulher IS 'Formulário mensal de medicamentos da saúde da mulher';
COMMENT ON TABLE tb_formulario_eagentes IS 'Formulário de pagamento de ACS e Supervisores';

-- =====================================================================
-- CONFIGURAÇÕES FINAIS
-- =====================================================================

-- Concede permissões ao usuário da aplicação
GRANT USAGE ON SCHEMA apsdigital TO apsdigital;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA apsdigital TO apsdigital;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA apsdigital TO apsdigital;

-- Mensagem de finalização
SELECT 'Schema APS Digital criado com sucesso!' as status;