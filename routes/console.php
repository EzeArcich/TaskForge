<?php

use App\Domain\Enums\PlanStatus;
use App\Jobs\DailyRunJob;
use App\Models\Plan;
use Illuminate\Support\Facades\Schedule;

// Daily routine: runs every day at 07:00 for all published plans
Schedule::call(function () {
    Plan::where('publish_status', PlanStatus::Published->value)
        ->each(function (Plan $plan) {
            DailyRunJob::dispatch($plan->id);
        });
})->dailyAt('07:00')
  ->name('dailypro:daily-run')
  ->withoutOverlapping();
