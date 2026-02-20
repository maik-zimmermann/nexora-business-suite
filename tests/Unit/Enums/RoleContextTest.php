<?php

use App\Enums\RoleContext;

test('tenant context has correct value', function () {
    expect(RoleContext::Tenant->value)->toBe('tenant');
});

test('administration context has correct value', function () {
    expect(RoleContext::Administration->value)->toBe('administration');
});

test('enum can be created from string value', function () {
    expect(RoleContext::from('tenant'))->toBe(RoleContext::Tenant);
    expect(RoleContext::from('administration'))->toBe(RoleContext::Administration);
});
