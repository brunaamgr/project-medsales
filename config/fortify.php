<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'login',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web', 'throttle:fortify'],
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'views' => true,
    // Sem cadastro público. 2FA TOTP entra no A04.
    'features' => [Features::resetPasswords()],
];
