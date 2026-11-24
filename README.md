# Plataforma MedSales (nome provisório: restruturacao)

SaaS modular multi-tenant: um código, um deploy, um MySQL. Cada cliente é um tenant com subdomínio.
Status e prazos ficam no Task64; aqui fica o conhecimento.

## Rodar localmente

Requisitos: PHP 8.3+, Composer, Node 22+.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder
npm ci --ignore-scripts
npm run build
php artisan serve
```

Abra `http://pinhabel.localhost:8000` ou `http://mychelleamaral.localhost:8000`. `localhost` puro devolve 404: não existe tenant padrão.
Usuários sintéticos: `owner@`, `recepcao@` e `medico@` em `pinhabel.example` e `mychelleamaral.example`. A senha está em `DemoSeeder::PASSWORD`.

Plataforma: `http://admin.localhost:8000`, com `admin@medsales.example`.

## Rodar com Docker (app + MySQL)

```bash
docker compose up -d --build
```

- Plataforma: `http://admin.localhost:8090` (`admin@medsales.example`)
- Clientes: `http://pinhabel.localhost:8090` (`owner@pinhabel.example`) e `http://mychelleamaral.localhost:8090` (`owner@mychelleamaral.example`)
- MySQL: `127.0.0.1:3390`. Testes no MySQL: banco `medsales_test`, variáveis `DB_*` no ambiente.
- E-mails vão para o log: `docker compose logs app`.
- `docker compose stop` para; `docker compose down -v` apaga os dados locais.

## Verificar

```bash
composer check
npm run build
```

## Onde está cada coisa

- `app/Core`: tenancy, RBAC, módulos, auditoria, autenticação, funcionários e perfis.
- `app/Modules/<Nome>`: módulos de negócio; `module.php` declara permissões e funcionalidades.
- `docs/`: arquitetura, ADRs e segurança.
