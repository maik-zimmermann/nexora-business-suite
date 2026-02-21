<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration route returns 404', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});

test('registration post returns 404', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(404);
});
