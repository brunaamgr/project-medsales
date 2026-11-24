# Plano — Sistema Único Modular (PHP + ValueHost)

**Data:** 19/11/2026  
**Versão:** 1.0 (draft)  
**Status:** substitui a arquitetura de “3 SaaS” dos documentos anteriores.  
**Nome provisório do sistema:** `restruturacao` (será renomeado).  
**Destino:** um único sistema PHP, modular, multi-tenant, hospedado na ValueHost, com MySQL.

**Decisões confirmadas em 19/11/2026:**

- Framework: **Laravel**.
- Banco: **um único MySQL**, sem banco dedicado por cliente.
- Equipe: **1 dev com apoio de IA**.
- Domínio base: `medsalesbr.com`.
- Cliente novo: **subdomínio obrigatório** no cadastro.
- Primeiro domínio: `https://mychelleamaral.medsalesbr.com/`.
- Isolamento: **tenant_id em todas as tabelas + blindagem na aplicação**, já que MySQL não tem RLS.

---

## 0. Decisão de arquitetura

**É um sistema, não três.**

- Um código-fonte.
- Um deploy principal (mais staging).
- **Um banco MySQL** (sem banco dedicado por cliente).
- Vários clientes (tenants).
- Vários módulos no mesmo sistema.
- Funcionalidades liberadas por cliente dentro de cada módulo.

As três URLs atuais passam a ser **entradas de módulo/tenant** do mesmo sistema:

| URL atual | Tenant | Módulo principal |
|---|---|---|
| `acompanhamentos.mychelleamaral.com.br/entrar` | Mychelle | `acompanhamento` |
| `operacaopinhabel.medsalesbr.com` | Pinhabel | `operacao` |
| `precificacao.institutomentalize.com.br` | Mentalize | `precificacao` |

Um cliente novo não ganha projeto, repositório ou deploy novo. Ganha:

1. Um tenant.
2. Um domínio/subdomínio.
3. Branding.
4. Usuários e permissões.
5. Módulos habilitados.
6. Funcionalidades habilitadas dentro de cada módulo.

**Migração:** Vercel e Supabase continuam no ar durante a transição; o desligamento é a última etapa, após cutover validado.

---

## 1. Stack recomendada

| Camada | Escolha | Motivo |
|---|---|---|
| Linguagem | PHP 8.2+ | Compatível com ValueHost/cPanel |
| Framework | **Laravel 11/12** | Auth, migrations, policies, filas, testes, segurança |
| Banco | MySQL 8 / MariaDB 10.6+ | Padrão em hospedagem compartilhada |
| Frontend | Blade + Tailwind + Alpine.js | Server-rendered; sem runtime Node na hospedagem |
| Build | Vite | Build local/CI; sobe assets compilados |
| Auth | Laravel Auth + Fortify/Breeze + TOTP | Controle total, sem dependência externa |
| Permissões | Gates/Policies + RBAC custom | Papéis, permissões e escopos |
| Filas | Database queue + cron | Sem Redis na ValueHost |
| Cache | Database/file cache | Compatível com shared hosting |
| Storage | Disco privado + URLs assinadas | Fora do `public_html` |
| E-mail | SMTP ValueHost | SPF/DKIM/DMARC obrigatórios |
| Testes | Pest/PHPUnit + Laravel Dusk/Playwright | Unit, feature, e2e |
| Qualidade | Pint + PHPStan/Larastan | Padrão e análise estática |
| CI/CD | GitHub Actions + SSH/rsync | Build, testes, deploy |
| Observabilidade | Sentry + logs + uptime | Erros, performance, alertas |

> **Recomendação forte:** Laravel, não PHP puro. O projeto tem dados de saúde, multi-tenancy, RBAC, filas, migrations e auditoria; reconstruir isso em PHP puro aumenta risco e prazo.

---

## 2. Premissas e validação da ValueHost

Antes de escrever código, validar a hospedagem. **Isso é um gate Go/No-Go.**  
Checklist detalhado e mensagem pronta para o suporte: `CHECKLIST-VALUEHOST.md`.

