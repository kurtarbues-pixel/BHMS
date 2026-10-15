<?php

namespace App\Console\Commands;

use App\Services\InvoiceGenerator;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate {period? : Billing period YYYY-MM}';

    protected $description = 'Generate monthly invoices (rent + pending charges). Safe to re-run.';

    public function handle(InvoiceGenerator $generator): int
    {
        $result = $generator->generate($this->argument('period'));
        $this->info("Period {$result['period']}: {$result['created']} created, {$result['updated']} updated.");

        return self::SUCCESS;
    }
}
