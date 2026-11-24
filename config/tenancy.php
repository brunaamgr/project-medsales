<?php

return [
    // Local: "localhost" (ex.: pinhabel.localhost:8000). Produção: medsalesbr.com.
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'localhost'),

    // Painel da plataforma. Produção: admin.medsalesbr.com.
    'platform_host' => strtolower(env('PLATFORM_HOST', 'admin.localhost')),

    // Nunca viram tenant. Inclui subdomínios já em uso no DNS de medsalesbr.com.
    'reserved_subdomains' => [
        'www', 'app', 'admin', 'api', 'mail', 'smtp', 'ftp', 'staging', 'dev', 'test', 'teste', 'status',
        'suporte', 'help', 'cdn', 'static', 'assets', 'login', 'sso', 'auth', 'painel', 'sistema', 'medsales',
        'centralclientes', 'estudobiblico', 'financeiro', 'gestaopinhabel', 'operacaopinhabel', 'socialmedia',
        'teleprompter', 'videos', 'gestaodramychelle', 'gestaoferraz', 'gestarefluir', 'linhaseditoriais',
        'manualpatriciajunqueira', 'acompanhamentosdramychelle', 'popsdrkelso',
    ],

    // Limite absoluto da sessão, em minutos (o de inatividade é SESSION_LIFETIME).
    'session_absolute_minutes' => (int) env('SESSION_ABSOLUTE_MINUTES', 720),
];
