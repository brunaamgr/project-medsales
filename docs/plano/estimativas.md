# Estimativas de Desenvolvimento — Sistema MedSales

**Data:** 23/11/2026  
**Base:** 1 dev + IA, **3h efetivas = 1 dia útil**.  
**Total estimado:** 184,5h = **62 dias úteis** de execução.  
**Calendário de desenvolvimento:** 24/11/2026 a 17/02/2027.  
**Observação:** prazos de execução técnica; validação, migração e retorno do cliente podem ajustar o calendário.

## Resumo por fase

| Fase | Horas | Dias úteis | Início | Fim |
|---|---:|---:|---|---|
| 00 · Descoberta & Go/No-Go | 8h | 3 | 24/11/2026 | 26/11/2026 |
| 01 · Fundação | 16,5h | 6 | 26/11/2026 | 04/12/2026 |
| 02 · Identidade & Acesso | 8h | 3 | 04/12/2026 | 08/12/2026 |
| 03 · Infra & Deploy ValueHost | 11h | 4 | 08/12/2026 | 14/12/2026 |
| 04 · Módulo Precificação | 16h | 6 | 14/12/2026 | 21/12/2026 |
| 05 · Módulo Acompanhamento | 28h | 10 | 21/12/2026 | 04/01/2027 |
| 06 · Módulo Operação | 33h | 11 | 04/01/2027 | 19/01/2027 |
| 07 · Dados & Migração | 24h | 8 | 19/01/2027 | 29/01/2027 |
| 08 · Segurança & LGPD | 8h | 3 | 29/01/2027 | 02/02/2027 |
| 09 · QA & Homologação | 12h | 4 | 02/02/2027 | 08/02/2027 |
| 10 · Lançamento & Desligamento | 7h | 3 | 08/02/2027 | 11/02/2027 |
| 11 · Cliente & Comunicação | 6h | 2 | 11/02/2027 | 15/02/2027 |
| 12 · Comercial & Produto | 7h | 3 | 15/02/2027 | 17/02/2027 |
| **Total** | **184,5h** | **62** | **24/11/2026** | **17/02/2027** |

## Detalhe por card

### 00 · Descoberta & Go/No-Go — 8h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| D00 | Validar ValueHost: PHP, MySQL, Composer, cron | 1 | 24/11/2026 |
| D00b | Validar subdomínio wildcard e SSL | 1 | 24/11/2026 |
| D01 | Mapear acessos e responsáveis | 0,5 | 24/11/2026 |
| D02 | Inventariar Supabase | 1 | 25/11/2026 |
| D03 | Inventariar Vercel e domínios | 0,5 | 25/11/2026 |
| D04 | Definir módulos e funcionalidades por tenant | 1 | 25/11/2026 |
| D05 | Definir piloto | 0,5 | 25/11/2026 |
| D06 | Criar risk register e plano LGPD | 1 | 26/11/2026 |
| D07 | Go/No-Go da ValueHost | 1,5 | 26/11/2026 |

### 01 · Fundação — 16,5h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| F01 | Criar projeto Laravel modular | 2 | 27/11/2026 |
| F02 | Configurar ambientes | 1 | 27/11/2026 |
| F03 | CI/CD e deploy ValueHost | 1,5 | 30/11/2026 |
| F04 | Design system e layout | 2 | 30/11/2026 |
| F05 | Migrations core | 2 | 01/12/2026 |
| F06 | Tenant resolver | 1 | 01/12/2026 |
| F06b | Cadastro de tenant + subdomínio | 2 | 02/12/2026 |
| F07 | Auditoria base | 1,5 | 02/12/2026 |
| F08 | Seeds de papéis e permissões | 1,5 | 03/12/2026 |
| F09 | Admin shell e login | 2 | 04/12/2026 |

### 02 · Identidade & Acesso — 8h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| A01 | CRUD de usuários e convites | 1,5 | 04/12/2026 |
| A02 | Papéis e matriz de permissões | 1,5 | 07/12/2026 |
| A03 | Módulos e funcionalidades por tenant | 1 | 07/12/2026 |
| A04 | MFA TOTP | 0,5 | 07/12/2026 |
| A05 | Sessões e revogação | 0,5 | 07/12/2026 |
| A06 | Policies, Gates e escopos | 1 | 08/12/2026 |
| A07 | Testes de isolamento | 1 | 08/12/2026 |
| A08 | Tenant isolation gate (sem RLS) | 1 | 08/12/2026 |

### 03 · Infra & Deploy ValueHost — 11h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| I01 | Hardening da ValueHost | 1 | 09/12/2026 |
| I02 | Cron e scheduler | 1 | 09/12/2026 |
| I03 | Fila em banco | 1 | 09/12/2026 |
| I04 | Storage privado e URLs assinadas | 1,5 | 10/12/2026 |
| I05 | SMTP e deliverability | 1 | 10/12/2026 |
| I06 | Backup e restore | 1,5 | 11/12/2026 |
| I07 | Observabilidade | 1,5 | 11/12/2026 |
| I08 | WAF/Cloudflare e SSL | 1 | 11/12/2026 |
| I09 | SSL wildcard e domínios customizados | 1,5 | 14/12/2026 |

