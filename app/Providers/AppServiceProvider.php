<?php

namespace App\Providers;

use App\Models\Agence;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\EcheanceCredit;
use App\Models\Reporting;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\AgencePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ComptePolicy;
use App\Policies\CreditPolicy;
use App\Policies\EcheanceCreditPolicy;
use App\Policies\ReportingPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        Gate::policy(Agence::class, AgencePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Compte::class, ComptePolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Credit::class, CreditPolicy::class);
        Gate::policy(EcheanceCredit::class, EcheanceCreditPolicy::class);
        Gate::policy(Reporting::class, ReportingPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}
