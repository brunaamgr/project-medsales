# ADR 0007: funcionários e perfis administrados pelo cliente

> **Cards:** A01, A02 · 24/11/2026 · Aceito pela PO em chat

**Contexto.** O plano mestre previa equipe só por convite. A PO decidiu que cada cliente cadastra a própria equipe, com login e senha, sem participação da plataforma, e que o perfil define o acesso.

**Decisão.**
- `employees` guarda a ficha (nome, CPF, RG, telefone, e-mail de contato, cargo, setor). CPF e RG criptografados; `cpf_hash` (HMAC) garante unicidade por cliente.
- `memberships.login` é único por cliente. `users.email` passa a ser opcional. Login sem `@` é procurado só no tenant do host; com `@`, é e-mail. A plataforma aceita só e-mail.
- Cadastrar sempre cria conta nova. Nunca se liga a conta existente: ela pode ser de outro cliente.
- Conta com e-mail próprio (convite) pode servir a vários clientes; por isso um cliente não troca a senha nem o nome global dela.
- Perfis: **Super Adm** (papel de sistema `tenant_owner`, tudo que foi contratado), **Adm** e **Comum** (papéis do cliente, editáveis em `/perfis` pelo Super Adm).
- Adm precisa conter tudo do Comum, com alcance igual ou maior; senão criaria um Comum para fazer o que não pode.
- "Gerenciar" foi separado em criar, alterar e cancelar. Excluir entra por área conforme a ação existir; hoje só em funcionários.
- Sem troca obrigatória de senha no primeiro acesso (decisão da PO). Há a tela "Minha senha".

**Consequências.**
- Quem cadastra conhece a senha inicial até a pessoa trocar. A auditoria não distingue os dois nesse período.
- Sem e-mail não há recuperação: um Adm ou Super Adm troca a senha.
- Perder a `APP_KEY` perde CPF e RG e invalida o `cpf_hash`.
- Excluir funcionário apaga documentos e contatos e mantém o nome, para o histórico. Prazo de retenção a validar com o jurídico.
- A migration não reverte se já houver conta sem e-mail.
- `'essential' => true` liga a funcionalidade nova em quem já tem o módulo; permissão fora dos manifestos é removida no `access:sync`.
