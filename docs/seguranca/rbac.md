# RBAC

> **Cards:** F08, A01, A02

Tenant → Membership → Role → Permission → Scope → Module/Feature. Negado por padrão.

| Escopo | Alcance |
|---|---|
| tenant | tudo do tenant |
| unit | unidades do vínculo |
| own | registros da própria pessoa |
| assigned | só concessão pontual em `resource_acl` |

A `resource_acl` soma acesso a quem já tem a permissão em algum escopo; sozinha não concede nada.

## Perfis de cada cliente (ADR 0007)

| Perfil | Pode | Configurável |
|---|---|---|
| Super Adm | tudo que foi contratado, e configurar perfis | não |
| Adm | o que o Super Adm marcar; gerencia só quem está abaixo | sim |
| Comum | o que o Super Adm marcar; nunca administração | sim |

- Ponto de partida: `config/profiles.php`. Configuração do cliente: `/perfis`.
- Permissão com `'super_only' => true` no `module.php` é exclusiva do Super Adm: não aparece em `/perfis` e nunca entra em Adm ou Comum.
- Ninguém muda o próprio perfil, se suspende ou se exclui. O último Super Adm ativo é protegido.
- Trocar perfil, senha ou status encerra as sessões da pessoa.
- Papéis de sistema antigos (recepção, médico etc., em `config/roles.php`) seguem valendo até a pessoa receber um perfil.
