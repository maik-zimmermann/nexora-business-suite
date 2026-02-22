<?php

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

afterEach(function () {
    app(Tenancy::class)->flush();
});

test('subdomain resolves active tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $response = $this->get(tenantUrl('acme'));

    $response->assertOk();
    expect(app(Tenancy::class)->get())->not->toBeNull();
    expect(app(Tenancy::class)->get()->slug)->toBe('acme');
});

test('subdomain with inactive tenant returns 403', function () {
    Tenant::factory()->inactive()->create(['slug' => 'dormant']);

    $response = $this->get(tenantUrl('dormant'));

    $response->assertStatus(403);
});

test('unknown subdomain returns 404 and logs failure', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $message === 'Tenant resolution failed'
            && $context['strategy'] === 'subdomain'
            && $context['slug'] === 'unknown'
        );

    $response = $this->get(tenantUrl('unknown'));

    $response->assertStatus(404);
});

test('valid X-Tenant-ID with valid signature resolves tenant', function () {
    $tenant = Tenant::factory()->create();
    $signature = hash_hmac('sha256', $tenant->id, config('app.key'));

    $response = $this->withHeaders([
        'X-Tenant-ID' => $tenant->id,
        'X-Tenant-Signature' => $signature,
    ])->get(appUrl());

    $response->assertOk();
    expect(app(Tenancy::class)->get())->not->toBeNull();
    expect(app(Tenancy::class)->get()->id)->toBe($tenant->id);
});

test('valid X-Tenant-ID with invalid signature returns 403', function () {
    $tenant = Tenant::factory()->create();

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['strategy'] === 'header');

    $response = $this->withHeaders([
        'X-Tenant-ID' => $tenant->id,
        'X-Tenant-Signature' => 'invalid-signature',
    ])->get(appUrl());

    $response->assertStatus(403);
});

test('valid X-Tenant-ID with missing signature returns 403', function () {
    $tenant = Tenant::factory()->create();

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['strategy'] === 'header');

    $response = $this->withHeaders([
        'X-Tenant-ID' => $tenant->id,
    ])->get(appUrl());

    $response->assertStatus(403);
});

test('subdomain takes precedence over X-Tenant-ID header', function () {
    $subdomainTenant = Tenant::factory()->create(['slug' => 'acme']);
    $headerTenant = Tenant::factory()->create();
    $signature = hash_hmac('sha256', $headerTenant->id, config('app.key'));

    $response = $this->withHeaders([
        'X-Tenant-ID' => $headerTenant->id,
        'X-Tenant-Signature' => $signature,
    ])->get(tenantUrl('acme'));

    $response->assertOk();
    expect(app(Tenancy::class)->get()->id)->toBe($subdomainTenant->id);
});

test('no subdomain and no header passes through without tenant', function () {
    $response = $this->get(appUrl());

    $response->assertOk();
    expect(app(Tenancy::class)->hasTenant())->toBeFalse();
});

test('RequiresTenant middleware aborts 404 when no tenant set', function () {
    Route::middleware(['web', 'tenant'])->get('/test-tenant-required', fn () => 'ok');

    $response = $this->get(appUrl('/test-tenant-required'));

    $response->assertStatus(404);
});

test('RequiresTenant middleware passes when tenant is set', function () {
    Route::middleware(['web', 'tenant'])->get('/test-tenant-required', fn () => 'ok');

    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $response = $this->get(tenantUrl('acme', '/test-tenant-required'));

    $response->assertOk();
    expect($response->getContent())->toBe('ok');
});

test('BelongsToTenant auto-assigns tenant_id on model creation', function () {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    // Create a temporary table and model for testing the trait
    \Illuminate\Support\Facades\Schema::create('tenant_test_items', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->uuid('tenant_id');
        $table->string('name');
        $table->timestamps();

        $table->foreign('tenant_id')->references('id')->on('tenants');
    });

    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        use \App\Concerns\BelongsToTenant;

        protected $table = 'tenant_test_items';

        protected $fillable = ['name'];
    };

    $item = $model::create(['name' => 'Test Item']);

    expect($item->tenant_id)->toBe($tenant->id);
    expect($item->tenant->id)->toBe($tenant->id);
});

test('global scope filters queries to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    \Illuminate\Support\Facades\Schema::create('tenant_scoped_items', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->uuid('tenant_id');
        $table->string('name');
        $table->timestamps();

        $table->foreign('tenant_id')->references('id')->on('tenants');
    });

    $modelClass = new class extends \Illuminate\Database\Eloquent\Model
    {
        use \App\Concerns\BelongsToTenant;

        protected $table = 'tenant_scoped_items';

        protected $fillable = ['name'];
    };

    // Create items for tenant A
    app(Tenancy::class)->set($tenantA);
    $modelClass::create(['name' => 'Item A1']);
    $modelClass::create(['name' => 'Item A2']);

    // Create items for tenant B
    app(Tenancy::class)->set($tenantB);
    $modelClass::create(['name' => 'Item B1']);

    // Query as tenant A — should only see their items
    app(Tenancy::class)->set($tenantA);
    $itemsA = $modelClass::all();
    expect($itemsA)->toHaveCount(2);
    expect($itemsA->pluck('name')->all())->toBe(['Item A1', 'Item A2']);

    // Query as tenant B — should only see their items
    app(Tenancy::class)->set($tenantB);
    $itemsB = $modelClass::all();
    expect($itemsB)->toHaveCount(1);
    expect($itemsB->first()->name)->toBe('Item B1');
});
