<?php

test('registration route is not available (registration disabled)', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('registration store route is not available (registration disabled)', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
});
