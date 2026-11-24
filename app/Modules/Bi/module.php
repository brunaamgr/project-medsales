<?php

return [
    'slug' => 'bi',
    'name' => 'BI',
    'version' => '0.1.0',
    'dependencies' => ['core'],
    'default_enabled' => false,
    'features' => [
        'dashboards' => ['name' => 'Dashboards', 'default' => true],
        'relatorios' => ['name' => 'Relatórios', 'default' => true],
        'exportacoes' => ['name' => 'Exportações', 'default' => false],
    ],
    'permissions' => [
        'bi.dashboard.view' => ['description' => 'Ver dashboards', 'feature' => 'dashboards'],
        'bi.relatorio.view' => ['description' => 'Ver relatórios', 'feature' => 'relatorios'],
        'bi.relatorio.export' => ['description' => 'Exportar relatórios', 'feature' => 'exportacoes'],
    ],
];
