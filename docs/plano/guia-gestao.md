# Guia de Gestão — Task64 + GitHub + Documentação

**Objetivo:** saber onde registrar cada coisa, sem duplicar informação e sem perder histórico.

---

## 1. A regra de ouro: uma fonte de verdade por tipo

| Informação | Onde fica | Exemplo |
|---|---|---|
| O que fazer / status / prazo | **Task64** | Card `[D01] Mapear acessos`, coluna “Fazendo” |
| Estimativa e prazo | **Task64** | Label `⏱ 0,5h`, campo `Prazo` |
| Checklist de execução | **Task64** | Checkboxes nativos no card |
| Andamento / bloqueios / tempo real | **Comentário no card** | “ValueHost OK; Vercel pendente” |
| Código | **GitHub** | Branch, commit, PR |
| Decisão de arquitetura | **`docs/adr/`** | `0001-php-laravel-valuehost.md` |
| Especificação de módulo | **`docs/modulos/`** | `precificacao.md` |
| Infra / deploy / backup | **`docs/operacao/`** | `deploy-valuehost.md`, `backup-restore.md` |
| Segurança / LGPD | **`docs/seguranca/`** | `rbac.md`, `lgpd.md` |
| Histórico de versões | **`CHANGELOG.md`** | “v0.1.0 — fundação” |
| Evidência de conclusão | **Comentário do card + links** | commit, PR, deploy, print, teste |

> **Não duplique status na documentação.** O card é o painel de execução; a documentação é o conhecimento que fica.

---

## 2. Estrutura de documentação no projeto

```text
docs/
  plano/
    escopo.md
    cronograma.md
    estimativas.md
  adr/
    0001-php-laravel-valuehost.md
    0002-banco-unico-tenant.md
    0003-subdominio-por-cliente.md
  modulos/
    acompanhamento.md
    operacao.md
    precificacao.md
  infra/
    valuehost.md
    dns-subdominios.md
    ssl.md
  operacao/
    deploy.md
    backup-restore.md
    incidentes.md
  seguranca/
    rbac.md
    lgpd.md
    checklist-seguranca.md
CHANGELOG.md
README.md
```

Cada documento deve começar com o vínculo ao card:

```markdown
> **Card:** [D01] Mapear acessos
> **Última atualização:** 24/11/2026
```

No card, o comentário aponta para o documento:

```text
Documentação: docs/infra/acessos.md
```

---

## 3. Ciclo de vida de um card

```text
Backlog → Fazendo → Revisão/Homologação → Concluído
```

### Backlog

- Card com título, estimativa, prazo, etiquetas e checklist.
- Ainda não começou.

### Fazendo

- Mova o card para “Fazendo”.
- Comente a data de início.
- Marque os checkboxes conforme executa.
- Registre bloqueios no comentário.
- Use branch/commit com o ID do card.

### Revisão/Homologação

- Código implementado e testado.
- Comente com:
  - commit/PR;
  - URL de homologação;
  - evidência (print, teste, log);
  - o que falta validar.
- Cliente/usuário testa, se aplicável.

### Concluído

- Todos os checkboxes marcados.
- Critério de aceite atendido.
- Comentário final com evidência e tempo real.
- Documentação atualizada, se necessário.
- Card movido para “Concluído”.

---

## 4. Rotina diária e semanal

### Diária (10–15 min)

1. Abra a coluna **Fazendo**.
2. Veja o próximo card e o prazo.
3. Execute/marque os checkboxes.
4. Registre bloqueios no comentário.
5. Ao terminar, mova para **Revisão** ou **Concluído**.

### Semanal (30 min)

1. Revise cards atrasados.
2. Compare **estimativa × tempo real**.
3. Atualize riscos e dependências.
4. Ajuste prazos somente com justificativa.
5. Atualize o `CHANGELOG.md` se houve entrega.

---

## 5. Template de comentário no card

Copie e cole no comentário do card:

```text
## Execução

- Início:
- Estimativa:
- Tempo real:
- Branch/commit:
- PR:
- Deploy/homologação:
- Testes executados:
- Evidências:
- Pendências/bloqueios:
- Próximo passo:
```

