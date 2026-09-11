<?php

namespace App\Console\Commands;

use App\Services\Calls\StuckCallRecovery;
use Illuminate\Console\Command;

class RecoverStuckCallsCommand extends Command
{
    protected $signature = 'sales:recover-stuck-calls
                            {--call= : Recover only this call id}
                            {--retry : Re-dispatch the missing job instead of only marking failed}
                            {--sync : Run a retried job in this process (requires --retry)}
                            {--minutes= : Minutes before processing/analyzing is considered stuck}';

    protected $description = 'Mark long-running processing/analyzing calls as failed when their queue job is gone';

    public function handle(StuckCallRecovery $recovery): int
    {
        $retry = (bool) $this->option('retry');
        $sync = (bool) $this->option('sync');

        if ($sync && ! $retry) {
            $this->error('--sync requires --retry.');

            return self::FAILURE;
        }

        $callId = $this->option('call');
        $minutes = $this->option('minutes');

        $actions = $recovery->recover(
            $callId !== null && $callId !== '' ? (int) $callId : null,
            $minutes !== null && $minutes !== ''
                ? (int) $minutes
                : (int) config('sales-analyzer.analysis.stuck_after_minutes', 30),
            $retry,
            $sync,
        );

        if ($actions === []) {
            $this->info('No stuck calls found.');

            return self::SUCCESS;
        }

        foreach ($actions as $action) {
            $this->line(sprintf(
                'Call #%d [%s] → %s',
                $action['id'],
                $action['status'],
                $action['action'],
            ));
        }

        $this->info('Recovered '.count($actions).' call(s).');

        return self::SUCCESS;
    }
}
