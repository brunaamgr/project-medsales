<?php

namespace App\Core\Platform;

use App\Core\Audit\AuditLogger;
use App\Core\Platform\Models\PlatformAdmin;
use App\Core\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Cria o primeiro acesso da plataforma. A senha é digitada, nunca passada por argumento. */
final class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin {email} {--name=}';

    protected $description = 'Concede acesso ao painel da plataforma';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();
        $input = ['email' => $email];
        $rules = ['email' => ['required', 'email:rfc', 'max:255']];

        if ($user === null) {
            $input += [
                'name' => (string) ($this->option('name') ?: $this->ask('Nome')),
                'password' => (string) $this->secret('Senha (mínimo de 12 caracteres)'),
            ];
            $rules += ['name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::default()]];
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $input, $audit): void {
            $user ??= User::query()->create($input);
            PlatformAdmin::query()->updateOrCreate(['user_id' => $user->id], ['status' => 'active']);
            $audit->record('platform.admin.granted', $user);
        });

        $this->info('Acesso à plataforma concedido.');

        return self::SUCCESS;
    }
}
