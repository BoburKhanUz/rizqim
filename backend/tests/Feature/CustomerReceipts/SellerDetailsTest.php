<?php

namespace Tests\Feature\CustomerReceipts;

use App\Domain\Receipts\SellerDetails;
use App\Models\AuditLog;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P (receipts): the seller's details a customer is shown before ordering (consumer law art. 28-1: name, address,
 * how to complain; VM 943: the supplier's legal address). The public store payload carries them as `store.seller`,
 * only these fields; the owner fills the legal address and the complaint contact in the partner panel.
 */
class SellerDetailsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function fillLegal(Store $store, array $values = []): void
    {
        DB::table('stores')->where('id', $store->id)->update($values + [
            'legal_name' => '"Lazzat Burger" MChJ',
            'tin' => '305123456',
            'tax_number' => '999888777',
            'bank_account' => '20208000900123456789',
            'legal_address' => 'Toshkent sh., Chilonzor t., Bunyodkor 1',
            'complaint_contact' => '@lazzat_support',
            'phone' => '+998901112233',
        ]);
    }

    private function catalogStore(): array
    {
        return $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/catalog?store_id=' . $this->store->uuid)
            ->assertOk()
            ->json('data.store');
    }

    public function test_the_public_store_payload_carries_the_seller_and_nothing_else_new(): void
    {
        $this->fillLegal($this->store);

        $store = $this->catalogStore();

        $this->assertSame([
            'legal_name' => '"Lazzat Burger" MChJ',
            'tin' => '305123456',
            'legal_address' => 'Toshkent sh., Chilonzor t., Bunyodkor 1',
            'phone' => '+998901112233',
            'complaint_contact' => '@lazzat_support',
        ], $store['seller']);

        // The raw columns stay out of the serialized store; so do the other internal ones
        foreach (['legal_name', 'tax_number', 'bank_account', 'legal_address', 'complaint_contact'] as $column) {
            $this->assertArrayNotHasKey($column, $store, $column);
        }
    }

    public function test_the_seller_falls_back_to_the_tax_number_and_leaves_missing_fields_empty(): void
    {
        $this->fillLegal($this->store, ['tin' => null, 'legal_address' => null, 'complaint_contact' => null, 'legal_name' => null]);

        $before = $this->catalogStore()['seller'];
        $this->assertSame([
            'legal_name' => null,
            'tin' => '999888777',
            'legal_address' => null,
            'phone' => '+998901112233',
            'complaint_contact' => null,
        ], $before);

        // A 14-digit JShShIR (a sole proprietor's personal ID) is never published: the 9-digit STIR or nothing
        // (stores.tin holds 9 characters; the older free-form tax_number may hold one)
        DB::table('stores')->where('id', $this->store->id)->update(['tin' => null, 'tax_number' => '41234567890123']);
        $this->assertNull($this->catalogStore()['seller']['tin']);
        $this->assertNull(SellerDetails::of($this->store->fresh())['tin']);
        // An order snapshot that holds one is not shown either
        $this->assertNull(SellerDetails::fromSnapshot(['tin' => '31234567890123'])['tin']);
        $this->assertSame('305123456', SellerDetails::fromSnapshot(['tin' => '305123456'])['tin']);
    }

    public function test_the_seller_follows_a_change_of_the_store(): void
    {
        $this->fillLegal($this->store);
        $this->assertSame('Toshkent sh., Chilonzor t., Bunyodkor 1', $this->catalogStore()['seller']['legal_address']);

        // The marketplace document is rebuilt when the store's row changes
        $this->store->refresh()->update(['legal_address' => 'Toshkent sh., Yunusobod t., 5']);

        $this->assertSame('Toshkent sh., Yunusobod t., 5', $this->catalogStore()['seller']['legal_address']);
    }

    private function settingsPayload(array $extra = []): array
    {
        return $extra + [
            'is_open' => true,
            'payment_settings' => ['cash' => ['enabled' => true]],
        ];
    }

    public function test_the_owner_sets_the_legal_address_and_complaint_contact_with_an_audit_entry(): void
    {
        $this->fillLegal($this->store, ['legal_address' => null, 'complaint_contact' => null]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v1/owner/settings', $this->settingsPayload([
                'legal_address' => '  Toshkent sh., Mirobod t., 7  ',
                'complaint_contact' => '+998 90 111 22 33',
                // Not the owner's to change: the legal name is checked when the store is admitted
                'legal_name' => 'Boshqa MChJ',
            ]))
            ->assertOk();

        $store = $this->store->fresh();
        $this->assertSame('Toshkent sh., Mirobod t., 7', $store->legal_address);
        $this->assertSame('+998 90 111 22 33', $store->complaint_contact);
        $this->assertSame('"Lazzat Burger" MChJ', $store->legal_name);

        $entry = AuditLog::where('action', 'store.seller_details_updated')->sole();
        $this->assertSame($this->store->id, (int) $entry->store_id);
        $this->assertSame($this->owner->id, (int) $entry->user_id);
        $this->assertSame(['legal_address' => null, 'complaint_contact' => null], $entry->before);
        // A phone number is masked in the log, as everywhere (AuditRedactor)
        $this->assertSame(['legal_address' => 'Toshkent sh., Mirobod t., 7', 'complaint_contact' => '+99890*****33'], $entry->after);

        $settings = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/settings')->assertOk()->json('data');
        $this->assertSame('Toshkent sh., Mirobod t., 7', $settings['legal_address']);
        $this->assertSame('+998 90 111 22 33', $settings['complaint_contact']);
        $this->assertSame('"Lazzat Burger" MChJ', $settings['legal_name']);
    }

    public function test_leaving_the_fields_out_keeps_them_and_writes_no_audit_entry(): void
    {
        $this->fillLegal($this->store);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $this->settingsPayload())->assertOk();

        $store = $this->store->fresh();
        $this->assertSame('Toshkent sh., Chilonzor t., Bunyodkor 1', $store->legal_address);
        $this->assertSame('@lazzat_support', $store->complaint_contact);
        $this->assertSame(0, AuditLog::where('action', 'store.seller_details_updated')->count());

        // An empty value clears it
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $this->settingsPayload(['complaint_contact' => '']))->assertOk();
        $this->assertNull($this->store->fresh()->complaint_contact);
    }

    public function test_overlong_values_are_refused_in_uzbek(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/v1/owner/settings', $this->settingsPayload([
                'legal_address' => str_repeat('a', 501),
                'complaint_contact' => str_repeat('b', 256),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_address', 'complaint_contact']);
    }
}
