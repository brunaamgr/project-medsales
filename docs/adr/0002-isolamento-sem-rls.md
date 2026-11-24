# ADR 0002: banco único e isolamento na aplicação

> **Cards:** F05, F06 · 24/11/2026 · Aceito

**Decisão.** Um MySQL, `tenant_id` em todo domínio e falha fechada sem contexto. O tenant sai do host. Não há tenant padrão.

- FKs compostas impedem vincular unidade ou membership de outro tenant. FKs para `tenants` são RESTRICT.
- `upsert` é proibido em models de tenant: a chave duplicada pode ser de outro tenant.
- `roles.tenant_id` nulo indica papel de sistema. A coluna gerada `tenant_key` evita duplicidade, já que UNIQUE aceita NULLs repetidos.
- Sessão presa ao tenant e cookie só do host: `SESSION_DOMAIN` nulo, `__Host-` em produção. `.medsalesbr.com` também serve Squarespace e Vercel.
- Tabelas globais permitidas estão listadas em `tests/Feature/SchemaTest.php`.

**Adiado.** Fallback `/t/{tenant}` em staging (previsto no F06): usar subdomínio também em staging é mais simples e testa o caminho real.
