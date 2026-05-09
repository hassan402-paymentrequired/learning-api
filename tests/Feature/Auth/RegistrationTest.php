<?php

use Illuminate\Support\Facades\Route;

test('web registration routes are not registered', function () {
    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();
});

test('get register page returns not found', function () {
    $this->get('/register')->assertNotFound();
});