### 04 · Módulo Precificação — 16h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| P01 | Schema e migrations | 1 | 14/12/2026 |
| P02 | Migração de dados | 2 | 15/12/2026 |
| P03 | Tabela de preços | 2 | 16/12/2026 |
| P04 | Simulador | 3 | 17/12/2026 |
| P05 | Bonificações | 2 | 17/12/2026 |
| P06 | Propostas | 3 | 18/12/2026 |
| P07 | Piloto Mentalize | 3 | 21/12/2026 |

### 05 · Módulo Acompanhamento — 28h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| AC01 | Schema e migrations | 2 | 22/12/2026 |
| AC02 | Auth do paciente | 3 | 23/12/2026 |
| AC03 | Painel da equipe | 2 | 24/12/2026 |
| AC04 | Diário, medidas e fotos | 4 | 25/12/2026 |
| AC05 | Exames e documentos | 3 | 28/12/2026 |
| AC06 | Mensagens e notificações | 3 | 29/12/2026 |
| AC07 | Planos e acompanhamento | 3 | 30/12/2026 |
| AC08 | IA e LGPD | 4 | 31/12/2026 |
| AC09 | Piloto Mychelle | 4 | 04/01/2027 |

### 06 · Módulo Operação — 33h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| OP01 | Schema e migrations | 2 | 04/01/2027 |
| OP02 | Agenda | 4 | 06/01/2027 |
| OP03 | Cirurgias | 4 | 07/01/2027 |
| OP04 | Financeiro | 4 | 08/01/2027 |
| OP05 | Estoque | 3 | 11/01/2027 |
| OP06 | Contratos | 3 | 12/01/2027 |
| OP07 | BI | 5 | 14/01/2027 |
| OP08 | Integrações | 4 | 15/01/2027 |
| OP09 | Piloto Pinhabel | 4 | 19/01/2027 |

### 07 · Dados & Migração — 24h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| M01 | Mapeamento Postgres → MySQL | 1,5 | 19/01/2027 |
| M02 | ETL por módulo | 4 | 20/01/2027 |
| M03 | Usuários e auth | 2 | 21/01/2027 |
| M04 | Storage | 2 | 22/01/2027 |
| M05 | Edge Functions | 8 | 26/01/2027 |
| M06 | pg_cron → Scheduler | 1,5 | 27/01/2027 |
| M07 | Dual-run e cutover | 3 | 28/01/2027 |
| M08 | Desligamento Vercel/Supabase | 2 | 29/01/2027 |

### 08 · Segurança & LGPD — 8h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| S00 | Conter P0 do legado (paralelo) | 2 | 29/01/2027 |
| S01 | Política de segurança do novo sistema | 1,5 | 01/02/2027 |
| S02 | LGPD e dados de saúde | 2 | 01/02/2027 |
| S03 | Teste de penetração | 2,5 | 02/02/2027 |

### 09 · QA & Homologação — 12h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| Q01 | Estratégia de testes | 1,5 | 03/02/2027 |
| Q02 | Matriz de isolamento | 2 | 03/02/2027 |
| Q03 | UAT por cliente | 3 | 04/02/2027 |
| Q04 | Performance | 2 | 05/02/2027 |
| Q05 | Acessibilidade e usabilidade | 1,5 | 08/02/2027 |
| Q06 | Go-live checklist | 2 | 08/02/2027 |

### 10 · Lançamento & Desligamento — 7h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| L01 | Cutover do módulo | 2 | 09/02/2027 |
| L02 | Treinamento e documentação | 2 | 10/02/2027 |
| L03 | Desligamento Vercel/Supabase | 2 | 10/02/2027 |
| L04 | Retrospectiva e próximos passos | 1 | 11/02/2027 |

### 11 · Cliente & Comunicação — 6h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| C01 | Kickoff e alinhamento de expectativas | 1 | 11/02/2027 |
| C02 | Aprovação do escopo e do MVP | 1 | 11/02/2027 |
| C03 | Plano de comunicação de mudanças | 1 | 12/02/2027 |
| C04 | Usuários de teste e acessos | 0,5 | 12/02/2027 |
| C05 | UAT por módulo | 1 | 12/02/2027 |
| C06 | Treinamento e go-live | 1 | 12/02/2027 |
| C07 | Retrospectiva e próximos passos | 0,5 | 15/02/2027 |

### 12 · Comercial & Produto — 7h

| Card | Título | Horas | Prazo |
|---|---|---:|---|
| CM01 | Definir pacotes comerciais | 1 | 15/02/2027 |
| CM02 | Definir preços e add-ons | 1 | 15/02/2027 |
| CM03 | Criar one-page comercial | 1 | 16/02/2027 |
| CM04 | Criar apresentação para o cliente | 1 | 16/02/2027 |
| CM05 | Contrato, SLA e LGPD | 1 | 16/02/2027 |
| CM06 | Landing page do produto | 1 | 17/02/2027 |
| CM07 | Métricas de receita | 1 | 17/02/2027 |
