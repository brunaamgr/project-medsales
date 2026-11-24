# Especificação — Identidade, Usuários e Controle de Acesso (RBAC + Escopos)

**Data:** 17/11/2026  
**Versão:** 1.0 (draft)  
**Contexto:** SaaS MedSales (acompanhamento, operacao, precificacao) com múltiplos tenants, dados de saúde e necessidade de governança por perfil.  
**Objetivo:** permitir que cada usuário veja e faça somente o que foi liberado para o seu perfil, no tenant correto, com auditoria e defesa em profundidade.

---

## 1. Princípios

1. **Negar por padrão.** Só existe acesso se houver permissão explícita.
2. **Nunca confiar na UI.** O front esconde, mas quem decide é a API + banco (RLS).
3. **Tenant é a fronteira primária.** Um usuário de um cliente nunca acessa dados de outro.
4. **Papel + permissão + escopo + módulo.** “Nível” sozinho não representa necessidades diferentes.
5. **Menor privilégio.** Cada papel tem o mínimo necessário.
6. **Segregação de funções.** Financeiro não edita clínico; clínico não administra usuários; tenant admin não acessa a plataforma.
7. **Tudo auditável.** Quem, o quê, quando, em qual tenant, de onde, com qual resultado.
8. **Configuração, não fork.** Papéis e permissões são dados/configuração, não `if` no código.
9. **MFA e sessão curta para funções sensíveis.**
10. **Break-glass com prazo, motivo e auditoria.**

---

## 2. Modelo conceitual

```
Usuário (auth.users)
  └── Perfil (profiles)
        └── Vínculo (memberships) ── Tenant (tenants)
              ├── Papéis (membership_roles → roles)
              │     └── Permissões (role_permissions → permissions)
              ├── Unidades (membership_units → units)
              └── Módulos do tenant (tenant_modules)
```

| Conceito | Definição |
|---|---|
| **Tenant** | Cliente/empresa. Fronteira de dados. |
| **Membership** | Vínculo de um usuário com um tenant; status e papéis. |
| **Role** | Conjunto nomeado de permissões (ex.: `tenant_admin`, `medico`, `recepcao`). |
| **Permission** | Ação granular (`operacao.agenda.view`). |
| **Scope** | Abrangência da permissão: `tenant`, `unit`, `own`, `assigned`. |
| **Module** | Funcionalidade contratada/habilitada por tenant. |
| **Resource ACL** | Concessão pontual de acesso a um recurso específico. |

### Por que não só “nível”

Um `nível 2` pode significar “financeiro” ou “enfermagem”. Os dois precisam de acessos diferentes. Nível é útil para **hierarquia visual**, mas a autorização precisa ser:

> **role + permission + scope + module entitlement**

Se quiser manter “nível”, use como atributo do papel (`access_level`), não como única regra.

---

## 3. Escopos de acesso

| Escopo | Significado | Exemplo |
|---|---|---|
| `tenant` | Todo o tenant | Admin vê todas as agendas da clínica |
| `unit` | Unidades/filiais vinculadas ao membership | Gerente vê apenas a unidade Recife |
| `own` | Apenas registros dos quais é responsável | Médico vê seus próprios pacientes |
| `assigned` | Registros compartilhados explicitamente | Médico convidado para um caso específico |

Implementação:

- `role_permissions.scope` define o escopo máximo da permissão.
- `membership_units` limita unidades.
- `resource_acl` concede acesso pontual com expiração.
- RLS aplica o escopo; a API também valida.

---

## 4. Catálogo de permissões

Convenção: `<produto>.<recurso>.<ação>`

Ações comuns: `view`, `create`, `update`, `delete`, `approve`, `export`, `invite`, `manage`, `execute`.

### Core / administração

