# Modelo — Registro Seguro de Acessos

**Objetivo:** controlar quem tem acesso a quê, sem colocar senhas, tokens ou chaves no Task64, WhatsApp, e-mail, Git ou documentação aberta.

---

## 1. Regra principal

> **O segredo fica no cofre. O card e a documentação guardam apenas o metadado e a referência.**

| O que guardar | Onde |
|---|---|
| Senha do cPanel | Cofre de senhas (Bitwarden/1Password/KeePassXC) |
| Chave SSH privada | Cofre + `ssh-agent`; nunca no Git |
| Token de API | Cofre ou secrets do GitHub/ValueHost |
| Senha do banco | Cofre + `.env` no servidor (fora do Git) |
| Quem tem acesso | Inventário de acessos (sem segredo) |
| Status “acesso validado” | Comentário no card do Task64 |
| Referência do cofre | Card + inventário (ex.: “Bitwarden > MedSales > ValueHost”) |

---

## 2. O que colocar no card do Task64

No card `[D01] Mapear acessos`, marque o checklist e comente **sem segredo**:

```text
## Acesso ValueHost

- Titular: Ornilio
- Conta: cPanel principal
- MFA: ativado
- Cofre: Bitwarden > MedSales > ValueHost
- SSH: chave ed25519 individual, passphrase no cofre
- Validado em: 24/11/2026
- Evidência: docs/seguranca/acessos.md
- Pendência: nenhuma
```

Não escreva:

- senha;
- token;
- chave privada;
- resposta de recuperação;
- código MFA;
- print com dados sensíveis.

---

## 3. Inventário de acessos (sem segredos)

Crie `docs/seguranca/acessos.md` no projeto:

```markdown
# Inventário de Acessos

> Card: [D01] Mapear acessos
> Última revisão: 24/11/2026

| Serviço | URL/Ambiente | Titular | Tipo de acesso | MFA | Cofre (referência) | Última rotação | Status |
|---|---|---|---|---|---|---|---|
| ValueHost | cPanel/SSH | Ornilio | Admin | Sim | Bitwarden > MedSales > ValueHost | 24/11/2026 | OK |
| GitHub | github.com | Ornilio | Owner | Sim | Bitwarden > MedSales > GitHub | 24/11/2026 | OK |
| Vercel | vercel.com | ... | Member | Sim | Bitwarden > MedSales > Vercel | ... | Pendente |
| Supabase | supabase.com | ... | Read-only | Sim | Bitwarden > MedSales > Supabase | ... | OK |
```

**Nunca** inclua a senha ou o token nessa tabela. A coluna “Cofre” é só a referência.

---

## 4. Regras para o ValueHost

- Ativar **MFA** no cPanel, se disponível.
- Usar **SSH com chave ed25519** por pessoa; não compartilhar chave privada.
- Preferir SFTP/SSH; evitar FTP puro.
- Criar usuário de banco separado para a aplicação, sem root e sem `FILE`.
- Guardar a senha do banco no `.env` do servidor, fora do `public_html` e fora do Git.
- Guardar a passphrase da chave SSH no cofre.
- Revisar acessos a cada 90 dias ou ao desligar alguém.
- Não usar a mesma senha do cPanel em outros serviços.

---

## 5. Quando marcar o item como concluído

Só marque “ValueHost (cPanel/SSH)” como feito quando:

- [ ] O responsável/titular está definido.
- [ ] A credencial está no cofre.
- [ ] MFA está ativo.
- [ ] O acesso foi testado.
- [ ] O inventário foi atualizado.
- [ ] Nenhum segredo foi colocado no card/doc.
- [ ] Há evidência no comentário do card.

“Saber quem vai acessar” não é suficiente; o acesso precisa estar **configurado, seguro e testado**.

---

## 6. O que profissionais fazem

1. **Cofre de senhas** com 2FA e compartilhamento por usuário.
2. **Contas individuais**, não credenciais compartilhadas.
3. **Princípio do menor privilégio**: cada pessoa só acessa o necessário.
4. **Inventário de acessos** sem segredos.
5. **Rotação** após exposição, desligamento ou troca de função.
6. **Auditoria** de quem acessou e quando.
7. **Referência no card**, não o segredo.

Ferramentas comuns: Bitwarden, 1Password, KeePassXC, Doppler, AWS Secrets Manager, GitHub Secrets, Vault.

---

## 7. O que nunca fazer

- Colocar senha/token no Task64, Trello, Notion, Google Docs ou WhatsApp.
- Commitar `.env` ou chave SSH.
- Compartilhar a senha do cPanel por mensagem.
- Usar a mesma senha em vários serviços.
- Tirar print de tela com token visível.
- Guardar segredo em `docs/` ou no README.
- Deixar ex-funcionário com acesso ativo.

---

## 8. Resposta curta para o D01

**Sim, está correto:** guarde a credencial onde você já guarda (idealmente num cofre), e no Task64 apenas:

1. marque o checklist;
2. comente “acesso configurado e validado”;
3. referencie o cofre e o inventário;
4. não escreva a senha.

A documentação extra necessária é só o **inventário de acessos sem segredos**.