> **Atualização 19/11:** o cliente já possui uma aplicação Laravel rodando na ValueHost, com **PHP 8.3**, **SSH + cPanel** e **backup diário**.  
> **DNS confirmado (23/11):** a ValueHost confirmou que aceita `medsalesbr.com` como addon/primary domain **mantendo os nameservers no Squarespace/Google**, e que a criação de subdomínios funciona com DNS externo. O site e o e-mail atuais não precisam ser movidos.  
> **Pendência:** confirmar o IP do plano novo e testar AutoSSL no primeiro subdomínio (`teste.medsalesbr.com`).  
> Análise detalhada dos registros e plano de mudança: `DNS-MEDSALESBR.md`.

### Checklist técnico da ValueHost

- [ ] PHP 8.2 ou 8.3 disponível e selecionável no cPanel.
- [ ] Extensões: `pdo_mysql`, `mbstring`, `openssl`, `json`, `gd`, `zip`, `intl`, `bcmath`, `fileinfo`, `curl`.
- [ ] Composer disponível via SSH ou possibilidade de deploy de `vendor/`.
- [ ] SSH/rsync ou Git deploy; se não houver, FTP/arquivo.
- [ ] MySQL 8 ou MariaDB 10.6+; `utf8mb4`; JSON; InnoDB.
- [ ] Cron jobs (mínimo 1x/minuto ou 5x/minuto).
- [ ] SSL/Let's Encrypt; subdomínios e domínios adicionais.
- [ ] Wildcard `*.medsalesbr.com` e SSL wildcard (ou emissão automática por subdomínio).
- [ ] Mecanismo para criar subdomínio/SSL no cPanel (manual ou API) no cadastro do cliente.
- [ ] `mod_rewrite`/`.htaccess`; PHP-FPM; limites de memória/upload.
- [ ] Espaço em disco e limite de inodes.
- [ ] Backups automáticos da hospedagem + possibilidade de dump próprio.
- [ ] SMTP, SPF/DKIM/DMARC.
- [ ] Saída HTTPS para APIs externas (Anthropic, Kommo, Meta, ZapSign, Resend).
- [ ] Possibilidade de apontar o document root para `public/`.
- [ ] WAF/Cloudflare ou proteção equivalente.
- [ ] Acesso a logs de erro e rotação.

### Se falhar

Se a ValueHost não tiver PHP 8.2+, Composer/SSH, cron confiável ou MySQL adequado:

- **Plano B:** VPS gerenciada (Hostinger VPS, Contabo, DigitalOcean) com PHP-FPM + MySQL + Redis.
- **Plano C:** hospedagem PHP gerenciada que atenda aos requisitos.
- **Não iniciar** o desenvolvimento sem esse gate; migrar depois é caro.

---

## 3. Arquitetura do sistema único

```text
┌─────────────────────────────────────────────────────────────┐
│                    SISTEMA MEDSALES (PHP/Laravel)            │
│                                                             │
│  CORE                                                       │
│  auth · tenants · usuários · papéis · permissões · módulos  │
│  funcionalidades · auditoria · configurações · arquivos     │
│  notificações · integrações · LGPD                          │
│                                                             │
│  MÓDULOS                                                    │
│  acompanhamento · operacao · precificacao · bi · sites      │
└─────────────────────────────────────────────────────────────┘
              │
              ▼
        MySQL (ValueHost)
        Storage privado
        Cron/Scheduler
        SMTP
        APIs externas
```

### 3.1 Estrutura de pastas

```text
app/
  Core/
    Auth/
    Tenancy/
    Users/
    Roles/
    Permissions/
    Modules/
    Features/
    Audit/
    Settings/
    Files/
    Notifications/
    Integrations/
  Modules/
    Acompanhamento/
      Http/
      Models/
      Services/
      Policies/
      Routes/
      Views/
      Database/Migrations/
      Tests/
      module.php
    Operacao/
    Precificacao/
    Bi/
  Shared/
    Support/
    ValueObjects/
    Exceptions/
    Traits/
config/
database/
  migrations/
  seeders/
resources/
  views/
  js/
  css/
routes/
storage/
tests/
```

