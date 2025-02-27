<?php declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as KernelVendor;
use App\Domains\CoreMaintenance\Schedule\Manager as CoreMaintenanceScheduleManager;
use App\Domains\Position\Schedule\Manager as PositionScheduleManager;
use App\Domains\Refuel\Schedule\Manager as RefuelScheduleManager;
use App\Domains\Server\Schedule\Manager as ServerScheduleManager;

class Kernel extends KernelVendor
{
    /**
     * @return void
     */
    protected function commands(): void
    {
        foreach (glob(app_path('Domains/*/Command')) as $dir) {
            $this->load($dir);
        }
    }

    /**
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $this->scheduleQueue($schedule);
        $this->scheduleCachePrune($schedule);

        (new ServerScheduleManager($schedule))->handle();
        (new PositionScheduleManager($schedule))->handle();
        (new RefuelScheduleManager($schedule))->handle();
        (new CoreMaintenanceScheduleManager($schedule))->handle();
    }

    /**
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function scheduleQueue(Schedule $schedule): void
    {
        if (config('queue.schedule') !== true) {
            return;
        }

        $schedule->command('queue:work', ['--tries' => 3, '--max-time' => 3600])
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($this->log())
            ->everyMinute();
    }

    /**
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function scheduleCachePrune(Schedule $schedule): void
    {
        $schedule->command('cache:prune-stale-tags')->hourly();
    }

    /**
     * @return string
     */
    protected function log(): string
    {
        $file = storage_path('logs/artisan/'.date('Y/m/d').'/schedule-command-queue-work.log');

        helper()->mkdir($file, true);

        return $file;
    }
}