- `core.usuario.view`
- `core.usuario.invite`
- `core.usuario.update`
- `core.usuario.suspend`
- `core.usuario.reset_mfa`
- `core.role.view`
- `core.role.manage`
- `core.audit.view`
- `core.audit.export`
- `core.tenant.manage`
- `core.module.manage`
- `core.security.manage`
- `core.billing.manage`

### Acompanhamento

- `acompanhamento.paciente.view`
- `acompanhamento.paciente.create`
- `acompanhamento.paciente.update`
- `acompanhamento.diario.view`
- `acompanhamento.diario.create`
- `acompanhamento.exame.view`
- `acompanhamento.exame.upload`
- `acompanhamento.mensagem.view`
- `acompanhamento.mensagem.send`
- `acompanhamento.plano.view`
- `acompanhamento.plano.edit`
- `acompanhamento.pagamento.view`

### Operação

- `operacao.agenda.view`
- `operacao.agenda.manage`
- `operacao.cirurgia.view`
- `operacao.cirurgia.create`
- `operacao.cirurgia.approve`
- `operacao.paciente.view`
- `operacao.paciente.update`
- `operacao.financeiro.view`
- `operacao.financeiro.edit`
- `operacao.estoque.view`
- `operacao.estoque.edit`
- `operacao.contrato.view`
- `operacao.contrato.manage`

### Precificação

- `precificacao.tabela.view`
- `precificacao.tabela.edit`
- `precificacao.simulador.view`
- `precificacao.simulador.create`
- `precificacao.proposta.view`
- `precificacao.proposta.approve`

### BI

- `bi.dashboard.view`
- `bi.relatorio.view`
- `bi.export`

### Portal do paciente

- `portal.self.view`
- `portal.self.update`
- `portal.documento.view`
- `portal.mensagem.send`
- `portal.pagamento.view`

---

## 5. Papéis padrão

Papéis de sistema (`is_system=true`, `tenant_id=null`), clonáveis por tenant se necessário.

| Papel | Escopo | Pode | Não pode |
|---|---|---|---|
| `platform_admin` | Plataforma | Criar tenants, módulos, segurança, suporte | Ver dados clínicos sem break-glass auditado |
| `tenant_owner` | Tenant | Tudo no tenant, usuários, papéis, billing | Acessar plataforma/outros tenants |
| `tenant_admin` | Tenant | Usuários, papéis, módulos, auditoria | Billing, platform admin |
| `manager` | Tenant/unit | BI, agenda, comercial, aprovações | Editar clínico/financeiro sensível |
| `doctor` | Own/unit | Prontuário, exames, prescrições, agenda | Usuários, financeiro, plataforma |
| `nurse` | Own/unit | Sinais, exames, evoluções, agenda | Prescrição (se vedado), financeiro |
| `reception` | Unit | Agenda, cadastro, check-in, contato | Dados clínicos/financeiro |
| `finance` | Tenant | Financeiro, pagamentos, relatórios | Dados clínicos |
| `auditor` | Tenant | Auditoria, relatórios | Editar dados |
| `patient` | Own | Portal, documentos, mensagens | Qualquer área staff |
| `integration` | Escopo definido | API específica | Login humano |

### Matriz resumida

| Permissão | platform_admin | tenant_owner | tenant_admin | manager | doctor | nurse | reception | finance | auditor | patient |
|---|---|---|---|---|---|---|---|---|---|---|
| `core.usuario.invite` | ✅ | ✅ | ✅ | — | — | — | — | — | — | — |
| `core.role.manage` | ✅ | ✅ | ✅ | — | — | — | — | — | — | — |
| `core.audit.view` | ✅ | ✅ | ✅ | — | — | — | — | — | ✅ | — |
| `operacao.agenda.view` | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | — |
| `operacao.cirurgia.approve` | — | ✅ | ✅ | — | ✅ | — | — | — | — | — |
| `operacao.financeiro.edit` | — | ✅ | — | — | — | — | — | ✅ | — | — |
| `acompanhamento.paciente.view` | — | ✅ | ✅ | ✅ | ✅ | ✅ | — | — | ✅ | — |
| `acompanhamento.exame.upload` | — | ✅ | ✅ | — | ✅ | ✅ | — | — | — | — |
| `precificacao.tabela.edit` | — | ✅ | ✅ | ✅ | — | — | — | — | — | — |
| `bi.dashboard.view` | — | ✅ | ✅ | ✅ | — | — | — | ✅ | ✅ | — |
| `bi.export` | — | ✅ | — | — | — | — | — | — | — | — |
| `portal.self.view` | — | — | — | — | — | — | — | — | — | ✅ |

