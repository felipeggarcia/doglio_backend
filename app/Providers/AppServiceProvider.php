<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\UserAddress;
use App\Models\UserFavorite;
use App\Observers\ProductObserver;
use App\Policies\OrderPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserAddressPolicy;
use App\Policies\UserFavoritePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Policies
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(UserAddress::class, UserAddressPolicy::class);
        Gate::policy(UserFavorite::class, UserFavoritePolicy::class);

        // Rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('public', function (Request $request) {
            // Rotas públicas: 200/min por IP
            return Limit::perMinute(200)->by($request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            // Login/register: 10 tentativas por minuto por IP
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
