<?php

namespace App\Providers;

use App\Events\TenantProvisioned;
use App\Listeners\HandleStripeCheckoutCompleted;
use App\Listeners\HandleStripeInvoicePaymentFailed;
use App\Listeners\HandleStripeSubscriptionDeleted;
use App\Listeners\HandleStripeSubscriptionUpdated;
use App\Listeners\SendTenantActivationEmail;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\ModuleObserver;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tenancy::class, fn () => new Tenancy);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Tenant::class);

        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureEventListeners();
        $this->configureObservers();
    }

    /**
     * Configure RBAC-based authorization via Gate.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdministrator() && $user->adminRole?->slug === 'super-admin') {
                return true;
            }

            if ($user->hasPermissionTo($ability)) {
                return true;
            }

            return null;
        });
    }

    /**
     * Configure event listeners for Stripe webhooks and tenant provisioning.
     */
    protected function configureEventListeners(): void
    {
        Event::listen(WebhookReceived::class, HandleStripeCheckoutCompleted::class);
        Event::listen(WebhookReceived::class, HandleStripeSubscriptionUpdated::class);
        Event::listen(WebhookReceived::class, HandleStripeSubscriptionDeleted::class);
        Event::listen(WebhookReceived::class, HandleStripeInvoicePaymentFailed::class);
        Event::listen(TenantProvisioned::class, SendTenantActivationEmail::class);
    }

    /**
     * Configure model observers.
     */
    protected function configureObservers(): void
    {
        Module::observe(ModuleObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
