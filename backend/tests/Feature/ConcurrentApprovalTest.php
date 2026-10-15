<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PendingCharge;
use App\Models\Room;
use App\Models\User;
use App\Services\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConcurrentApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_approval_fails_when_room_is_taken(): void
    {
        Mail::fake();

        $landlord = User::factory()->landlord()->create();
        $tenantA = User::factory()->tenant()->create();
        $tenantB = User::factory()->tenant()->create();
        $room = Room::factory()->create(['landlord_id' => $landlord->id, 'status' => 'available']);

        $inquiryA = Inquiry::factory()->create([
            'room_id' => $room->id,
            'tenant_id' => $tenantA->id,
        ]);
        $inquiryB = Inquiry::factory()->create([
            'room_id' => $room->id,
            'tenant_id' => $tenantB->id,
        ]);

        Sanctum::actingAs($landlord);
        $this->postJson("/api/inquiries/{$inquiryA->id}/approve")->assertOk();

        $this->assertEquals(Inquiry::CONVERTED, $inquiryA->fresh()->status);
        $this->assertEquals(Inquiry::REJECTED, $inquiryB->fresh()->status);
        $this->assertEquals('occupied', $room->fresh()->status);
        $this->assertEquals(1, Lease::where('room_id', $room->id)->where('status', 'active')->count());

        $inquiryC = Inquiry::factory()->create([
            'room_id' => $room->id,
            'tenant_id' => $tenantB->id,
        ]);

        $this->postJson("/api/inquiries/{$inquiryC->id}/approve")
            ->assertStatus(409)
            ->assertJsonFragment(['code' => 'room_taken']);
    }
}
