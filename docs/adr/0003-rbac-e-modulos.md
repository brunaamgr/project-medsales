# ADR 0003: RBAC e módulos declarados em manifesto

> **Cards:** F01, F08 · 24/11/2026 · Aceito

**Decisão.** Permissões e funcionalidades nascem no `module.php`; papéis de sistema em `config/roles.php`; `access:sync` espelha no banco. Uma permissão só vale com papel, módulo e funcionalidade ativos.

**Diferenças em relação à especificação (Postgres/RLS).**

- `bi.export` virou `bi.relatorio.export`, para seguir `modulo.recurso.acao`.
- `portal.*` fica para o AC02; `patient` e `integration` nascem sem permissões.
- `core.module.manage` e `core.tenant.manage` não são dadas a papéis do tenant: quem contrata é a plataforma.
- `platform_admin` não é atribuível em tenant. O painel da plataforma é decisão em aberto.