> A matriz final deve ser validada com cada cliente. O importante é que ela seja **dados**, não código.

---

## 6. Modelo de dados

```sql
-- Tenants
create table public.tenants (
  id uuid primary key default gen_random_uuid(),
  slug text unique not null,
  name text not null,
  status text not null default 'active',
  created_at timestamptz not null default now()
);

-- Perfis (1:1 com auth.users)
create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  full_name text,
  email citext,
  phone text,
  avatar_url text,
  status text not null default 'active', -- invited | active | suspended
  mfa_required boolean not null default false,
  last_login_at timestamptz,
  created_at timestamptz not null default now()
);

-- Vínculo usuário × tenant
create table public.memberships (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  status text not null default 'invited', -- invited | active | suspended
  invited_by uuid references public.profiles(id),
  joined_at timestamptz,
  created_at timestamptz not null default now(),
  unique (tenant_id, user_id)
);

-- Unidades/filiais
create table public.units (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  name text not null,
  status text not null default 'active'
);

create table public.membership_units (
  membership_id uuid references public.memberships(id) on delete cascade,
  unit_id uuid references public.units(id) on delete cascade,
  primary key (membership_id, unit_id)
);

-- Papéis (sistema ou custom por tenant)
create table public.roles (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid references public.tenants(id) on delete cascade,
  slug text not null,
  name text not null,
  is_system boolean not null default false,
  access_level int,
  created_at timestamptz not null default now(),
  unique (tenant_id, slug)
);

-- Catálogo de permissões
create table public.permissions (
  id uuid primary key default gen_random_uuid(),
  module text not null,
  resource text not null,
  action text not null,
  slug text unique not null,
  description text
);

-- Permissões por papel + escopo
create table public.role_permissions (
  role_id uuid references public.roles(id) on delete cascade,
  permission_id uuid references public.permissions(id) on delete cascade,
  scope text not null default 'tenant', -- tenant | unit | own | assigned
  primary key (role_id, permission_id)
);

-- Papéis por vínculo (múltiplos papéis)
create table public.membership_roles (
  membership_id uuid references public.memberships(id) on delete cascade,
  role_id uuid references public.roles(id) on delete cascade,
  primary key (membership_id, role_id)
);

-- Convites
create table public.invitations (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  email citext not null,
  role_id uuid references public.roles(id),
  unit_ids uuid[] not null default '{}',
  token_hash text not null,
  expires_at timestamptz not null,
  accepted_at timestamptz,
  created_by uuid references public.profiles(id),
  created_at timestamptz not null default now()
);

-- ACL pontual
create table public.resource_acl (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null,
  resource_type text not null,
  resource_id text not null,
  user_id uuid not null references public.profiles(id) on delete cascade,
  permission_id uuid references public.permissions(id),
  expires_at timestamptz,
  created_by uuid references public.profiles(id),
  created_at timestamptz not null default now()
);

-- Auditoria
create table public.audit_logs (
  id bigserial primary key,
  tenant_id uuid,
  actor_id uuid,
  action text not null,
  resource_type text,
  resource_id text,
  before jsonb,
  after jsonb,
  ip inet,
  user_agent text,
  result text not null default 'success',
  created_at timestamptz not null default now()
);

-- API keys de integração (não são usuários)
create table public.api_keys (
  id uuid primary key default gen_random_uuid(),
  tenant_id uuid not null references public.tenants(id) on delete cascade,
  name text not null,
  key_hash text not null,
  scopes text[] not null default '{}',
  last_used_at timestamptz,
  expires_at timestamptz,
  created_by uuid references public.profiles(id),
  created_at timestamptz not null default now()
);
```

