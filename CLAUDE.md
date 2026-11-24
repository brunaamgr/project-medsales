# Regras do projeto

- Leia `docs/arquitetura.md` e os ADRs em `docs/adr/` antes de mudar o core.
- Antes de cada card: objetivo, arquivos, impacto em tenant/RBAC, testes, aceite, riscos. Espere o aceite.
- Todo model de domínio usa `BelongsToTenant`. Toda tabela de domínio tem `tenant_id` obrigatório com FK.
- `tenant_id` vem do `TenantContext`, nunca do request.
- Proibido: `withoutGlobalScope(s)`, `DB::table()` e SQL direto fora das exceções do `phpstan.neon`; `if (cliente === ...)`.
- Toda rota de módulo usa `module:`, `feature:` e `permission:`; toda listagem com escopo usa `AccessResolver::restrict()`.
- Sem Redis, websocket ou worker longo. Sem Vercel/Supabase.
- Pacote novo só com justificativa e alternativa first-party.
- Sem segredos e sem dados reais de pacientes.
- Mudança de arquitetura exige ADR. Deploy em produção só com autorização explícita.
- Antes de entregar: `composer check` e `npm run build`.
