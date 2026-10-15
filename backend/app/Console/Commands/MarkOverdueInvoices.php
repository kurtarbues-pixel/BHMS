<?php

namespace App\Console\Commands;

use App\Services\InvoiceGenerator;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark unpaid invoices past their due date as overdue.';

    public function handle(InvoiceGenerator $generator): int
    {
        $count = $generator->markOverdue();
        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