### Funções auxiliares

- `current_tenant_id()`: tenant ativo do JWT/sessão, validado contra `memberships`.
- `meu_papel()`: papéis do membership ativo.
- `has_permission(slug)`: verifica permissão no tenant ativo.
- `has_permission(slug, scope)`: verifica permissão + escopo.
- `is_member_of(tenant_id)`: valida vínculo ativo.
- `can_access_resource(resource_type, resource_id)`: ACL/own/unit.

Todas `security definer` com `search_path` fixo, `stable`, e sem recursão em RLS.

### RLS — padrão

```sql
-- Exemplo: agenda do tenant
alter table public.operacao_agenda enable row level security;

create policy agenda_select on public.operacao_agenda
for select to authenticated
using (
  tenant_id = public.current_tenant_id()
  and public.has_permission('operacao.agenda.view')
  and (
    public.has_permission('operacao.agenda.view', 'tenant')
    or (public.has_permission('operacao.agenda.view', 'unit') and unit_id in (select unit_id from public.minhas_unidades()))
    or (public.has_permission('operacao.agenda.view', 'own') and owner_id = auth.uid())
  )
);
```

**Importante:** a API também valida; a RLS é a última linha, não a única.

---

## 7. Painel de administração

### 7.1 Telas

| Tela | Funções |
|---|---|
| **Visão geral** | Usuários ativos, convites pendentes, MFA, acessos recentes, alertas |
| **Usuários** | Listar, filtrar, buscar, convidar, editar, suspender, reativar, resetar MFA, revogar sessões |
| **Detalhe do usuário** | Perfil, vínculos, papéis, unidades, sessões, auditoria, permissões efetivas |
| **Papéis** | Listar sistema/custom, clonar, editar matriz, definir escopo |
| **Permissões** | Catálogo somente leitura; busca por módulo/ação |
| **Módulos** | Habilitar/desabilitar módulos por tenant; configurar |
| **Convites** | Pendentes, expirados, reenviar, cancelar |
| **Auditoria** | Filtros por ator, ação, recurso, data, resultado; exportar |
| **Segurança** | Política de MFA, sessão, senha, IP allowlist, SSO |
| **Tenant** | Branding, domínio, dados cadastrais, plano |

### 7.2 Fluxo de convite

1. Admin informa e-mail, papel(is), unidades e módulos.
2. Sistema cria `invitation` com token hasheado e validade (7 dias).
3. Envia e-mail (Resend/Supabase Auth) com link.
4. Usuário aceita, define senha, ativa MFA se exigido.
5. Sistema cria/ativa `membership`, `membership_roles`, `membership_units`.
6. Evento auditado; convite marcado como aceito.
7. Reenvio invalida token anterior.

### 7.3 Fluxos críticos

- **Suspender:** status `suspended`, revogar sessões, bloquear login, manter dados e auditoria.
- **Reativar:** status `active`, exigir novo login/MFA.
- **Trocar papel:** validar último owner, impedir auto-elevação, exigir motivo, invalidar cache/sessões, auditar.
- **Resetar MFA:** apenas admin com permissão, step-up MFA, notificar usuário, auditar.
- **Offboarding:** remover memberships, revogar sessões, transferir ownership, anonimizar após retenção.
- **Troca de tenant:** seletor de tenant; revalidar membership; nunca confiar no cookie sozinho.

### 7.4 Guardrails de UI

- Não permitir remover o último `tenant_owner`.
- Não permitir autoelevação ou autoexclusão.
- Exigir confirmação + motivo em mudanças sensíveis.
- Exigir step-up MFA para: alterar papéis, exportar dados, excluir, resetar MFA.
- Mostrar “permissões efetivas” antes de salvar.
- Notificar o usuário afetado por e-mail.

