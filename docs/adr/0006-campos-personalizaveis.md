# ADR 0006: campos personalizáveis por cliente

> **Cards:** OP01, OP02 · 24/11/2026 · Proposto (aguarda aceite da PO)

**Contexto.** Clientes usam a agenda com campos diferentes (ver `docs/modulos/operacao.md`). Fork e `if (cliente)` são proibidos.

**Decisão.** Campos conhecidos são colunas com FK; a configuração por cliente fica em `operacao_form_fields`. Campos criados pelo cliente ficam em `custom_values`, um JSON **criptografado** na linha da consulta, porque podem conter dado de saúde.

**Alternativas.** EAV (uma linha por valor): permite filtrar, mas complica consulta e exclusão. JSON aberto: pesquisável, porém expõe dado de saúde no dump. Uma tabela por cliente: é fork.

**Consequências.**
- Não dá para filtrar ou ordenar por campo criado. Se um deles virar necessário em relatório, vira coluna padrão.
- Perder a `APP_KEY` perde observações e campos criados: a chave entra no plano de backup (I06).
- Data e hora da consulta são valores civis do local, não instantes UTC.
- Nome e telefone do paciente ficam em claro, para busca; acesso restrito por escopo e auditado.
- `'available' => false` no manifesto impede liberar funcionalidade que ainda não existe.
