<?php

use App\Models\Module;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('welcome page loads successfully', function () {
    $response = $this->get(appUrl('/'));

    $response->assertSuccessful();
});

test('welcome page renders the Welcome component', function () {
    $response = $this->get(appUrl('/'));

    $response->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('welcome page includes active modules', function () {
    $modules = Module::query()->where('is_active', true)->orderBy('sort_order')->get();

    $response = $this->get(appUrl('/'));

    $response->assertInertia(fn ($page) => $page
        ->component('Welcome')
        ->has('modules', $modules->count())
    );
});
