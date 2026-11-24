# Checklist ValueHost — Go/No-Go para Laravel

**Objetivo:** confirmar, antes de começar o sistema, se a ValueHost suporta Laravel 11/12, MySQL, cron, subdomínios e SSL.  
**Por que isso importa:** hospedagem compartilhada às vezes bloqueia Composer, SSH, cron ou extensões PHP. Se bloquear, o projeto começa com risco alto.

> **Atualização 23/11/2026:** a ValueHost confirmou por escrito que **é possível adicionar `medsalesbr.com` como domínio adicional ou principal mantendo os nameservers no Squarespace/Google**, e que **a criação de subdomínios funciona normalmente** com DNS externo. O Go/No-Go de infraestrutura está **aprovado**; falta apenas testar o SSL do primeiro subdomínio.
>
> **Atenção:** confirmar o **IP do plano novo** antes de criar os registros DNS. Não há confirmação de SSL wildcard para este projeto/plano; o MVP usará SSL por subdomínio.

### Status rápido

| Item | Status |
|---|---|
| PHP 8.3 | ✅ Confirmado |
| Laravel rodando | ✅ Confirmado |
| SSH + cPanel | ✅ Confirmado |
| Backup diário | ✅ Confirmado (testar restore) |
| Criação de subdomínio no cPanel | ✅ Confirmado |
| Addon/primary domain com DNS externo | ✅ **Confirmado por escrito pela ValueHost** |
| SSL wildcard no plano novo | ⚠️ **Não confirmado** |
| `medsalesbr.com` apontando para a ValueHost | ⚠️ Configurar A record após comprar o plano |
| IP exato do plano novo | ⚠️ **Confirmar com a ValueHost** |
| Composer/extensões | ⚠️ Confirmar |
| Cron | ⚠️ Confirmar |
| AutoSSL no subdomínio | ⚠️ **Testar no primeiro subdomínio** |

### Evidências deste projeto

- `medsalesbr.com` está no Squarespace/Google Cloud DNS (198.185.159.x), **não** na ValueHost.
- `mychelleamaral.medsalesbr.com`, `teste.medsalesbr.com` e `*.medsalesbr.com` não existem no DNS.
- A ValueHost confirmou addon/primary domain com DNS externo e criação de subdomínios.
- SSL wildcard **não** foi confirmado para este plano; será testado SSL por subdomínio.

**Conclusão:** o caminho do MVP é **A record específico + subdomínio no cPanel + AutoSSL**. Wildcard fica como otimização futura, se a ValueHost confirmar.

**Análise completa dos registros DNS e plano de mudança:** `DNS-MEDSALESBR.md`.

### Antes de comprar o plano: perguntas obrigatórias

Envie por escrito ao pré-venda/suporte e guarde a resposta:

