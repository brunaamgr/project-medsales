<?php

/*
 * Ponto de partida dos perfis de cada cliente; o Super Adm ajusta na tela Perfis.
 * Super Adm não aparece aqui: tem sempre tudo que foi contratado.
 */
return [
    // Nunca vão para Adm ou Comum.
    'super_only' => [
        'core.role.view', 'core.role.manage', 'core.security.manage', 'core.billing.manage', 'core.audit.export',
        'core.usuario.reset_mfa', 'core.tenant.manage', 'core.module.manage',
    ],

    // Prefixo que o Comum nunca recebe.
    'comum_blocked_prefix' => 'core.',

    'defaults' => [
        'adm' => [
            'scope' => 'tenant',
            // "*" = todas as permissões de módulos de negócio.
            'permissions' => ['*', 'core.usuario.view', 'core.usuario.create', 'core.usuario.update', 'core.usuario.suspend', 'core.usuario.reset_password'],
        ],
        'comum' => [
            'scope' => 'unit',
            'permissions' => [
                'operacao.agenda.view', 'operacao.agenda.create', 'operacao.agenda.update',
                'operacao.paciente.view', 'operacao.paciente.create',
            ],
        ],
    ],
];
