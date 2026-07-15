<?php

namespace App\Console\Commands;

use App\Services\ChallengeService;
use Illuminate\Console\Command;

class ResolveChallenges extends Command
{
    protected $signature = 'challenges:resolve';

    protected $description = 'Resolve challenges whose 48h arena voting window has closed.';

    public function handle(ChallengeService $service): int
    {
        $completed = $service->resolveDueChallenges();

        $this->info("Resolved {$completed} challenge(s).");

        return self::SUCCESS;
    }
}
