<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\AssetValidationDueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckAssetValidationDue extends Command
{
    protected $signature = 'assets:check-validation-due';

    protected $description = 'Notify admins when asset next_validation is within 5 working days or overdue';

    /**
     * Add working days to a date (excluding Saturday and Sunday).
     *
     * @param  \Carbon\Carbon  $date
     * @param  int  $days
     * @return \Carbon\Carbon
     */
    protected function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $d = $date->copy();
        $added = 0;
        while ($added < $days) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $added++;
            }
        }

        return $d;
    }

    /**
     * Check if a date is within the next N working days from today (inclusive) or in the past.
     *
     * @param  \Carbon\Carbon  $nextValidation
     * @param  int  $workingDays
     * @return bool
     */
    protected function isDueWithinWorkingDays(Carbon $nextValidation, int $workingDays = 5): bool
    {
        $today = Carbon::today();
        $limit = $this->addWorkingDays($today, $workingDays);
        $nextValidation->startOfDay();

        return $nextValidation->lte($limit);
    }

    public function handle()
    {
        $today = Carbon::today();
        $limit = $this->addWorkingDays($today, 5);

        $assets = Asset::whereNull('deleted_at')
            ->whereNotNull('next_validation')
            ->get()
            ->filter(function (Asset $asset) {
                return $this->isDueWithinWorkingDays($asset->next_validation->copy(), 5);
            });

        if ($assets->isEmpty()) {
            $this->info('No assets due for validation within the next 5 working days.');

            return 0;
        }

        $permission = Permission::where('name', 'assets')->first();
        if (! $permission) {
            $this->warn('Permission "assets" not found. No notifications sent.');

            return 0;
        }

        $roleIds = $permission->roles()->pluck('roles.id')->toArray();
        if (empty($roleIds)) {
            $this->warn('No roles have "assets" permission. No notifications sent.');

            return 0;
        }

        $users = User::whereNull('deleted_at')
            ->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            })
            ->get()
            ->unique('id');

        if ($users->isEmpty()) {
            $this->warn('No users with assets permission found. No notifications sent.');

            return 0;
        }

        foreach ($assets as $asset) {
            foreach ($users as $user) {
                $user->notify(new AssetValidationDueNotification($asset));
            }
        }

        $this->info(sprintf(
            'Sent %d notification(s) to %d user(s) for %d asset(s) due for validation.',
            $assets->count() * $users->count(),
            $users->count(),
            $assets->count()
        ));

        return 0;
    }
}
