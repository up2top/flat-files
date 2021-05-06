<?php

namespace up2top\FlatFiles;

use Illuminate\Support\ServiceProvider;

class FlatFilesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            Console\Commands\LoadFlatContent::class,
        ]);
    }
}