1. Consigo adicionar o domínio existente `medsalesbr.com` como **addon domain** neste plano?
2. Qual o limite de addon domains e subdomínios?
3. O plano permite **wildcard subdomain** (`*.medsalesbr.com`)?
4. O plano emite **SSL wildcard** para `*.medsalesbr.com`? Como (AutoSSL, Let's Encrypt DNS-01, certificado manual)?
5. Posso manter o DNS atual no Squarespace/Google e apenas criar registros `A` para os subdomínios, ou preciso mover os nameservers para a ValueHost?
6. Se eu mover os nameservers, como fica o site atual da `medsalesbr.com` (Squarespace)?
7. O plano novo tem **PHP 8.3, SSH, Composer, cron e backup diário**?
8. Consigo criar subdomínio via **cPanel API** para automatizar o onboarding?
9. Qual o IP do servidor e os nameservers do plano novo?

> **A confirmação recebida cobre addon/primary domain + criação de subdomínios.** Ainda falta confirmar, no plano novo, o **IP**, o **AutoSSL por subdomínio** e o **wildcard SSL** (se necessário).

### Cenários de DNS

| Cenário | Como fica | Prós | Contras |
|---|---|---|---|
| **A. Mover nameservers para ValueHost** | `medsalesbr.com` passa a usar `ns1/ns2.valueserver.com.br` | cPanel controla DNS, subdomínios e SSL | Pode quebrar o site atual no Squarespace se os registros não forem recriados |
| **B. Manter DNS no Squarespace/Google** | Criar `A` para `mychelleamaral` e `*.medsalesbr.com` → `<IP_DO_NOVO_PLANO>` | Não mexe no site principal | Wildcard SSL pode exigir certificado manual/Cloudflare; addon domain precisa ser aceito |
| **C. Cloudflare na frente** | DNS wildcard na Cloudflare + SSL wildcard no edge + Origin Certificate | Melhor para muitos subdomínios; mantém site atual | Adiciona Cloudflare e configuração extra |

**Recomendação:** começar com **B** para o primeiro tenant (Mychelle) se o site atual no Squarespace precisar ficar no ar; avaliar **A** ou **C** para escala. O onboarding do primeiro cliente pode ser manual; automatizamos depois.

---

## 1. O que precisamos confirmar

### Checagem rápida (já existe Laravel)

Se você tem SSH:

```bash
php -v
composer --version
php -m
php artisan --version
mysql --version
```

Se não tem SSH, confira no cPanel:

- **MultiPHP Manager** → versão do PHP.
- **Select PHP Version/Extensions** → extensões.
- **Cron Jobs** → frequência disponível.
- **SSL/TLS Status** → certificados.
- **Domains/Subdomains** → wildcard.
- **phpMyAdmin** → versão do MySQL/MariaDB.
- **Backup** → política do plano.

### PHP

- [ ] PHP **8.2 ou 8.3** selecionável no cPanel.
- [ ] Extensões habilitadas:
  - `pdo_mysql`
  - `mbstring`
  - `openssl`
  - `json`
  - `gd`
  - `zip`
  - `intl`
  - `bcmath`
  - `fileinfo`
  - `curl`
- [ ] `memory_limit` >= 256 MB.
- [ ] `upload_max_filesize` e `post_max_size` adequados (exames/fotos/documentos).
- [ ] `max_execution_time` >= 60s (ou possibilidade de ajustar).
- [ ] `opcache` habilitado.

### Composer e deploy

- [ ] Composer disponível via SSH.
- [ ] SSH habilitado para o plano.
- [ ] Git/rsync ou FTP para deploy.
- [ ] Possibilidade de rodar `php artisan` via SSH/cron.
- [ ] Se não houver SSH: possibilidade de subir `vendor/` compilado e rodar migrations via cron/rota protegida.

### Banco

- [ ] MySQL 8.0 ou MariaDB 10.6+.
- [ ] `utf8mb4` e collation adequada.
- [ ] Suporte a JSON.
- [ ] InnoDB.
- [ ] Usuário de banco com permissões apenas no banco da aplicação (sem root, sem `FILE`).
- [ ] Limite de conexões e tamanho do banco adequados.
- [ ] Backup do banco disponível.

### Cron e filas

- [ ] Cron jobs disponíveis.
- [ ] Frequência mínima de 1x/minuto (ou 5x/minuto).
- [ ] Possibilidade de rodar `php artisan schedule:run` e `queue:work --stop-when-empty`.
- [ ] Limite de tempo do cron compatível.

### Domínios e SSL

- [ ] Wildcard `*.medsalesbr.com` apontando para o servidor.
- [ ] SSL wildcard `*.medsalesbr.com` (ou emissão automática por subdomínio).
- [ ] Possibilidade de adicionar domínios customizados (ex.: `acompanhamentos.mychelleamaral.com.br`).
- [ ] Renovação automática de SSL.
- [ ] Redirecionamento HTTP → HTTPS.
- [ ] Acesso ao DNS ou instruções para CNAME/TXT.

> **Ponto crítico:** se a ValueHost não tiver SSL wildcard, será preciso emitir SSL por subdomínio ou usar Cloudflare na frente. Confirmar antes.

### Arquivos e e-mail

- [ ] Document root apontando para `public/`.
- [ ] Possibilidade de manter `.env` e `storage/` fora do `public_html`.
- [ ] Espaço em disco e limite de inodes.
- [ ] Backup de arquivos.
- [ ] SMTP com SPF/DKIM/DMARC.
- [ ] Saída HTTPS para APIs externas (Anthropic, Kommo, Meta, ZapSign, Resend).

### Segurança e logs

- [ ] `open_basedir` configurável.
- [ ] `display_errors=Off` em produção.
- [ ] `expose_php=Off`.
- [ ] Funções perigosas desabilitáveis (`exec`, `shell_exec`, `system`, `proc_open` etc.).
- [ ] Logs de erro acessíveis.
- [ ] WAF/Cloudflare ou proteção equivalente.
- [ ] Backups automáticos da hospedagem.

---

## 2. Mensagem para enviar ao suporte da ValueHost

Copie e envie:

```text
Olá, preciso confirmar se meu plano atende a um sistema Laravel 11/12 em produção.

1. PHP: qual versão está disponível? Preciso de PHP 8.2 ou 8.3 com as extensões pdo_mysql, mbstring, openssl, json, gd, zip, intl, bcmath, fileinfo e curl. É possível habilitar?
2. Composer e SSH: o plano tem SSH e Composer? Consigo rodar `composer install` e `php artisan migrate`?
3. Banco: qual versão do MySQL/MariaDB? Suporta utf8mb4, JSON e InnoDB? Qual o limite de tamanho/conexões?
4. Cron: consigo agendar `php artisan schedule:run` a cada minuto? Qual o limite?
5. Domínios: consigo usar wildcard *.medsalesbr.com e SSL wildcard? Se não, consigo emitir SSL por subdomínio automaticamente?
6. Domínio customizado: consigo apontar dominios de clientes (ex.: acompanhamentos.mychelleamaral.com.br) para o mesmo sistema?
7. Document root: consigo apontar para a pasta public/ e manter .env/storage fora do public_html?
8. Backup: qual a política de backup do banco e dos arquivos? Consigo fazer dump próprio?
9. SMTP: posso usar SMTP do plano? SPF/DKIM/DMARC estão disponíveis?
10. Limites: memória, upload, tempo de execução, disco e inodes.
11. Segurança: consigo desabilitar funções perigosas, usar open_basedir e acessar logs de erro?
12. Saída HTTPS: o servidor permite chamadas externas para APIs (Anthropic, Kommo, Meta, ZapSign, Resend)?
```

---

## 3. Como testar no cPanel

- **MultiPHP Manager:** selecionar PHP 8.2/8.3.
- **Select PHP Version/Extensions:** conferir extensões.
- **MultiPHP INI Editor:** conferir `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`.
- **Cron Jobs:** criar um teste `php -v` a cada minuto.
- **Domains/Subdomains:** criar um subdomínio de teste `teste.medsalesbr.com`.
- **SSL/TLS:** verificar se o subdomínio recebe certificado automaticamente.
- **phpMyAdmin:** confirmar versão do MySQL/MariaDB e collation.
- **File Manager:** confirmar permissão de escrita em `storage/` e `bootstrap/cache/`.

---

## 3.1 Teste de SSL por subdomínio (o ponto crítico)

### Teste A — subdomínio explícito

> **Pré-requisito:** `medsalesbr.com` precisa estar na mesma conta cPanel ou com DNS apontando para este servidor. Se ele estiver em outra conta, adicione-o como domínio adicional ou crie o subdomínio na conta correta.

1. No cPanel, crie `teste.medsalesbr.com`.
2. Aponte para uma pasta de teste (ex.: `public_html/teste`).
3. Coloque um `index.html` simples.
4. Acesse `http://teste.medsalesbr.com`.
5. No cPanel, abra **SSL/TLS Status** e rode **AutoSSL** para esse domínio.
6. Acesse `https://teste.medsalesbr.com` e veja se o cadeado é válido.

Se funcionar, o onboarding pode criar subdomínio + AutoSSL por cliente via cPanel API ou passo manual.

### Teste B — wildcard

1. Crie um subdomínio que **não** foi adicionado explicitamente, por exemplo `teste2.medsalesbr.com`.
2. Tente acessar `https://teste2.medsalesbr.com`.
3. No SSH, verifique o certificado:

```bash
curl -I https://teste2.medsalesbr.com
openssl s_client -connect teste2.medsalesbr.com:443 -servername teste2.medsalesbr.com </dev/null 2>/dev/null | openssl x509 -noout -subject -ext subjectAltName
```

**Interpretação:**

- Se `teste2` abrir com HTTPS válido e o certificado tiver `*.medsalesbr.com` → wildcard OK.
- Se `teste2` abrir sem HTTPS válido ou com erro de certificado → não há wildcard SSL.
- Se `teste2` nem resolver → não há DNS wildcard.

> **Atenção:** o AutoSSL padrão do cPanel geralmente emite certificado **por subdomínio**, não wildcard (Let's Encrypt wildcard exige DNS-01). Se não houver wildcard, os caminhos são:
>
> 1. Criar subdomínio + AutoSSL por cliente no onboarding (manual ou cPanel API).
> 2. Usar Cloudflare na frente com SSL wildcard + Origin Certificate.
> 3. Instalar um certificado wildcard manualmente no cPanel, se o plano permitir.

### O que preciso saber

- O Teste A funcionou?
- O Teste B funcionou?
- O cPanel permite criar subdomínio via API (token) ou só manual?

Se você criar `teste.medsalesbr.com`, eu consigo verificar externamente os headers e o certificado por aqui.

## 4. Go/No-Go

**Go de infraestrutura (confirmado):** PHP 8.3, Laravel rodando, SSH + cPanel, backup diário, addon/primary domain com DNS externo e criação de subdomínios.

**Condição para o primeiro deploy:** confirmar o **IP do plano novo** e testar **AutoSSL** em `teste.medsalesbr.com`.

**No-Go:** falha em AutoSSL no subdomínio, Composer/extensões, cron ou restore.

**Decisão:** pode comprar o plano e iniciar o Sprint 0; o teste de SSL roda no primeiro subdomínio de homologação.

**Plano B:** VPS gerenciada com PHP-FPM + MySQL + Redis, ou hospedagem PHP gerenciada que atenda.

**Não iniciar o desenvolvimento** sem esse gate. Migrar depois custa mais caro do que validar agora.

---

## 5. Checklist de subdomínio do cliente

No cadastro de um novo cliente, exigir:

- [ ] Nome do cliente.
- [ ] Subdomínio (ex.: `mychelleamaral`).
- [ ] Prévia: `https://mychelleamaral.medsalesbr.com`.
- [ ] Disponibilidade do subdomínio.
- [ ] E-mail do admin inicial.
- [ ] Módulos habilitados.
- [ ] Funcionalidades habilitadas.
- [ ] Branding (logo/cores) — pode ser depois.
- [ ] Domínio customizado — opcional.

**Regras do subdomínio:**

- 3–63 caracteres.
- Letras minúsculas, números e hífen.
- Não começar/terminar com hífen.
- Não usar pontos, underscores ou espaços.
- Não usar palavras reservadas: `www`, `app`, `admin`, `api`, `mail`, `smtp`, `ftp`, `staging`, `dev`, `test`, `status`, `suporte`, `help`, `cdn`, `static`, `assets`, `login`, `sso`, `auth`, `painel`, `sistema`, `medsales`.
- Único globalmente.
- Normalizar para minúsculo e remover acentos.
- Bloquear IDN/homograph no início.

**Exemplo:**

- Cliente: Mychelle Amaral
- Subdomínio: `mychelleamaral`
- URL: `https://mychelleamaral.medsalesbr.com`
- Domínio antigo: `https://acompanhamentos.mychelleamaral.com.br` (custom domain/redirect)
