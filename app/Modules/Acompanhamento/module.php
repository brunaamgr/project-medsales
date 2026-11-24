<?php

return [
    'slug' => 'acompanhamento',
    'name' => 'Acompanhamento',
    'version' => '0.1.0',
    'dependencies' => ['core'],
    'default_enabled' => false,
    'features' => [
        'pacientes' => ['name' => 'Pacientes', 'default' => true],
        'diario' => ['name' => 'Diário', 'default' => true],
        'medidas' => ['name' => 'Medidas', 'default' => true],
        'fotos' => ['name' => 'Fotos', 'default' => false],
        'exames' => ['name' => 'Exames', 'default' => false],
        'mensagens' => ['name' => 'Mensagens', 'default' => true],
        'plano' => ['name' => 'Plano', 'default' => true],
        'pagamentos' => ['name' => 'Pagamentos', 'default' => false],
    ],
    'permissions' => [
        'acompanhamento.paciente.view' => ['description' => 'Ver pacientes', 'feature' => 'pacientes'],
        'acompanhamento.paciente.create' => ['description' => 'Cadastrar pacientes', 'feature' => 'pacientes'],
        'acompanhamento.paciente.update' => ['description' => 'Editar pacientes', 'feature' => 'pacientes'],
        'acompanhamento.diario.view' => ['description' => 'Ver diário', 'feature' => 'diario'],
        'acompanhamento.diario.create' => ['description' => 'Registrar diário', 'feature' => 'diario'],
        'acompanhamento.exame.view' => ['description' => 'Ver exames', 'feature' => 'exames'],
        'acompanhamento.exame.upload' => ['description' => 'Enviar exames', 'feature' => 'exames'],
        'acompanhamento.mensagem.view' => ['description' => 'Ver mensagens', 'feature' => 'mensagens'],
        'acompanhamento.mensagem.send' => ['description' => 'Enviar mensagens', 'feature' => 'mensagens'],
        'acompanhamento.plano.view' => ['description' => 'Ver plano', 'feature' => 'plano'],
        'acompanhamento.plano.edit' => ['description' => 'Editar plano', 'feature' => 'plano'],
        'acompanhamento.pagamento.view' => ['description' => 'Ver pagamentos', 'feature' => 'pagamentos'],
    ],
];
