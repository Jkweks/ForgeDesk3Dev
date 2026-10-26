<?php

namespace App\Console\Commands;

use App\Services\SpecialOrderReceivedNotifier;
use Illuminate\Console\Command;

class CheckSpecialOrderReservations extends Command
{
    protected $signature = 'reservations:check-special-orders';

    protected $description = 'Notify reservation watchers whose special-order items have all been received';

    public function handle(SpecialOrderReceivedNotifier $notifier): int
    {
        $this->info('Notified '.$notifier->check().' reservation(s).');

        return self::SUCCESS;
    }
}