### 3.2 Manifesto de módulo

Cada módulo tem um `module.php`:

```php
return [
    'slug' => 'precificacao',
    'name' => 'Precificação',
    'version' => '1.0.0',
    'dependencies' => ['core'],
    'default_enabled' => false,
    'permissions' => [
        'precificacao.tabela.view',
        'precificacao.tabela.edit',
        'precificacao.simulador.view',
        'precificacao.simulador.create',
        'precificacao.proposta.approve',
    ],
    'features' => [
        'simulador' => ['default' => true],
        'bonificacao' => ['default' => false],
        'proposta' => ['default' => true],
    ],
];
```

O `ModuleServiceProvider` registra rotas, views, permissões, menu e migrations.

---

## 4. Módulos × funcionalidades × clientes

### 4.1 Conceitos

| Conceito | O que é | Exemplo |
|---|---|---|
| **Sistema** | Um codebase/deploy | `restruturacao` |
| **Tenant** | Cliente | Pinhabel, Mychelle, Mentalize |
| **Módulo** | Área funcional ampla | `acompanhamento`, `operacao`, `precificacao` |
| **Funcionalidade** | Capacidade dentro do módulo | exames, estoque, bonificação, proposta |
| **Permissão** | Ação granular | `operacao.cirurgia.approve` |
| **Escopo** | Abrangência | tenant, unit, own, assigned |
| **Role** | Pacote de permissões | médico, recepção, financeiro |

### 4.2 Tabelas de entitlement

- `modules`
- `features`
- `tenant_modules` (enabled, settings)
- `tenant_features` (enabled, settings)
- `permissions`
- `roles`
- `role_permissions`
- `memberships`
- `membership_roles`
- `membership_units`

### 4.3 Exemplo

**Pinhabel — módulo `operacao`:**

- Funcionalidades: agenda, cirurgia, financeiro, estoque, contratos, BI.
- Roles: admin, médico, enfermagem, recepção, financeiro.
- Escopos: tenant, unidade, próprios casos.

**Mychelle — módulo `acompanhamento`:**

- Funcionalidades: diário, medidas, fotos, exames, mensagens, plano, pagamentos.
- Roles: admin, nutricionista, secretária, paciente.
- Escopos: tenant, próprios pacientes, portal do paciente.

**Mentalize — módulo `precificacao`:**

- Funcionalidades: tabela de preços, simulador, bonificação, proposta.
- Roles: admin, comercial, gestor.
- Escopos: tenant.

### 4.4 Regra de ouro

> **Módulo liberado não significa funcionalidade liberada.** O menu, a rota, a API e a permissão verificam os dois níveis: módulo + funcionalidade.

Middleware:

```php
Route::middleware(['auth', 'tenant', 'module:operacao', 'feature:operacao.estoque', 'permission:operacao.estoque.view'])
    ->get('/estoque', [EstoqueController::class, 'index']);
```

### 4.5 Cadastro de cliente e subdomínio

Todo cliente novo precisa de um **subdomínio único** em `medsalesbr.com`.

**Exemplo:**

- Cliente: Mychelle Amaral
- Subdomínio: `mychelleamaral`
- URL: `https://mychelleamaral.medsalesbr.com`
- Domínio antigo: `https://acompanhamentos.mychelleamaral.com.br` (custom domain/redirect)

**Modelo:**

```text
tenants
  id, name, slug, subdomain (unique), status, plan_id, created_at

tenant_domains
  id, tenant_id, host, type (subdomain|custom), is_primary,
  verified_at, ssl_status, created_at
```

**Fluxo de cadastro:**

1. Admin informa nome, subdomínio, e-mail do owner, módulos e funcionalidades.
2. Sistema valida disponibilidade e palavras reservadas.
3. Cria tenant + `tenant_domains` (`{subdomain}.medsalesbr.com`).
4. Cria membership do owner e envia convite.
5. Habilita módulos/funcionalidades.
6. Exibe a URL e o status do SSL.
7. Tudo auditado.