---

## 8. Enforcement em profundidade

| Camada | O que faz | Regra |
|---|---|---|
| **UI** | Esconde menus/botões, route guards | Conveniência, não segurança |
| **API/BFF** | `requireAuth`, `requireTenant`, `requirePermission`, `requireScope` | Toda request valida |
| **Banco/RLS** | Filtra por tenant, permissão e escopo | Última linha |
| **Storage** | Buckets privados, path por tenant, URL assinada | Checar permissão antes |
| **Edge Functions** | `verify_jwt=true`, validar tenant/permissão | Service role só no servidor |
| **Jobs** | Service role com escopo, idempotência, auditoria | Nunca varrer todos os tenants sem controle |
| **Auditoria** | Registra ações sensíveis | Imutável, com retenção |

### 8.1 API — exemplo

```ts
const ctx = await requireAuth(req);
await requireTenant(ctx, tenantId);
await requirePermission(ctx, "operacao.cirurgia.approve");
await requireScope(ctx, "operacao.cirurgia.approve", resource);
```

Respostas:

- `401` sem sessão.
- `403` sem permissão/escopo.
- `404` para recurso de outro tenant (não revelar existência).

### 8.2 Storage

- Bucket privado.
- Path `tenant_id/resource_type/resource_id/...`.
- URL assinada curta após checar permissão.
- Nunca servir arquivo por path público.
- Auditoria de download para dados sensíveis.

### 8.3 Edge Functions

- `verify_jwt=true` por padrão.
- Validar `tenant_id` contra membership.
- Validar permissão no banco.
- Rate limit por usuário/IP.
- Service role apenas para operações internas; nunca aceitar `tenant_id` do body sem validar.

---

## 9. Segurança de autenticação e sessão

| Controle | Recomendação |
|---|---|
| Cadastro staff | **Somente por convite**; signup público desabilitado |
| Cadastro paciente | Convite/OTP/magic link; auth separada do staff |
| Senha | Mínimo 12 caracteres; breached-password protection; rate limit |
| MFA | Obrigatório para platform admin, tenant owner/admin, financeiro; recomendado para todos |
| Sessão | Access token curto; refresh rotation; idle 30 min admin; absoluto 12h |
| Revogação | Suspender/role change força logout |
| Dispositivos | Lista de sessões, revogar individual/todas |
| Step-up | Ações sensíveis exigem MFA recente |
| IP allowlist | Platform admin/SSO enterprise |
| SSO/SAML | Fase 2/3 para clientes enterprise |
| Break-glass | Acesso temporário, motivo, aprovação, auditoria |
| Rate limit | Login, convite, reset, MFA, API |
| CAPTCHA | Login/cadastro/recuperação |
| Logs | Sem senha/token; mascarar PII |

---

## 10. Auditoria

Registrar no mínimo:

- Login/logout, falha de login, MFA.
- Convite, aceite, reenvio, cancelamento.
- Criação/edição/suspensão de usuário.
- Mudança de papel/permissão/escopo.
- Acesso/exportação de dados sensíveis.
- Reset de MFA, revogação de sessão.
- Alteração de módulo/configuração.
- Break-glass.

Campos: `tenant_id`, `actor_id`, `action`, `resource_type`, `resource_id`, `before`, `after`, `ip`, `user_agent`, `result`, `created_at`.

- Imutável (append-only).
- Retenção conforme LGPD/contrato.
- Exportação restrita e auditada.
- Alertas para mudanças críticas.

---

## 11. Testes e critérios de aceite

### Matriz de testes obrigatórios

