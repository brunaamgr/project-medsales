<?php

namespace App\Core\Modules;

use App\Core\Modules\Exceptions\InvalidModuleManifest;

final class ModuleRegistry
{
    /** Módulo sempre ativo; não pode ser desligado por tenant. */
    public const CORE = 'core';

    /** @var array<string, ModuleManifest> */
    private array $manifests = [];

    /** @param list<string> $files */
    public function __construct(array $files)
    {
        foreach ($files as $file) {
            $manifest = ModuleManifest::fromFile($file);

            if (isset($this->manifests[$manifest->slug])) {
                throw new InvalidModuleManifest("Módulo duplicado: {$manifest->slug}.");
            }

            $this->manifests[$manifest->slug] = $manifest;
        }

        foreach ($this->manifests as $manifest) {
            foreach ($manifest->dependencies as $dependency) {
                if ($dependency === $manifest->slug || ! isset($this->manifests[$dependency])) {
                    throw new InvalidModuleManifest("Módulo {$manifest->slug}: dependência inválida ({$dependency}).");
                }
            }
        }
    }

    /** @return array<string, ModuleManifest> */
    public function all(): array
    {
        return $this->manifests;
    }

    /** @return array<string, ModuleManifest> */
    public function business(): array
    {
        return array_diff_key($this->manifests, [self::CORE => true]);
    }

    public function has(string $slug): bool
    {
        return isset($this->manifests[$slug]);
    }

    public function get(string $slug): ModuleManifest
    {
        return $this->manifests[$slug] ?? throw new InvalidModuleManifest("Módulo desconhecido: {$slug}.");
    }
}
