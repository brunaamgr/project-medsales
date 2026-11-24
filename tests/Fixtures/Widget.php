<?php

namespace Tests\Fixtures;

use App\Core\Permissions\ScopedResource;
use App\Core\Tenancy\BelongsToTenant;
use App\Shared\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Model de domínio sintético para testar isolamento e escopos. */
class Widget extends Model implements ScopedResource
{
    use BelongsToTenant, HasPublicId;

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::dropIfExists('widgets');
        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->ulid('public_id');
            $table->foreignId('unit_id')->nullable();
            $table->foreignId('owner_id')->nullable();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function unitColumn(): ?string
    {
        return 'unit_id';
    }

    public function ownerColumn(): ?string
    {
        return 'owner_id';
    }
}
