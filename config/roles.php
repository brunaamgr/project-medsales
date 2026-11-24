<?php

/*
 * Papéis de sistema: escopo => permissões. É dado, não código: clientes com
 * necessidade própria ganham papel customizado (A02), nunca um if.
 */

$acompanhamento = [
    'acompanhamento.paciente.view', 'acompanhamento.paciente.create', 'acompanhamento.paciente.update',
    'acompanhamento.diario.view', 'acompanhamento.diario.create', 'acompanhamento.exame.view',
    'acompanhamento.exame.upload', 'acompanhamento.mensagem.view', 'acompanhamento.mensagem.send',
    'acompanhamento.plano.view', 'acompanhamento.plano.edit',
];

$operacao = [
    'operacao.agenda.view', 'operacao.agenda.create', 'operacao.agenda.update', 'operacao.agenda.cancel', 'operacao.cirurgia.view', 'operacao.cirurgia.create',
    'operacao.cirurgia.approve', 'operacao.paciente.view', 'operacao.paciente.create', 'operacao.paciente.update',
    'operacao.cadastro.view', 'operacao.cadastro.create', 'operacao.cadastro.update',
    'operacao.campo.view', 'operacao.campo.create', 'operacao.campo.update', 'operacao.estoque.view', 'operacao.estoque.edit', 'operacao.contrato.view', 'operacao.contrato.manage',
];

$precificacao = [
    'precificacao.tabela.view', 'precificacao.tabela.edit', 'precificacao.simulador.view',
    'precificacao.simulador.create', 'precificacao.bonificacao.view', 'precificacao.bonificacao.edit',
    'precificacao.proposta.view', 'precificacao.proposta.create', 'precificacao.proposta.approve',
];

$usuarios = [
    'core.usuario.view', 'core.usuario.create', 'core.usuario.update', 'core.usuario.suspend', 'core.usuario.reset_password',
    'core.audit.view',
];

return [
    // Sem permissões dentro de tenants; o painel da plataforma é decisão em aberto.
    'platform_admin' => ['name' => 'Administração da plataforma', 'level' => 100, 'grants' => []],

    // Super Adm do cliente: tudo que existir, limitado ao que foi contratado.
    'tenant_owner' => ['name' => 'Super Adm', 'level' => 90, 'grants_all_except' => ['core.tenant.manage', 'core.module.manage'], 'grants' => []],

    'tenant_admin' => ['name' => 'Administração', 'level' => 80, 'grants' => ['tenant' => [
        ...$usuarios, ...$acompanhamento, ...$operacao, 'operacao.financeiro.view', ...$precificacao,
        'bi.dashboard.view', 'bi.relatorio.view',
    ]]],

    'manager' => ['name' => 'Gestão', 'level' => 60, 'grants' => [
        'tenant' => [...$precificacao, 'bi.dashboard.view', 'bi.relatorio.view'],
        'unit' => ['operacao.agenda.view', 'operacao.agenda.create', 'operacao.agenda.update', 'operacao.agenda.cancel', 'operacao.paciente.view', 'operacao.paciente.create', 'acompanhamento.paciente.view'],
    ]],

    'doctor' => ['name' => 'Médico(a)', 'level' => 50, 'grants' => [
        'unit' => ['operacao.agenda.view'],
        'own' => [
            ...$acompanhamento, 'operacao.agenda.create', 'operacao.agenda.update', 'operacao.agenda.cancel', 'operacao.cirurgia.view', 'operacao.cirurgia.create',
            'operacao.cirurgia.approve', 'operacao.paciente.view', 'operacao.paciente.create', 'operacao.paciente.update',
        ],
    ]],

    'nurse' => ['name' => 'Enfermagem', 'level' => 40, 'grants' => [
        'unit' => [
            'operacao.agenda.view', 'operacao.cirurgia.view', 'operacao.paciente.view',
            'acompanhamento.paciente.view', 'acompanhamento.diario.view', 'acompanhamento.diario.create',
            'acompanhamento.exame.view', 'acompanhamento.exame.upload',
        ],
    ]],

    'reception' => ['name' => 'Recepção', 'level' => 30, 'grants' => [
        'unit' => ['operacao.agenda.view', 'operacao.agenda.create', 'operacao.agenda.update', 'operacao.agenda.cancel', 'operacao.paciente.view', 'operacao.paciente.create', 'operacao.paciente.update'],
    ]],

    'finance' => ['name' => 'Financeiro', 'level' => 30, 'grants' => [
        'tenant' => ['operacao.financeiro.view', 'operacao.financeiro.edit', 'acompanhamento.pagamento.view', 'bi.dashboard.view', 'bi.relatorio.view'],
    ]],

    'auditor' => ['name' => 'Auditoria', 'level' => 30, 'grants' => [
        'tenant' => ['core.audit.view', 'operacao.agenda.view', 'acompanhamento.paciente.view', 'bi.dashboard.view', 'bi.relatorio.view'],
    ]],

    // Permissões do portal entram no AC02.
    'patient' => ['name' => 'Paciente', 'level' => 10, 'grants' => []],

    // API keys entram com as integrações; sem login humano.
    'integration' => ['name' => 'Integração', 'level' => 0, 'grants' => []],
];