Exemplo:

```text
## Execução

- Início: 24/11/2026 09:00
- Estimativa: 0,5h
- Tempo real: 0,7h
- Branch/commit: feat/D01-acessos / a1b2c3d
- PR: #12
- Deploy/homologação: https://teste.medsalesbr.com
- Testes: acesso ao cPanel e SSH OK
- Evidências: docs/infra/acessos.md
- Pendências: aguardando acesso Vercel
- Próximo passo: solicitar acesso ao time Vercel
```

---

## 6. Convenção de branch, commit e PR

Use o ID do card em tudo:

```text
Branch: feat/D01-mapear-acessos
Commit: feat(D01): mapeia acessos e documenta pendências
PR:     [D01] Mapear acessos e responsáveis
```

Isso permite rastrear:

- card → branch → commit → PR → deploy → evidência.

### Exemplo de fluxo

1. Card `[D01]` está em Backlog.
2. Você move para **Fazendo**.
3. Cria branch `feat/D01-mapear-acessos`.
4. Commits com `feat(D01): ...`.
5. Abre PR `[D01] ...`.
6. Comenta no card com link do PR.
7. Após merge/deploy, comenta evidência e move para **Concluído**.

---

## 7. Definition of Done (padrão)

Um card só está concluído quando:

- [ ] Todos os checkboxes do card estão marcados.
- [ ] Critério de aceite atendido.
- [ ] Código commitado e revisado.
- [ ] Testes executados.
- [ ] Deploy/homologação validado.
- [ ] Evidência registrada no comentário.
- [ ] Documentação atualizada, se necessário.
- [ ] Tempo real registrado.
- [ ] Sem segredo/PII em card, print ou doc.

---

## 8. Exemplo prático: D01 — Mapear acessos

### No Task64

- Estimativa: `0,5h`
- Prazo: `24/11/2026`
- Checklist:
  - ValueHost
  - Vercel
  - Supabase
  - GitHub
  - DNS
  - E-mail
  - Kommo/Meta/ZapSign/Resend/Anthropic
  - Responsável por aprovação

### Durante a execução

- Mova para **Fazendo**.
- Marque cada checkbox obtido.
- Se Vercel travar, comente:

```text
Vercel pendente: conta conectada não tem acesso ao time.
Preciso do login Google da conta dona ou de um token.
```

### Ao concluir

- Comente o template de execução.
- Link `docs/infra/acessos.md`.
- Mova para **Concluído**.

### Na documentação

`docs/infra/acessos.md`:

```markdown
# Acessos

> Card: [D01] Mapear acessos

| Serviço | Status | Responsável | Observação |
|---|---|---|---|
| ValueHost | OK | Ornilio | cPanel/SSH |
| Vercel | Pendente | ... | sem acesso ao time |
| Supabase | OK | ... | leitura |
```

---

## 9. O que não fazer

- Não manter uma pasta por tarefa.
- Não copiar o checklist inteiro para o Word/Notion.
- Não usar WhatsApp como fonte de decisão.
- Não misturar status de tarefa com documentação de arquitetura.
- Não colocar segredo, token, senha ou PII em card, print ou doc.
- Não mudar a estimativa original; registre o tempo real no comentário.
- Não concluir card sem evidência.

---

## 10. Como empresas profissionais fazem

Ferramentas mudam (Jira, Linear, GitHub Projects, Trello, Task64), mas o princípio é sempre:

1. **Issue tracker** para execução e prioridade.
2. **Repositório** para código, PR e CI.
3. **Docs-as-code** para decisões, arquitetura e runbooks.
4. **Comentários/PRs** para evidência e histórico.
5. **Revisão semanal** para ajustar rota.

No seu caso:

- **Task64** = Jira/Linear.
- **GitHub** = código e CI.
- **`docs/`** = Confluence/Notion, mas versionado junto do código.
- **Comentários dos cards** = diário de bordo.

O ponto principal: **o card diz o que fazer; o repositório diz como funciona; o comentário diz o que aconteceu.**
