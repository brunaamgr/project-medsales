# Arquitetura

> **Cards:** F01, F05, F06, F07, F08, F09

## Caminho de um request

1. `ResolveTenant` (primeiro do grupo web): host → tenant ativo, ou 404.
2. Sessão e autenticação.
3. `EnsureActiveMembership`: sessão criada neste tenant, vínculo ativo e limite absoluto; senão encerra a sessão.
4. `module:` e `feature:` → 404 se não contratado.
5. `permission:` → 403 e auditoria se o papel não permite.
6. Dentro do controller: `$user->can('modulo.recurso.acao', $model)` e `AccessResolver::restrict()`.

## Camadas de isolamento

| Camada | Onde |
|---|---|
| Escopo automático, falha sem contexto | `TenantScope` |
| Escrita conferida em create/update/delete | `BelongsToTenant` |
| Atalhos do Eloquent bloqueados em runtime | `TenantBuilder` |
| FKs compostas `(tenant_id, id)` | migrations |
| Regra de análise estática | `phpstan/Rules/ForbidTenantBypassRule.php` |
| Testes com dois tenants e teste de schema | `tests/Feature` |

Limite conhecido: sem RLS, SQL cru escrito nas exceções do `phpstan.neon` não é filtrado. Toda exceção nova exige revisão.

## Criar um módulo

1. `app/Modules/<Nome>/module.php` com slug, dependências, funcionalidades e permissões (`slug.recurso.acao`).
2. `Routes/web.php`, `Views/`, `Database/Migrations/` são carregados sozinhos.
3. Models em `Models/` com `BelongsToTenant`; se usar escopos unit/own, implemente `ScopedResource`.
4. Ajuste `config/roles.php` e rode `php artisan access:sync`.

## Filas

O tenant vai no payload. Job de tenant suspenso falha. Despache dentro do `run()` com closure de bloco: devolver o
`PendingDispatch` de dentro do `run()` faz o job ser despachado depois, já sem o contexto.
