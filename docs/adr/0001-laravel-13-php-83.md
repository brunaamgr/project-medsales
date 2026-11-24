# ADR 0001: Laravel 13, PHP 8.3 e Pest 4

> **Card:** F01 · 24/11/2026 · Aceito

**Contexto.** Laravel 12 só recebe correções de segurança, até 24/02/2027. Laravel 13 exige PHP 8.3+ e tem segurança até 17/03/2028.

**Decisão.** Laravel 13, PHP 8.3, Pest 4 (o 5 exige PHP 8.4).

**Pacotes.** Fortify (first-party, autenticação). Pest e pest-plugin-laravel (alternativa first-party: PHPUnit). Larastan (sem first-party). `@alpinejs/csp` no lugar de `alpinejs`: permite CSP sem `unsafe-eval`.

**Consequência.** PHP 8.3 perde segurança em 31/12/2027; planejar 8.4 com a ValueHost.
