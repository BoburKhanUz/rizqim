<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\SetupPresenter;
use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Enums\PrinterConnection;
use App\Enums\PrinterPurpose;
use App\Models\Branch;
use App\Models\Device;
use App\Models\KitchenStation;
use App\Models\Printer;
use App\Services\Audit\AuditLogger;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P3-13: the printers of a branch (receipt, kitchen, label), for the partner web panel (settings.manage; never from a
 * terminal PIN session). The terminals read them from GET /pos/bootstrap and print themselves.
 */
#[Group('Owner: printers', "Filial printerlari: chek, oshxona markasi, yorliq; LAN, USB, ichki, Bluetooth (P3-13).")]
class PrinterController extends ManagementController
{
    /** Paper widths in mm (integers: the API document types them as such). */
    public const PAPER_WIDTHS = [58, 80];

    private const PRINTER = 'array{id: int, branch_id: int, name: string, purpose: string, connection: string, address: string|null, port: int|null, paper_width: int, station_id: int|null, device_id: string|null, is_active: bool, encoding: string|null, print_as_image: bool}';

    /**
     * The branch's printers
     *
     * By `branch_id` (a branch-bound caller: their own); without it, every branch of the store. `device_id` is the
     * terminal's public uuid (a USB or built-in printer belongs to one terminal).
     */
    #[QueryParameter('branch_id', description: 'Only this branch.', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::PRINTER . '>}')]
    public function index(Request $request): JsonResponse
    {
        $branch = $this->branchFilter($request, Permission::SETTINGS_MANAGE);

        $printers = Printer::query()
            ->with('device:id,uuid')
            ->where('store_id', $this->storeId($request))
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->orderBy('branch_id')->orderBy('id')
            ->get();

        return $this->success($printers->map(fn (Printer $printer) => SetupPresenter::printer($printer))->values());
    }

