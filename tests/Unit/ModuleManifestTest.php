<?php

use App\Core\Modules\Exceptions\InvalidModuleManifest;
use App\Core\Modules\ModuleManifest;
use App\Core\Modules\ModuleRegistry;

$valid = ['slug' => 'demo', 'name' => 'Demo', 'features' => ['lista' => ['name' => 'Lista']], 'permissions' => ['demo.item.view' => ['feature' => 'lista']]];

it('aceita manifesto válido', function () use ($valid) {
    $manifest = ModuleManifest::fromArray($valid, '/tmp');

    expect($manifest->defaultEnabled)->toBeFalse()
        ->and($manifest->permissions['demo.item.view']['feature'])->toBe('demo.lista');
});

it('recusa manifesto inválido', function (array $change) use ($valid) {
    ModuleManifest::fromArray([...$valid, ...$change], '/tmp');
})->throws(InvalidModuleManifest::class)->with([
    'slug' => [['slug' => 'Demo-X']],
    'nome vazio' => [['name' => '']],
    'permissão de outro módulo' => [['permissions' => ['outro.item.view' => []]]],
    'permissão com duas partes' => [['permissions' => ['demo.view' => []]]],
    'funcionalidade não declarada' => [['permissions' => ['demo.item.view' => ['feature' => 'x']]]],
]);

it('recusa dependência ausente e módulo duplicado', function () {
    $file = tempnam(sys_get_temp_dir(), 'mod');
    file_put_contents($file, "<?php return ['slug' => 'demo', 'name' => 'Demo', 'dependencies' => ['fantasma']];");
    expect(fn () => new ModuleRegistry([$file]))->toThrow(InvalidModuleManifest::class, 'fantasma');

    file_put_contents($file, "<?php return ['slug' => 'demo', 'name' => 'Demo'];");
    expect(fn () => new ModuleRegistry([$file, $file]))->toThrow(InvalidModuleManifest::class, 'duplicado');
    unlink($file);
});

it('registra os módulos do produto', function () {
    expect(array_keys(app(ModuleRegistry::class)->business()))
        ->toEqualCanonicalizing(['acompanhamento', 'operacao', 'precificacao', 'bi']);
});