**Regras do subdomínio:**

- 3–63 caracteres.
- Letras minúsculas, números e hífen.
- Não começar/terminar com hífen.
- Sem pontos, underscores, espaços ou acentos.
- Único globalmente.
- Reservados: `www`, `app`, `admin`, `api`, `mail`, `smtp`, `ftp`, `staging`, `dev`, `test`, `status`, `suporte`, `help`, `cdn`, `static`, `assets`, `login`, `sso`, `auth`, `painel`, `sistema`, `medsales`.
- Bloquear IDN/homograph no início.
- Normalizar para minúsculo antes de validar.

**Resolução do tenant:**

1. Host exato em `tenant_domains` (custom domain).
2. Sufixo `.medsalesbr.com` → extrai subdomínio.
3. Path `/t/{subdomain}` apenas em staging.
4. Se não encontrar, 404 — nunca “tenant padrão” silencioso.

**Domínio customizado:**

- Campo opcional após o cadastro.
- Verificação por CNAME/TXT.
- SSL emitido após verificação.
- Domínio antigo pode redirecionar 301 para o canônico.
- Um tenant pode ter mais de um domínio (principal + legado).

**Ponto crítico ValueHost:** confirmar wildcard `*.medsalesbr.com` e SSL wildcard (ou emissão automática por subdomínio). Se não houver, usar Cloudflare/Let's Encrypt ou criar subdomínio/SSL via cPanel.

---

## 5. Multi-tenancy e controle de acesso

### 5.1 Modelo

```text
Tenant → Membership → Role → Permission → Scope → Module/Feature
```

- `users` global.
- `memberships` liga usuário × tenant.
- `roles` podem ser de sistema ou custom do tenant.
- `permissions` granulares.
- `role_permissions.scope`: `tenant`, `unit`, `own`, `assigned`.
- `tenant_modules` e `tenant_features` definem o que o cliente contratou.
- `module_settings` e `feature_settings` guardam configuração por tenant.

### 5.2 Como o tenant é resolvido

1. **Domínio/subdomínio** (`pinhabel.medsalesbr.com`, `acompanhamentos.mychelleamaral.com.br`).
2. **Path** para staging (`/t/pinhabel/...`).
3. **Sessão** guarda o tenant ativo; middleware revalida membership.
4. **Job/queue** carrega `tenant_id` no payload e restaura o contexto.

### 5.3 Sem RLS: o cuidado muda de lugar

O Supabase tinha RLS. No MySQL/ValueHost **não existe RLS nativa**. Como a decisão é **um único banco**, o isolamento é responsabilidade da aplicação. A segurança passa a depender de:

- Trait `BelongsToTenant` + global scope em todos os models.
- `TenantContext` singleton por request/job.
- Repositórios/serviços que sempre filtram `tenant_id`.
- Middleware `tenant` e `permission`.
- Policies/Gates para ações.
- `tenant_id` obrigatório em todas as tabelas de domínio, com índice e FK.
- Route model binding sempre com `where tenant_id`.
- `tenant_id` definido pelo contexto (subdomínio/sessão), nunca pelo body.
- Testes de isolamento por model/tabela com dois tenants.
- Code review obrigatório para queries novas.
- PHPStan/Larastan e regra customizada para banir `withoutGlobalScope`, `DB::table()` sem tenant e `find()` sem escopo.
- Auditoria e alerta de tentativa de acesso cruzado.

**Regra:** nenhum `Model::query()` sem tenant scope; nenhum `DB::table()` sem `where tenant_id`; nenhum `find($id)` sem validar tenant; nenhum `tenant_id` vindo do formulário.

**Blindagem real:** sem RLS, o banco não impede fisicamente que o app leia outro tenant. A proteção é o código + testes + revisão + análise estática. Se um dia um contrato exigir isolamento físico, a opção é schema/banco dedicado, mas **não agora**.

### 5.4 Escopos

- `tenant`: tudo do tenant.
- `unit`: unidades vinculadas.
- `own`: registros do próprio usuário.
- `assigned`: ACL pontual.

