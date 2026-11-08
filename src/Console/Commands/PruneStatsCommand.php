<?php

declare(strict_types=1);

namespace Modules\LinkPage\Console\Commands;

use Illuminate\Console\Command;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\Contracts\LinkPageStatsInterface;

/**
 * Runs every night: the statistics keep the configured number of days and no more.
 */
final class PruneStatsCommand extends Command
{
    protected $signature = 'link-page:prune-stats {--days= : Days to keep (defaults to the retention set in the link page settings)}';

    protected $description = 'Delete the link page statistics older than the retention period';

    public function handle(LinkPageStatsInterface $stats, LinkPageSettingsInterface $settings): int
    {
        $option = $this->option('days');

        if ($option === null || $option === '') {
            $days = $settings->statsRetentionDays();
        } elseif (is_string($option) && ctype_digit($option)) {
            $days = (int) $option;
        } else {
            $this->error('The --days option must be a non-negative integer.');

            return self::FAILURE;
        }

        $this->info('Pruned: '.$stats->prune($days));

        return self::SUCCESS;
    }
}