- `anon` não acessa nenhuma tabela sensível.
- Usuário do tenant A não acessa tenant B (select/insert/update/delete).
- Usuário suspenso não acessa nada.
- Usuário sem permissão recebe 403.
- Escopo `own` não acessa registro de outro.
- Escopo `unit` não acessa outra unidade.
- `resource_acl` expirada não concede acesso.
- Troca de papel reflete imediatamente (cache invalidado).
- Último owner não pode ser removido.
- Auto-elevação bloqueada.
- Convite expirado/reutilizado falha.
- Reset de MFA exige step-up e audita.
- Exportação exige permissão e audita.
- Edge Function sem JWT retorna 401.
- Bucket privado não permite URL pública.

### Critérios de aceite

- 100% das rotas administrativas com `requirePermission`.
- 100% das tabelas de domínio com RLS.
- 0 acesso cruzado entre tenants nos testes.
- 0 permissão sem escopo definido.
- Auditoria cobrindo 100% das ações críticas.
- MFA obrigatório para papéis administrativos.
- Testes de RLS no CI.

---

## 12. Migração do estado atual

1. Inventariar `perfis`, `meu_papel()`, papéis e usuários existentes (51 no `srby…`, 7 no `xgyv…`).
2. Mapear papéis atuais para o catálogo novo.
3. Criar tabelas novas em paralelo; backfill de memberships/roles.
4. Dual-read: app antiga e nova leem o mesmo modelo.
5. Convidar/resetar usuários; não migrar senhas de painel front.
6. Testar por tenant; cutover com feature flag.
7. Rollback: manter modelo antigo por 30 dias.
8. Pinhabel dedicated: mesmo modelo, projeto separado.

---

## 13. Roadmap de implementação

| Fase | Prazo | Entregáveis |
|---|---|---|
| 1. Fundação | Semana 1–2 | Tabelas, permissões, papéis de sistema, RLS, funções auxiliares, seeds |
| 2. Admin básico | Semana 3–4 | Lista/convite/edição/suspensão, papéis, auditoria, sessões |
| 3. Enforcement | Semana 5–6 | Middleware/API guards, module gates, storage, Edge Functions, testes RLS |
| 4. Segurança avançada | Semana 7–8 | MFA, step-up, revogação, rate limit, IP allowlist, break-glass |
| 5. Customização | Semana 9–10 | Papéis custom por tenant, escopos unit/own/assigned, resource ACL |
| 6. Migração | Semana 11–12 | Backfill, dual-run, cutover, testes de penetração |

---

## 14. Decisões pendentes

1. Staff pode pertencer a **mais de um tenant**? Recomendação: sim, via memberships.
2. Existem **unidades/filiais**? Recomendação: suportar desde o início.
3. Clientes podem criar **papéis customizados**? Recomendação: sim, clonando papéis de sistema, com limite.
4. Paciente usa o **mesmo auth** ou projeto separado? Recomendação: mesmo Supabase inicialmente, app separado e RLS estrita; separar se contrato exigir.
5. MFA obrigatório para **todos** os staff ou só admins? Recomendação: todos os staff; admins obrigatório.
6. SSO/SAML agora? Recomendação: fase 2/3, após o core.
7. Aprovação de acesso sensível (four-eyes)? Recomendação: fase 4, para exportação e break-glass.
8. Retenção de auditoria? Definir com jurídico/LGPD.

---

## 15. O que não fazer

- Usar apenas “nível 1/2/3” como autorização.
- Confiar em menus escondidos.
- Colocar `service_role` no client.
- Criar um papel novo no código para cada cliente.
- Deixar permissões sem escopo.
- Permitir auto-elevação.
- Compartilhar login entre pessoas.
- Guardar senha de painel no JavaScript.
- Exportar dados sem auditoria.
- Ignorar RLS porque “a API já valida”.

---

**Resumo:** o painel de usuários é a interface; o valor real está no modelo **tenant → membership → role → permission → scope → module**, aplicado em UI, API, banco, storage e Edge Functions, com auditoria e testes. É isso que permite cada cliente ter especificidades sem perder controle.
