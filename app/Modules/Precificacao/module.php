<?php

return [
    'slug' => 'precificacao',
    'name' => 'Precificação',
    'version' => '0.1.0',
    'dependencies' => ['core'],
    'default_enabled' => false,
    'features' => [
        'tabela' => ['name' => 'Tabela de preços', 'default' => true],
        'simulador' => ['name' => 'Simulador', 'default' => true],
        'bonificacao' => ['name' => 'Bonificação', 'default' => false],
        'proposta' => ['name' => 'Proposta', 'default' => true],
    ],
    'permissions' => [
        'precificacao.tabela.view' => ['description' => 'Ver tabela de preços', 'feature' => 'tabela'],
        'precificacao.tabela.edit' => ['description' => 'Editar tabela de preços', 'feature' => 'tabela'],
        'precificacao.simulador.view' => ['description' => 'Ver simulações', 'feature' => 'simulador'],
        'precificacao.simulador.create' => ['description' => 'Criar simulações', 'feature' => 'simulador'],
        'precificacao.bonificacao.view' => ['description' => 'Ver bonificações', 'feature' => 'bonificacao'],
        'precificacao.bonificacao.edit' => ['description' => 'Editar bonificações', 'feature' => 'bonificacao'],
        'precificacao.proposta.view' => ['description' => 'Ver propostas', 'feature' => 'proposta'],
        'precificacao.proposta.create' => ['description' => 'Criar propostas', 'feature' => 'proposta'],
        'precificacao.proposta.approve' => ['description' => 'Aprovar propostas', 'feature' => 'proposta'],
    ],
];
