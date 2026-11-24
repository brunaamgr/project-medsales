<?php

namespace App\Core\Modules;

use App\Core\Modules\Exceptions\InvalidModuleManifest;

/**
 * Representa o module.php já validado. Um manifesto inválido derruba o boot:
 * é melhor falhar cedo do que subir com permissão faltando.
 */
final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $dependencies
     * @param  array<string, array{name: string, default: bool, available: bool, essential: bool}>  $features
     * @param  array<string, array{description: string, feature: string|null, super_only: bool}>  $permissions
     */
    private function __construct(
        public string $slug,
        public string $name,
        public string $version,
        public array $dependencies,
        public bool $defaultEnabled,
        public array $features,
        public array $permissions,
        public string $path,
    ) {}

    public static function fromFile(string $file): self
    {
        $data = require $file;

        if (! is_array($data)) {
            throw new InvalidModuleManifest("{$file} deve retornar um array.");
        }

        return self::fromArray($data, dirname($file));
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data, string $path): self
    {
        $slug = $data['slug'] ?? null;

        if (! is_string($slug) || preg_match('/^[a-z][a-z0-9_]*$/', $slug) !== 1) {
            throw new InvalidModuleManifest("Slug de módulo inválido em {$path}.");
        }

        if (! is_string($data['name'] ?? null) || $data['name'] === '') {
            throw new InvalidModuleManifest("Módulo {$slug}: name é obrigatório.");
        }

        $features = [];
        foreach ($data['features'] ?? [] as $key => $feature) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1 || ! is_array($feature)) {
                throw new InvalidModuleManifest("Módulo {$slug}: funcionalidade inválida.");
            }

            $features["{$slug}.{$key}"] = [
                'name' => (string) ($feature['name'] ?? $key),
                'default' => (bool) ($feature['default'] ?? false),
                // false = prevista, ainda não construída: o painel não deixa liberar.
                'available' => (bool) ($feature['available'] ?? true),
                // true = ao nascer, liga sozinha em quem já tem o módulo (não é item comercial).
                'essential' => (bool) ($feature['essential'] ?? false),
            ];
        }

        $permissions = [];
        foreach ($data['permissions'] ?? [] as $permission => $meta) {
            if (! is_string($permission) || ! is_array($meta)
                || preg_match('/^'.$slug.'\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $permission) !== 1) {
                throw new InvalidModuleManifest("Módulo {$slug}: permissão deve seguir {$slug}.recurso.acao.");
            }

            $feature = isset($meta['feature']) ? "{$slug}.{$meta['feature']}" : null;

            if ($feature !== null && ! isset($features[$feature])) {
                throw new InvalidModuleManifest("Permissão {$permission}: funcionalidade {$feature} não declarada.");
            }

            $permissions[$permission] = [
                'description' => (string) ($meta['description'] ?? $permission),
                'feature' => $feature,
                // true = só Super Adm; não aparece na tela de perfis nem entra em papel do cliente.
                'super_only' => (bool) ($meta['super_only'] ?? false),
            ];
        }

        return new self(
            $slug,
            $data['name'],
            (string) ($data['version'] ?? '0.1.0'),
            array_values($data['dependencies'] ?? []),
            (bool) ($data['default_enabled'] ?? false),
            $features,
            $permissions,
            $path,
        );
    }
}
