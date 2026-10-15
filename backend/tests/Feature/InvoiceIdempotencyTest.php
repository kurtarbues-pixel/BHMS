<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\PendingCharge;
use App\Models\Room;
use App\Models\User;
use App\Services\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_invoice_generation_is_safe_to_rerun(): void
    {
        $landlord = User::factory()->landlord()->create();
        $tenant = User::factory()->tenant()->create();
        $room = Room::factory()->create(['landlord_id' => $landlord->id, 'price' => 5000]);

        $lease = Lease::create([
            'room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'monthly_rent' => 5000,
            'status' => 'active',
            'active_room_id' => $room->id,
        ]);

        PendingCharge::create([
            'lease_id' => $lease->id,
            'category' => 'CANTEEN',
            'description' => 'Meals',
            'amount' => 250.50,
            'status' => 'pending',
        ]);

        $generator = app(InvoiceGenerator::class);
        $period = now()->format('Y-m');

        $first = $generator->generate($period);
        $second = $generator->generate($period);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertEquals(1, $lease->invoices()->where('billing_period', $period)->count());

        $invoice = $lease->invoices()->first();
        $this->assertEquals('5250.50', $invoice->total_amount_due);
        $this->assertEquals(0, PendingCharge::where('status', 'pending')->count());
    }
}
