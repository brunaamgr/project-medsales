<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\MissingTenantContext;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/** Leva o tenant no payload do job e restaura o contexto na execução. */
final class QueueTenancy
{
    /** @var list<array{Tenant|null, bool}> */
    private array $stack = [];

    public function register(): void
    {
        Queue::createPayloadUsing(fn (): array => ['tenant_id' => app(TenantContext::class)->id()]);

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $context = app(TenantContext::class);
            $this->stack[] = $context->snapshot();

            $id = $event->job->payload()['tenant_id'] ?? null;
            $tenant = $id === null ? null : Tenant::query()->find($id);
            $context->set($tenant instanceof Tenant && $tenant->isActive() ? $tenant : null);

            if ($id !== null && $context->current() === null) {
                throw new MissingTenantContext;
            }
        });

        // Pilha: na fila sync o contexto do request precisa voltar ao fim do job.
        $restore = fn () => app(TenantContext::class)->restore(array_pop($this->stack) ?? [null, false]);
        Event::listen(JobProcessed::class, $restore);
        Event::listen(JobExceptionOccurred::class, $restore);
    }
}