Aplicar no backend + policies + queries; a UI apenas esconde.

### 5.5 Painel de administração

- Usuários: convidar, editar, suspender, reativar, resetar MFA, revogar sessões.
- Papéis: sistema + custom; matriz de permissões; escopos.
- Módulos: habilitar/desabilitar por tenant.
- Funcionalidades: habilitar/desabilitar dentro do módulo.
- Auditoria: quem, o quê, quando, tenant, IP, resultado.
- Segurança: MFA, sessão, senha, IP allowlist.
- Tenants: branding, domínio, plano, limites.

### 5.6 Segurança de autenticação

- Cadastro staff somente por convite.
- Paciente com auth separada (app/portal; mesmo banco ou projeto separado).
- Senha: Argon2id, mínimo 12, breached-password check, rate limit.
- MFA TOTP para admins/staff; recovery codes.
- Sessão em banco; cookies `HttpOnly`, `Secure`, `SameSite=Lax`.
- Idle timeout e timeout absoluto; revogação ao suspender/trocar papel.
- Step-up MFA para ações sensíveis.
- CSRF, CAPTCHA, rate limit em login/convite/reset.
- Auditoria imutável.

---

## 6. Segurança específica de PHP/ValueHost

### 6.1 Hardening

- `.env` fora do `public_html`.
- Document root apontando para `public/`.
- `open_basedir` configurado.
- Funções perigosas desabilitadas (`exec`, `shell_exec`, `passthru`, `system`, `proc_open` etc., se possível).
- `display_errors=Off` em produção; log em arquivo.
- `expose_php=Off`.
- Diretório listing desabilitado.
- Uploads fora do public; validação de MIME real; nomes UUID.
- Permissões de arquivo 644/755.
- Banco com usuário próprio, sem `root`, sem `FILE`.
- SSL obrigatório; HSTS; redirect HTTP → HTTPS.
- CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- WAF/Cloudflare na frente.
- Backups criptografados e offsite.
- Logs sem PII/segredos.

### 6.2 Shared hosting: limitações e contornos

| Limitação | Contorno |
|---|---|
| Sem Redis | Database cache/queue |
| Sem worker longo | Cron + `queue:work --stop-when-empty` |
| Sem websocket | Polling/SSE curto |
| Sem RLS | Tenant scope em app + testes + análise estática + banco único |
| Sem Node em runtime | Build local/CI; assets compilados |
| Limite de memória/tempo | Jobs em chunks; paginação; cache |
| Sem backups gerenciados | `mysqldump` + storage offsite + restore testado |

### 6.3 Dados de saúde

- Criptografia de campos sensíveis (Laravel encrypted casts) quando aplicável.
- Storage privado; URL assinada e expiração curta.
- Minimização de PII; retenção definida.
- DPA com fornecedores; consentimento para IA.
- Auditoria de acesso/exportação.
- LGPD: base legal, direitos do titular, incidentes.

---

## 7. Migração Supabase/Postgres → MySQL/ValueHost

### 7.1 Inventário

- 2 projetos Supabase: `xgyv…` (Pinhabel) e `srby…` (MedSales).
- ~440 tabelas no total.
- 22 Edge Functions (16 sem código no Git).
- 58 usuários.
- ~156 MB de storage.
- Views, functions, triggers, policies, pg_cron.

### 7.2 Mapeamento

| Postgres/Supabase | MySQL/Laravel |
|---|---|
| `uuid` | `char(36)` ou `binary(16)` |
| `jsonb` | `json` |
| `timestamptz` | `datetime` UTC |
| Array | JSON |
| Enum | `varchar` + check |
| RLS | Policies/Gates + tenant scope |
| Edge Function | Controller/Service/Command |
| pg_cron | Laravel Scheduler |
| Supabase Storage | Disco privado/S3 |
| Auth | Laravel Auth + reset/convite |
| Realtime | Polling/SSE |

### 7.3 Ordem

