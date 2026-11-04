<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Se crea recién cuando se usa, porque la librería exige GD o Imagick al construirse.
        $this->app->singleton(ImageHash::class, fn (): ImageHash => new ImageHash(new DifferenceHash));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
