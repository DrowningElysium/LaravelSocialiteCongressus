# Congressus Socialite Provider
This package adds a [Laravel Socialite](https://laravel.com/docs/socialite) provider for [Congressus](https://congressus.nl).

### Configuration for `config/services.php`
```php
return [
    // ...
    'congressus' => [
        'domain' => 'https://www.association.url', // The url of the association to connect to
        'client_id' => env('CONGRESSUS_CLIENT_ID'),
        'client_secret' => env('CONGRESSUS_CLIENT_SECRET'),
        'redirect' => env('APP_URL').'/callback-url',
    ],
    // ...
];
```
