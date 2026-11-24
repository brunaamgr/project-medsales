# DNS — medsalesbr.com → ValueHost

**Data:** 23/11/2026  
**Objetivo:** usar `mychelleamaral.medsalesbr.com` e futuros subdomínios de clientes na ValueHost, sem quebrar o site atual (Squarespace) nem o e-mail (Google Workspace).

> **Confirmação ValueHost (23/11/2026):** é possível adicionar `medsalesbr.com` como domínio adicional ou principal **mantendo os nameservers no Squarespace/Google**, e a criação de subdomínios funciona normalmente com DNS externo. Não é necessário mover os nameservers.  
> **Atenção:** confirmar o **IP do plano novo** com a ValueHost antes de criar os registros DNS.

---

## 1. Situação atual do DNS

### Site principal (Squarespace)

| Tipo | Nome | Dados |
|---|---|---|
| A | `@` | `198.185.159.144` |
| A | `@` | `198.185.159.145` |
| A | `@` | `198.49.23.144` |
| A | `@` | `198.49.23.145` |
| CNAME | `www` | `ext-sq.squarespace.com` |
| HTTPS | `@` | ALPN/ipv4hint Squarespace |
| CNAME | `_domainconnect` | `_domainconnect.domains.squarespace.com` |

### E-mail (Google Workspace)

| Tipo | Nome | Dados |
|---|---|---|
| MX | `@` | `smtp.google.com` (prioridade 1) |
| TXT | `@` | `v=spf1 include:_spf.google.com ~all` |
| TXT | `google._domainkey` | DKIM do Google |

> **Não mexer** nesses registros. O site e o e-mail dependem deles.

### Subdomínios atuais

| Tipo | Nome | Destino |
|---|---|---|
| A | `centralclientes` | `76.76.21.21` (Vercel) |
| A | `estudobiblico` | `76.76.21.21` |
| A | `financeiro` | `76.76.21.21` |
| A | `gestaopinhabel` | `76.76.21.21` |
| A | `operacaopinhabel` | `76.76.21.21` |
| A | `socialmedia` | `76.76.21.21` |
| A | `teleprompter` | `76.76.21.21` |
| A | `videos` | `76.76.21.21` |
| CNAME | `gestaodramychelle` | `cname.vercel-dns.com` |
| CNAME | `gestaoferraz` | `cname.vercel-dns.com` |
| CNAME | `gestarefluir` | `cname.vercel-dns.com` |
| CNAME | `linhaseditoriais` | `cname.vercel-dns.com` |
| CNAME | `manualpatriciajunqueira` | `cname.vercel-dns.com` |

### O que não existe

- `mychelleamaral.medsalesbr.com`
- `teste.medsalesbr.com`
- `*.medsalesbr.com` (wildcard)
- Nenhum registro apontando para a ValueHost (IP do novo plano a confirmar)

---

## 2. Decisão recomendada

**Não mover os nameservers.** Manter o site no Squarespace e o e-mail no Google Workspace.

Adicionar apenas registros específicos para os subdomínios da ValueHost:

| Tipo | Nome | TTL | Dados |
|---|---|---|---|
| A | `mychelleamaral` | 30 min | `<IP_DO_NOVO_PLANO>` |
| A | `teste` | 30 min | `<IP_DO_NOVO_PLANO>` (para validar) |
| A | `app` | 30 min | `<IP_DO_NOVO_PLANO>` (futuro painel central) |

Opcional para escala:

| Tipo | Nome | TTL | Dados |
|---|---|---|---|
| A | `*` | 30 min | `<IP_DO_NOVO_PLANO>` |

> **Cuidado com `*`:** o wildcard manda qualquer subdomínio não explícito para a ValueHost. Os registros explícitos (`www`, `centralclientes`, etc.) continuam vencendo. Para o MVP, prefira criar subdomínio explícito por cliente e só depois avaliar wildcard.

**Não alterar:**

- A `@` (Squarespace)
- CNAME `www`
- MX, SPF, DKIM (Google)
- HTTPS `@`
- `_domainconnect`

---

## 3. O que fazer no cPanel/ValueHost

