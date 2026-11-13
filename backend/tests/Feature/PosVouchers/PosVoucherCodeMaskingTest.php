<?php

namespace Tests\Feature\PosVouchers;

use App\Domain\Pos\Vouchers\VoucherCode;
use App\Enums\UserRole;
use App\Models\RolePermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Review S1 (P6 wave 4): a certificate code is the money (typed at a till, no PIN), so a reader with reports.view but
 * not vouchers.manage gets it masked (VoucherCode::mask) in the owner's list, one certificate's view and the Excel
 * export, with no `print` block, and finds a certificate by its whole code only; vouchers.manage and the till's
 * lookup of a typed code keep the full code.
 */
class PosVoucherCodeMaskingTest extends TestCase
{
    use PosVouchersFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpVouchers();
    }

    public function test_a_reports_only_reader_gets_masked_codes_and_the_manager_full_ones(): void
    {
        $this->openShift();
        $sold = $this->sell('100000', 'cash', ['buyer_name' => 'Aziz'])->assertCreated()->assertJsonPath('data.print.code', fn ($code) => $code !== null)->json('data');
        $code = $sold['code'];
        $masked = VoucherCode::mask($code);
        $this->assertStringNotContainsString(substr($code, 5, 4), $masked);

        // The owner lets cashiers read reports in the partner panel
        RolePermissionOverride::create(['store_id' => $this->store->id, 'role' => UserRole::CASHIER->value, 'permission' => 'reports.view', 'allowed' => true]);

        $this->owner($this->cashier, 'GET', '/vouchers')->assertOk()
            ->assertJsonPath('data.vouchers.0.code', $masked)
            ->assertJsonPath('data.vouchers.0.print', null);
        $this->owner($this->cashier, 'GET', "/vouchers/{$sold['uuid']}")->assertOk()
            ->assertJsonPath('data.voucher.code', $masked)
            ->assertJsonPath('data.voucher.print', null);
        // A part of the code finds nothing (no guessing it group by group); the whole code still finds it
        $this->owner($this->cashier, 'GET', '/vouchers?q=' . substr($code, 0, 6))->assertOk()->assertJsonCount(0, 'data.vouchers');
        $this->owner($this->cashier, 'GET', '/vouchers?q=' . $code)->assertOk()->assertJsonCount(1, 'data.vouchers');
        $this->assertSame($masked, $this->exportedCode($this->cashier));

        // vouchers.manage sees the full code and the slip
        $this->owner($this->owner, 'GET', '/vouchers')->assertOk()
            ->assertJsonPath('data.vouchers.0.code', $code)
            ->assertJsonPath('data.vouchers.0.print.code', $code);
        $this->owner($this->owner, 'GET', "/vouchers/{$sold['uuid']}")->assertOk()->assertJsonPath('data.voucher.print.qr', $code);
        $this->owner($this->owner, 'GET', '/vouchers?q=' . substr($code, 0, 6))->assertOk()->assertJsonCount(1, 'data.vouchers');
        $this->assertSame($code, $this->exportedCode($this->owner));

        // The till's lookup of a typed code keeps it whole
        $this->pos('GET', '/vouchers/' . $code)->assertOk()->assertJsonPath('data.voucher.code', $code);
    }

    private function exportedCode(\App\Models\User $user): string
    {
        $file = $this->owner($user, 'GET', '/vouchers/export')->assertOk()->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);
        $reader = new Reader();
        $reader->open($file->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        return (string) $rows[1][0];
    }
}
