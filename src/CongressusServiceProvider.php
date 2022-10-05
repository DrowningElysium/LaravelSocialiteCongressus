<?php

namespace DrowningElysium\LaravelSocialiteCongressus;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

class CongressusServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function boot(): void
    {
        /** @var \Laravel\Socialite\SocialiteManager $manager */
        $manager = $this->app->get(Factory::class);
        $manager->extend('congressus', function (Container $app) use ($manager) {
            $config = $app->make('config')->get('services.congressus');

            /** @var \DrowningElysium\LaravelSocialiteCongressus\CongressusProvider $provider */
            $provider = $manager->buildProvider(CongressusProvider::class, $config);
            $provider->setDomain($config['domain']);

            return $provider;
        });
    }
}
