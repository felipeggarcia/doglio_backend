<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Morph map — guarda alias curto no banco em vez do FQCN
        Relation::morphMap([
            'order' => Order::class,
        ]);

        Product::observe(ProductObserver::class);
    }
}
