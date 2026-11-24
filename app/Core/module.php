<?php

return [
    'slug' => 'core',
    'name' => 'Núcleo',
    'version' => '0.1.0',
    'dependencies' => [],
    'default_enabled' => true,
    'features' => [],
    'permissions' => [
        'core.usuario.view' => ['description' => 'Ver funcionários'],
        'core.usuario.create' => ['description' => 'Cadastrar funcionários'],
        'core.usuario.update' => ['description' => 'Alterar funcionários'],
        'core.usuario.suspend' => ['description' => 'Suspender e reativar funcionários'],
        'core.usuario.reset_password' => ['description' => 'Trocar a senha de funcionários'],
        'core.usuario.delete' => ['description' => 'Excluir funcionários'],
        'core.usuario.reset_mfa' => ['description' => 'Resetar MFA'],
        'core.role.view' => ['description' => 'Ver papéis'],
        'core.role.manage' => ['description' => 'Gerenciar papéis'],
        'core.audit.view' => ['description' => 'Ver auditoria'],
        'core.audit.export' => ['description' => 'Exportar auditoria'],
        'core.tenant.manage' => ['description' => 'Gerenciar tenants'],
        'core.module.manage' => ['description' => 'Gerenciar módulos contratados'],
        'core.security.manage' => ['description' => 'Gerenciar segurança'],
        'core.billing.manage' => ['description' => 'Gerenciar cobrança'],
    ],
];
