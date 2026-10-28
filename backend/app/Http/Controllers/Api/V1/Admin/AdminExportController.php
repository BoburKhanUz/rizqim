<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Export\Datasets\ApplicationsExport;
use App\Services\Admin\Export\Datasets\AuditLogsExport;
use App\Services\Admin\Export\Datasets\BalancesExport;
use App\Services\Admin\Export\Datasets\DevicesExport;
use App\Services\Admin\Export\Datasets\InvoicesExport;
use App\Services\Admin\Export\Datasets\OrdersExport;
use App\Services\Admin\Export\Datasets\PayoutsExport;
use App\Services\Admin\Export\Datasets\StoresExport;
use App\Services\Admin\Export\Datasets\TopupsExport;
use App\Services\Admin\Export\ExportDataset;
use App\Services\Admin\Export\XlsxExporter;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P4-31: Excel export of the lists. Every endpoint needs admin.export AND the list's own view permission, takes the same
 * filters as the list (no paging), answers an .xlsx download (XlsxExporter: streamed, at most 50 000 rows, texts as text,
 * phones masked unless admin.pii.view) and records the export in the audit log (admin.exports.<list>). At most
 * `admin.export.per_minute` exports a minute per member (429).
 *
 * The headers `X-Export-Rows` and `X-Export-Truncated` (`1` when the list was longer than the cap) say what the file holds.
 */
#[Group('Admin')]
class AdminExportController extends AdminController
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private readonly XlsxExporter $exporter,
    ) {}

    /**
     * Export orders
     *
     * The filters of GET /admin/orders: `status` (group), `channel`, `region_id`, `store_id`, `sla`, `from`, `to`, `q`.
     * Needs admin.export and admin.orders.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Buyurtmalar" and the info sheet "Ma\'lumot"', type: 'string')]
    public function orders(Request $request, OrdersExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export stores
     *
     * The filters of GET /admin/stores: `q`, `status`, `region_id`, `health`, `plan`, `debt`, `next_contact`. Needs
     * admin.export and admin.stores.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Ovqatxonalar" and the info sheet', type: 'string')]
    public function stores(Request $request, StoresExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export store applications
     *
     * The filters of GET /admin/store-applications: `stage` (also `open`, `overdue`), `assignee_id` (or `me`), `source`,
     * `q`. Needs admin.export and admin.stores.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Arizalar" and the info sheet', type: 'string')]
    public function applications(Request $request, ApplicationsExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export payout requests
     *
     * The filters of GET /admin/finance/payouts: `status` (default `open`), `store_id`. Cards are always masked. Needs
     * admin.export and admin.finance.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Payoutlar" and the info sheet', type: 'string')]
    public function payouts(Request $request, PayoutsExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export balance top-ups
     *
     * The filters of GET /admin/finance/topups: `status`, `provider`, `store_id`, `stuck`. Needs admin.export and
     * admin.finance.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Balans to\'ldirishlar" and the info sheet', type: 'string')]
    public function topups(Request $request, TopupsExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export store balances and debts
     *
     * The filters of GET /admin/finance/balances: `filter`, `q`. Needs admin.export and admin.finance.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Balanslar" and the info sheet', type: 'string')]
    public function balances(Request $request, BalancesExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export subscription invoices
     *
     * The filters of GET /admin/pos/invoices: `status`, `store_id`, `period`, `dunning`, `q`. Needs admin.export and
     * admin.subscriptions.manage or admin.finance.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Hisob-fakturalar" and the info sheet', type: 'string')]
    public function invoices(Request $request, InvoicesExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export devices
     *
     * The filters of GET /admin/pos/devices: `state`, `type`, `terminal`, `store_id`, `outdated`, `q`. Needs
     * admin.export and admin.devices.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Qurilmalar" and the info sheet', type: 'string')]
    public function devices(Request $request, DevicesExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    /**
     * Export the audit log
     *
     * The filters of GET /admin/audit-logs: `actor_id`, `action`, `store_id`, `subject_type`, `subject_id`, `from`, `to`,
     * `q`. Needs admin.export and admin.audit.view.
     */
    #[ApiDocResponse(200, 'An .xlsx file: sheet "Jurnal" and the info sheet', type: 'string')]
    public function auditLogs(Request $request, AuditLogsExport $dataset): BinaryFileResponse
    {
        return $this->download($request, $dataset);
    }

    private function download(Request $request, ExportDataset $dataset): BinaryFileResponse
    {
        $filters = $request->validate($dataset->rules(), UzValidation::messages());
        $file = $this->exporter->export($dataset, $filters, $this->member($request), $request);

        return response()->download($file->path, $file->name, [
            'Content-Type' => self::XLSX,
            'Cache-Control' => 'private, no-store',
            'X-Export-Rows' => (string) $file->rows,
            'X-Export-Truncated' => $file->truncated ? '1' : '0',
        ])->deleteFileAfterSend();
    }
}
