# Sistema de Mapas Regionais - APS Digital

## Visão Geral
O sistema de mapas regionais foi integrado ao APS Digital para fornecer visualização interativa dos dados de saúde por região de Mato Grosso do Sul. O sistema inclui mapas SVG interativos e indicadores de saúde regionalizados.

## Funcionalidades Implementadas

### 1. Mapas Regionais (/mapas)
- **Visualização Interativa**: Mapas SVG das 9 regiões de MS
- **Navegação por Região**: Acesso direto aos dados de cada região
- **Responsividade**: Interface adaptável para desktop e mobile
- **Integração Bootstrap**: Design consistente com o sistema APS

#### Regiões Disponíveis:
1. BAIXO PANTANAL
2. CENTRO SUL  
3. CENTRO
4. LESTE
5. NORDESTE
6. NORTE
7. PANTANAL
8. SUDESTE
9. SUL FRONTEIRA

### 2. Indicadores de Saúde (/indicadores)
- **Dashboard Regional**: Painel com estatísticas por região
- **Comparativo**: Ferramenta para comparar dados entre regiões
- **Exportação**: Funcionalidade para exportar relatórios
- **Tutorial**: Guia de utilização do sistema

### 3. APIs Implementadas

#### APIs Públicas (sem autenticação)
```
GET /api/mapas/regioes - Lista todas as regiões
GET /api/mapas/svg/{regiao} - Retorna SVG da região
GET /api/mapas/geojson/{regiao} - Retorna dados GeoJSON
GET /api/indicadores/regioes - Lista regiões com indicadores
GET /api/indicadores/dados/{regiao} - Dados da região
GET /api/indicadores/estatisticas - Estatísticas gerais
```

#### APIs Protegidas (com autenticação)
```
POST /api/indicadores/dados/{regiao} - Criar dados
PUT /api/indicadores/dados/{id} - Atualizar dados
DELETE /api/indicadores/dados/{id} - Excluir dados
```

## Estrutura de Arquivos

### Controllers
- `app/Controllers/MapaController.php` - Gerencia visualização de mapas
- `app/Controllers/IndicadorController.php` - Gerencia indicadores de saúde

### Models
- `app/Models/Mapa.php` - Modelo para dados regionais
- `app/Models/Indicador.php` - Modelo para indicadores de saúde
- `app/Models/Municipio.php` - Modelo para dados municipais

### Views
- `app/Views/mapas/index.php` - Página principal dos mapas
- `app/Views/mapas/regiao.php` - Visualização por região
- `app/Views/mapas/visualizar.php` - Interface de visualização interativa

### Assets
- `public/assets/mapas/svg/` - Arquivos SVG das regiões (9 arquivos)
- `public/assets/mapas/json/` - Dados GeoJSON das regiões (9 arquivos)

## Integração com o Sistema

### Menu de Navegação
Adicionada seção "Análise Regional" no menu principal:
- Mapas Regionais
- Indicadores de Saúde  
- Dashboard Regional

### Rotas Configuradas
Todas as rotas foram integradas ao sistema de roteamento existente em `app/Config/Routes.php`, mantendo a compatibilidade com middleware e sistema de permissões.

### Banco de Dados
- Integração com PostgreSQL existente
- Tabelas criadas automaticamente via models
- Suporte a agregações e consultas complexas

## Tecnologias Utilizadas
- **Backend**: PHP 8.1+ com arquitetura MVC
- **Frontend**: Bootstrap 5 + JavaScript
- **Mapas**: SVG interativo + GeoJSON
- **Banco**: PostgreSQL 15
- **APIs**: RESTful com JSON

## Como Usar

### 1. Acessar Mapas
1. Faça login no sistema APS Digital
2. Acesse o menu "Análise Regional" > "Mapas Regionais"
3. Clique em uma região no mapa ou use os botões de navegação
4. Visualize os dados específicos da região selecionada

### 2. Visualizar Indicadores
1. Acesse "Análise Regional" > "Indicadores de Saúde"
2. Selecione uma região para ver os dados detalhados
3. Use o dashboard para comparar regiões
4. Exporte relatórios conforme necessário

### 3. Dashboard Regional
1. Acesse "Análise Regional" > "Dashboard Regional"
2. Visualize gráficos e métricas consolidadas
3. Compare performance entre regiões
4. Acompanhe tendências e evolução dos dados

## Status da Implementação
✅ **Concluído**: Sistema totalmente integrado e funcional
- Arquivos copiados e organizados
- Controllers e models implementados
- Views criadas com Bootstrap 5
- Rotas configuradas
- Menu integrado
- APIs funcionais
- Servidor testado e rodando

## Próximos Passos (Opcionais)
- [ ] Implementar sistema de cache para mapas
- [ ] Adicionar filtros temporais nos indicadores  
- [ ] Criar relatórios automatizados
- [ ] Implementar notificações por região
- [ ] Adicionar análise preditiva

## Suporte
Para dúvidas sobre o sistema de mapas, consulte a documentação técnica ou entre em contato com a equipe de desenvolvimento do APS Digital.