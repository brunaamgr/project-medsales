<?php

use App\Core\Tenancy\BelongsToTenant;

arch('sem funções de debug')->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();
arch('o core não depende de módulos')->expect('App\Core')->not->toUse('App\Modules');
arch('módulos não usam o facade DB')->expect('App\Modules')->not->toUse('Illuminate\Support\Facades\DB');
arch('código compartilhado não depende de módulos')->expect('App\Shared')->not->toUse('App\Modules');

it('todo model de módulo usa BelongsToTenant', function () {
    foreach (glob(app_path('Modules/*/Models/*.php')) ?: [] as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], str_replace('\\', '/', substr($file, strlen(app_path()) + 1)));
        expect(in_array(BelongsToTenant::class, class_uses_recursive($class), true))->toBeTrue($class);
    }
});
