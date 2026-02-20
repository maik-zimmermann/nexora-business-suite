<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'admin'])->get('/test-admin-route', fn () => 'ok');
});

test('unauthenticated request gets 403', function () {
    $this->get('/test-admin-route')->assertForbidden();
});

test('non-admin user gets 403', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/test-admin-route')
        ->assertForbidden();
});

test('admin user passes through', function () {
    $role = Role::factory()->superAdmin()->create();
    $user = User::factory()->create(['admin_role_id' => $role->id]);

    $this->actingAs($user)
        ->get('/test-admin-route')
        ->assertSuccessful();
});