1. Exportar schema e dados em modo read-only.
2. Criar migrations Laravel.
3. ETL para MySQL; validar contagens/checksums.
4. Migrar storage; verificar hash.
5. Migrar auth: convite/reset (não migrar hash sem validar).
6. Reescrever Edge Functions.
7. Dual-run por módulo/tenant.
8. Cutover com freeze curto e rollback.
9. Desligar Vercel/Supabase após 30 dias de estabilidade.

### 7.4 Regras

- Nunca migrar PII para dev/staging sem mascarar.
- Nunca migrar todos os tenants de uma vez.
- Um módulo piloto (`precificacao`) antes de `acompanhamento`/`operacao`.
- Validar LGPD/data residency antes de decidir região do banco.

---

## 8. Etapas do projeto

### Etapa 0 — Descoberta e Go/No-Go (semana 1)

**Objetivo:** provar que ValueHost + PHP/MySQL atendem e fechar escopo.

- Validar checklist da ValueHost.
- Obter acessos: Vercel, Supabase, GitHub, DNS, e-mail.
- Inventariar tabelas, functions, storage e usuários.
- Mapear módulos/funcionalidades por tenant.
- Definir piloto: `precificacao` + Mentalize.
- Criar risk register e plano de comunicação.
- **Gate:** Go/No-Go da hospedagem e do escopo.

### Etapa 1 — Fundação (semanas 2–6)

**Objetivo:** esqueleto do sistema único.

- Laravel + estrutura modular.
- Tenants, users, memberships, roles, permissions, modules, features.
- Admin shell; login; convite; auditoria.
- Layout Blade/Tailwind; design system.
- Migrations core; seeds; tenant resolver.
- CI/CD; staging; backups; Sentry; headers.
- **Gate:** login + isolamento entre tenants + RBAC básico.

### Etapa 2 — Identidade e acesso (semanas 6–10)

**Objetivo:** painel de usuários e controle de acesso completo.

- CRUD de usuários, convites, suspensão, reativação.
- Papéis e permissões; escopos tenant/unit/own/assigned.
- MFA, sessões, revogação, step-up.
- Módulos/funcionalidades por tenant.
- Auditoria e testes de isolamento.
- **Gate:** matriz de acesso validada; 0 vazamento entre tenants.

### Etapa 3 — Módulo Precificação (semanas 10–15)

**Objetivo:** piloto real.

- Migrar schema/dados do Supabase.
- Reescrever telas em Blade/Alpine.
- Funcionalidades: tabela, simulador, bonificação, proposta.
- Permissões e escopos.
- Testes; dual-run; cutover Mentalize.
- **Gate:** piloto aprovado; rollback testado.

### Etapa 4 — Módulo Acompanhamento (semanas 15–26)

**Objetivo:** portal do paciente e equipe.

- Pacientes, diário, medidas, fotos, exames, mensagens, plano.
- Auth de paciente; storage privado; notificações.
- IA com LGPD/DPA; integrações.
- Migração Mychelle; dual-run.
- **Gate:** segurança/LGPD aprovados; piloto Mychelle.

### Etapa 5 — Módulo Operação (semanas 26–38)

**Objetivo:** operação clínica completa.

- Agenda, cirurgias, financeiro, estoque, contratos, BI.
- Pinhabel no mesmo banco, com isolamento reforçado e testes dedicados.
- Migração de dados; integrações; relatórios.
- **Gate:** P0 fechado; piloto Pinhabel; performance.

### Etapa 6 — Consolidação e desligamento (semanas 38–42)

**Objetivo:** virar a chave.

- Migrar tenants restantes e sites.
- Backups/DR drill; auditoria externa.
- Treinamento e documentação.
- Desligar Vercel/Supabase após 30 dias.
- **Gate:** produção estável, restore < 1h, 0 P0.

> **Prazo realista:** com **1 dev + IA**, 8–10 meses (42 semanas). Com 2 devs, ~5–6 meses. O prazo depende da qualidade dos dados, do acesso às 16 Edge Functions sem código e da velocidade de validação dos clientes. IA acelera código, mas não substitui revisão de segurança/tenant isolation.

---

## 9. Cronograma resumido

