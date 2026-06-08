<?php

namespace CleaniqueCoders\ConfigWebhook\Commands;

use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use Illuminate\Console\Command;

class PruneWebhookDeliveryLogsCommand extends Command
{
    public $signature = 'config-webhook:prune
        {--days=30 : Delete delivery logs older than this many days}';

    public $description = 'Prune old webhook delivery logs';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = WebhookDeliveryLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} webhook delivery log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
