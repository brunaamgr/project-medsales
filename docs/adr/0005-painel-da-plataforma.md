# ADR 0005: painel da plataforma em host próprio

> **Cards:** F06b, A01 (parcial), A03 (parcial) · 24/11/2026 · Proposto (aguarda aceite da PO)

**Decisão.** O painel fica em `PLATFORM_HOST` (`admin.localhost`; produção `admin.medsalesbr.com`), no mesmo código e deploy.

- `platform_admins` é tabela global, fora do RBAC de tenant. Administrar a plataforma não dá login nem dados de cliente; isso exigirá break-glass.
- A sessão guarda o contexto (`platform` ou `tenant:ID`) e só vale nele.
- Rotas com `Route::domain` mais o middleware `platform` (duas barreiras).
- O papel de sistema `platform_admin` (F08) segue sem permissões e não atribuível.
- Primeiro acesso por `php artisan platform:admin e-mail`; a senha é digitada, nunca argumento.
- Convite: token de 64 caracteres, só o hash no banco, 7 dias, uso único. Quem já tem conta confirma a senha atual.

**Antes de produção.** MFA e step-up (A04), CAPTCHA e, se possível, restrição de IP.