| Semana | Entrega |
|---|---|
| 1 | ValueHost validada; escopo; piloto definido |
| 2–6 | Fundação Laravel; auth; tenants; subdomínio; RBAC; admin shell |
| 6–10 | Usuários, papéis, permissões, MFA, auditoria |
| 10–15 | Módulo Precificação + piloto Mentalize |
| 15–26 | Módulo Acompanhamento + piloto Mychelle |
| 26–38 | Módulo Operação + piloto Pinhabel |
| 38–42 | Consolidação, DR, treinamento, desligamento |

---

## 10. Riscos e mitigação

| Risco | Impacto | Mitigação |
|---|---|---|
| ValueHost não suportar PHP 8.2/Composer | Alto | Gate Go/No-Go; plano B VPS |
| Perder RLS do Supabase | Alto | Tenant scope + testes + análise estática + banco único blindado na aplicação |
| 16 Edge Functions sem código | Alto | Extrair/reescrever antes do cutover |
| Dados de saúde no shared hosting | Alto | Criptografia, storage privado, backup, DPA |
| Migração de dados complexa | Alto | ETL por módulo; checksums; dual-run |
| Shared hosting sem worker | Médio | Cron + database queue + jobs curtos |
| Sem Redis/websocket | Médio | Cache em banco; polling |
| Escopo grande demais | Alto | Piloto precificação; um módulo por vez |
| Bus factor 1 | Alto | Documentação, code review, 2º dev |
| LGPD/IA | Alto | DPA, consentimento, minimização |

---

## 11. Critérios de aceite

- [ ] ValueHost validada e documentada.
- [ ] 1 sistema, 1 deploy, 1 banco MySQL.
- [ ] 0 vazamento entre tenants nos testes.
- [ ] RBAC + escopos + módulos/funcionalidades funcionando.
- [ ] Painel de usuários completo.
- [ ] MFA e sessões seguras.
- [ ] Migrations e backups testados.
- [ ] CI/CD e staging.
- [ ] Módulo piloto em produção.
- [ ] Vercel/Supabase desligados após estabilidade.
- [ ] Documentação e treinamento.

---

## 12. Decisões confirmadas e pendentes

### Confirmadas

- **Laravel** como framework.
- **Um único MySQL**, sem banco dedicado por cliente.
- **1 dev + IA**.
- **Subdomínio obrigatório** no cadastro de cliente.
- **Mychelle** começa com `https://mychelleamaral.medsalesbr.com/`.
- **Módulos + funcionalidades** por tenant.

### Pendentes

1. **ValueHost atende ao checklist?** Executar `CHECKLIST-VALUEHOST.md`.
2. **Wildcard `*.medsalesbr.com` e SSL wildcard** estão disponíveis?
3. **Paciente:** auth separada ou mesma base? Recomendação: mesma base no início, app/portal separado e tenant scope rígido.
4. **MFA obrigatório para todos os staff?** Recomendação: sim; admins obrigatório.
5. **Quem faz code review?** Com 1 dev + IA, recomenda-se um revisor externo/segurança nos gates de auth/tenant isolation.
6. **Orçamento:** ValueHost, backup offsite, Sentry.
7. **Piloto:** criar Mychelle como primeiro tenant/subdomínio no core; primeiro módulo funcional recomendado é `precificacao` + Mentalize (menor superfície). Se preferir, começar pelo `acompanhamento` da Mychelle, mas o prazo aumenta.

### O que é “piloto”

Piloto é o **primeiro módulo/cliente a entrar no sistema novo**, para validar a arquitetura antes de migrar todos. Recomendação:

- **Piloto de plataforma:** Mychelle como primeiro tenant/subdomínio no core (auth, admin, RBAC).
- **Piloto funcional:** `precificacao` + Mentalize (menor escopo, menor risco).
- **Depois:** `acompanhamento` + Mychelle; `operacao` + Pinhabel.

---

## 13. Trello

A estrutura de listas, cartões, checklists e etiquetas está em:

`TRELLO-ESTRUTURA.md`

Use como board de execução. Cada cartão tem checklist e critério de aceite.
