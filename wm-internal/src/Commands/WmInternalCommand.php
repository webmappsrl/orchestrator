<?php

namespace Wm\WmInternal\Commands;

use Illuminate\Console\Command;

class WmInternalCommand extends Command
{
    public $signature = 'wm-internal';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
