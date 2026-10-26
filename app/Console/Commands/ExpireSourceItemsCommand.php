<?php

namespace App\Console\Commands;

use App\Models\SourceItem;
use Illuminate\Console\Command;

class ExpireSourceItemsCommand extends Command
{
    protected $signature = 'opportunities:expire';

    protected $description = 'Mark pending candidates whose deadline has passed as expired';

    public function handle(): int
    {
        $count = SourceItem::query()
            ->pending()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now('UTC'))
            ->update([
                'status' => SourceItem::STATUS_EXPIRED,
                'status_reason' => 'deadline_passed',
                'updated_at' => now('UTC'),
            ]);

        $this->info("Expired {$count} pending source item(s).");

        return self::SUCCESS;
    }
}
