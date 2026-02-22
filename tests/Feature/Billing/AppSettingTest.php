<?php

use App\Models\AppSetting;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('get returns null when key is missing', function () {
    expect(AppSetting::get('nonexistent.key'))->toBeNull();
});

test('get returns default when key is missing', function () {
    expect(AppSetting::get('nonexistent.key', 'fallback'))->toBe('fallback');
});

test('get returns stored value', function () {
    AppSetting::create(['key' => 'test.key', 'value' => 'stored-value']);

    expect(AppSetting::get('test.key'))->toBe('stored-value');
});

test('set creates a new record', function () {
    AppSetting::set('new.key', 'new-value');

    expect(AppSetting::get('new.key'))->toBe('new-value');
    expect(AppSetting::query()->count())->toBe(1);
});

test('set updates an existing record', function () {
    AppSetting::set('update.key', 'original');
    AppSetting::set('update.key', 'updated');

    expect(AppSetting::get('update.key'))->toBe('updated');
    expect(AppSetting::query()->count())->toBe(1);
});