    /**
     * Add a printer
     *
     * `purpose`: receipt, kitchen or label. `connection`: lan (needs `address`, `port` defaults to 9100), bluetooth
     * (needs `address`), usb or builtin (need `device_id`: the uuid of the POS terminal it is attached to; no
     * address). `paper_width`: 58 or 80 mm. `station_id` (a station of the same branch) only for kitchen and label
     * printers. `encoding`: the code page when the printer's fonts need one; `print_as_image`: print the text as a
     * picture. `name` is unique within the branch.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::PRINTER . '}')]
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        // P3-26: the whole body first (so the API document lists it), then the name's uniqueness in the branch
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'name' => ['required', 'string', 'max:100'],
            'purpose' => ['sometimes', 'string', 'in:receipt,kitchen,label'],
            'connection' => ['required', 'string', 'in:lan,usb,builtin,bluetooth'],
            'address' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['sometimes', 'integer', Rule::in(self::PAPER_WIDTHS)],
            'station_id' => ['nullable', 'integer', 'min:1', 'max:999999999999999999'],
            'device_id' => ['nullable', 'uuid'],
            'is_active' => ['sometimes', 'boolean'],
            'encoding' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'print_as_image' => ['sometimes', 'boolean'],
        ], UzValidation::messages($this->messages()), $this->attributes());
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $request->validate([
            'name' => [Rule::unique('printers', 'name')->where('branch_id', $branch->id)],
        ], UzValidation::messages($this->messages()), $this->attributes());

        $fields = $this->checked($branch, collect($data)->except('branch_id')->all(), null);
        $printer = Printer::create(['store_id' => $branch->store_id, 'branch_id' => $branch->id] + $fields);
        $printer->refresh()->load('device:id,uuid');
        $audit->record('pos.printer_created', $printer, [], SetupPresenter::printer($printer));

        return $this->success(SetupPresenter::printer($printer), "Printer qo'shildi.", 201);
    }

    /**
     * Edit a printer
     *
     * Any subset of the create fields (the branch cannot change). The connection rules apply to the result:
     * switching to usb or builtin needs `device_id`, to lan an `address`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::PRINTER . '}')]
    public function update(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $printer = $this->printerOf($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('printers', 'name')->where('branch_id', $printer->branch_id)->ignore($printer->id)],
            'purpose' => ['sometimes', 'string', 'in:receipt,kitchen,label'],
            'connection' => ['sometimes', 'string', 'in:lan,usb,builtin,bluetooth'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['sometimes', 'integer', Rule::in(self::PAPER_WIDTHS)],
            'station_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999'],
            'device_id' => ['sometimes', 'nullable', 'uuid'],
            'is_active' => ['sometimes', 'boolean'],
            'encoding' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'print_as_image' => ['sometimes', 'boolean'],
        ], UzValidation::messages($this->messages()), $this->attributes());

        $branch = Branch::findOrFail($printer->branch_id);
        $before = SetupPresenter::printer($printer->load('device:id,uuid'));
        $printer->update($this->checked($branch, $data, $printer));
        $printer->refresh()->load('device:id,uuid');
        $audit->record('pos.printer_updated', $printer, $before, SetupPresenter::printer($printer));

        return $this->success(SetupPresenter::printer($printer), 'Printer saqlandi.');
    }

    /**
     * Delete a printer
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $printer = $this->printerOf($request, $id);

        $before = SetupPresenter::printer($printer->load('device:id,uuid'));
        $printer->delete();
        $audit->record('pos.printer_deleted', $printer, $before, []);

        return $this->success(null, "Printer o'chirildi.");
    }

    /**
     * The fields to save: the request's, resolved against what the printer would be after the change (its current
     * values for what was not sent) and checked for the connection kind, the station and the terminal.
     *
     * @param  array<string, mixed>  $data  validated input
     * @return array<string, mixed>
     */
    private function checked(Branch $branch, array $data, ?Printer $current): array
    {
        $connection = PrinterConnection::from((string) ($data['connection'] ?? $current?->connection->value));
        $purpose = PrinterPurpose::from((string) ($data['purpose'] ?? $current?->purpose->value ?? PrinterPurpose::RECEIPT->value));
        $address = array_key_exists('address', $data) ? $data['address'] : $current?->address;
        $port = array_key_exists('port', $data) ? $data['port'] : $current?->port;
        $stationId = array_key_exists('station_id', $data) ? $data['station_id'] : $current?->station_id;

        if ($connection->isDeviceBound()) {
            if (array_key_exists('device_id', $data) && $data['device_id'] !== null) {
                $data['device_id'] = $this->terminalId($branch, (string) $data['device_id']);
            } elseif (array_key_exists('device_id', $data) || $current?->device_id === null || !$current->connection->isDeviceBound()) {
                throw ValidationException::withMessages(['device_id' => 'USB yoki ichki printer uchun terminalni tanlang (device_id).']);
            }
            $data['address'] = null;
            $data['port'] = null;
        } else {
            if ($address === null || $address === '') {
                throw ValidationException::withMessages(['address' => $connection === PrinterConnection::LAN ? 'LAN printer manzili (IP yoki nomi) majburiy.' : 'Bluetooth printer manzili majburiy.']);
            }
            $data['address'] = $address;
            $data['port'] = $connection === PrinterConnection::LAN ? ($port ?? 9100) : null;
            // Reached by address from any terminal: tied to none
            $data['device_id'] = null;
        }

        if ($stationId !== null) {
            if ($purpose === PrinterPurpose::RECEIPT) {
                throw ValidationException::withMessages(['station_id' => "Sexga faqat oshxona markasi yoki yorliq printeri biriktiriladi."]);
            }
            if (!KitchenStation::where('branch_id', $branch->id)->whereKey((int) $stationId)->exists()) {
                throw ValidationException::withMessages(['station_id' => "Sex topilmadi (u shu filialniki bo'lishi kerak)."]);
            }
        }
        // Switching to a receipt printer drops its station
        $data['station_id'] = $purpose === PrinterPurpose::RECEIPT ? null : $stationId;

        return $data;
    }

    /**
     * The numeric id of the store's POS terminal with this uuid, which must work this branch (its own, or paired to the
     * whole store).
     */
    private function terminalId(Branch $branch, string $uuid): int
    {
        $device = Device::query()
            ->where('uuid', $uuid)
            ->where('store_id', $branch->store_id)
            ->where('type', DeviceType::POS->value)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->first();

        if (!$device instanceof Device) {
            throw ValidationException::withMessages(['device_id' => 'Kassa terminali topilmadi (u shu filialniki bo\'lishi kerak).']);
        }

        return (int) $device->id;
    }

    private function printerOf(Request $request, int $id): Printer
    {
        $printer = Printer::where('store_id', $this->storeId($request))->find($id);
        if (!$printer instanceof Printer) {
            throw new HttpResponseException($this->error('Printer topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $printer);

        return $printer;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.unique' => 'Bu filialda bunday nomli printer bor.',
            'address.regex' => "Manzil faqat harf, raqam, nuqta, ikki nuqta va chiziqchadan iborat bo'lishi kerak.",
            'paper_width.in' => "Qog'oz kengligi 58 yoki 80 mm bo'lishi kerak.",
            'encoding.regex' => "Kodlash nomi noto'g'ri.",
            'device_id.uuid' => "Terminal identifikatori noto'g'ri.",
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return ['name' => 'Nomi', 'purpose' => 'Vazifasi', 'connection' => 'Ulanish turi', 'address' => 'Manzil', 'port' => 'Port', 'paper_width' => "Qog'oz kengligi", 'station_id' => 'Sex', 'device_id' => 'Terminal', 'encoding' => 'Kodlash'];
    }
}