1. Comprar/ativar o plano e anotar o **IP do servidor** e os nameservers.
2. Adicionar `medsalesbr.com` na conta ValueHost como **addon domain** ou **primary domain** (confirmado: funciona com DNS externo).
3. Criar o subdomínio `teste` no cPanel, com document root de teste.
4. Adicionar no Squarespace o registro A `teste` → `<IP_DO_NOVO_PLANO>`.
5. Aguardar a propagação (TTL 30 min).
6. Rodar **AutoSSL** para `teste.medsalesbr.com`.
7. Testar `https://teste.medsalesbr.com`.
8. Se funcionar, repetir para `mychelleamaral`:
   - criar subdomínio no cPanel;
   - document root para `/home/usuario/medsales/public` (Laravel);
   - A record `mychelleamaral` → `<IP_DO_NOVO_PLANO>`;
   - AutoSSL;
   - testar `https://mychelleamaral.medsalesbr.com`.

> **Ponto crítico:** confirmar o **IP do plano novo** com a ValueHost. Não usar IP de outra conta. Se o AutoSSL falhar, o plano B é Cloudflare/Origin Certificate ou instalação manual de certificado.

---

## 4. SSL

### Por subdomínio (recomendado no MVP)

- `mychelleamaral.medsalesbr.com` recebe um certificado próprio via AutoSSL.
- Funciona com DNS externo, desde que o subdomínio aponte para o servidor.
- Cada novo cliente = criar subdomínio + AutoSSL.

### Wildcard

- Requer `*.medsalesbr.com` e certificado `*.medsalesbr.com`.
- Let's Encrypt wildcard exige DNS-01; AutoSSL padrão do cPanel geralmente não faz.
- Wildcard SSL **não foi confirmado** para este plano; perguntar à ValueHost como seria emitido.
- Alternativas: certificado wildcard manual, Cloudflare + Origin Certificate, ou continuar por subdomínio.

**Para o MVP:** SSL por subdomínio é suficiente. Wildcard é otimização.

---

## 5. Perguntas para a ValueHost antes de comprar

```text
Tenho o domínio medsalesbr.com com DNS no Squarespace/Google Workspace
(site no Squarespace e e-mail no Google) e quero hospedar um Laravel
com subdomínios por cliente na ValueHost.

1. Consigo adicionar medsalesbr.com como addon domain ou primary domain
   mantendo os nameservers no Squarespace/Google?
2. O cPanel cria subdomínios normalmente com DNS externo?
3. Consigo rodar AutoSSL para mychelleamaral.medsalesbr.com com DNS externo?
4. O plano permite wildcard *.medsalesbr.com e SSL wildcard? Como é emitido?
5. Qual o limite de subdomínios/addon domains?
6. Consigo criar subdomínio via cPanel API para automatizar o onboarding?
7. O plano tem PHP 8.3, SSH, Composer, cron e backup diário?
8. Quais são os nameservers e o IP do servidor?
```

---

## 6. Validação

Depois de criar o registro A e o subdomínio:

```bash
nslookup mychelleamaral.medsalesbr.com
curl -I https://mychelleamaral.medsalesbr.com
```

No SSH, para ver o certificado:

```bash
openssl s_client -connect mychelleamaral.medsalesbr.com:443 \
  -servername mychelleamaral.medsalesbr.com </dev/null 2>/dev/null \
  | openssl x509 -noout -subject -ext subjectAltName
```

**Esperado:**

- `nslookup` → `<IP_DO_NOVO_PLANO>`
- `curl -I` → `200` ou `302` com HTTPS válido
- certificado com `DNS:mychelleamaral.medsalesbr.com` (ou `*.medsalesbr.com`, se wildcard)

---

## 7. Resumo executivo

- **Dá para criar subdomínios** sem mexer no site nem no e-mail.
- **Não mova os nameservers** agora; adicione registros A específicos.
- **O bloqueio é o cPanel aceitar `medsalesbr.com` com DNS externo.**
- **SSL por subdomínio resolve o MVP**; wildcard é otimização.
- **Confirme com a ValueHost antes de comprar**; guarde a resposta por escrito.
