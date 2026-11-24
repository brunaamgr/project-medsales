# Changelog

## Não lançado

### Fundação (Etapa 1, local)

- F01: Laravel 13 modular, registro de módulos e manifestos.
- F05: migrations do core com `tenant_id`, FKs e chaves compostas.
- F06: resolução por subdomínio/domínio verificado e contexto em filas.
- F07: auditoria append-only com mascaramento e consulta restrita.
- F08: 11 papéis de sistema, catálogo de permissões e escopos.
- F09: login, recuperação de senha, layout, menu por permissão e páginas de erro.
- F06b: painel da plataforma, cadastro de cliente, módulos, suspensão e convite do responsável.
- F02 (local): Dockerfile e compose com MySQL 8; migrations validadas no MySQL.
- OP01/OP02 (parcial): módulo Operação com agenda de consultas, pacientes, cadastros e campos personalizáveis por cliente.
- Unidade "Principal" criada no cadastro do cliente; convite vincula a pessoa quando há uma única unidade.
- A01/A02: Equipe e Perfis de acesso no sistema de cada cliente; login próprio por cliente; tela Minha senha.
- Operação: permissões separadas em ver, criar, alterar e cancelar; pacientes e cadastros com trava de funcionalidade.
- Operação: excluir, restaurar e apagar campos da agenda (Super Adm e plataforma).
