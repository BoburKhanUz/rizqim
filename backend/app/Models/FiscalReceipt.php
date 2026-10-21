<?php

namespace App\Models;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssueResult;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalSnapshot;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A receipt the store issued (P3-11, App\Domain\Fiscal\FiscalService): the lines and payments as sent, the provider's
 * answer (fiscal sign, receipt number, QR link) and the retry state. Written only through FiscalIssuer.
 *
 * @property int $id
 * @property string $uuid
 * @property int $store_id
 * @property int|null $branch_id
 * @property int|null $device_id
 * @property FiscalReceiptType $type
 * @property FiscalPaymentForm $payment_form
 * @property FiscalReceiptStatus $status
 * @property string $driver sandbox | null | … | external (registered by a payment provider)
 * @property string|null $provider payme | click for external receipts
 * @property string $idempotency_key
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $tax_regime
 * @property numeric-string $total
 * @property numeric-string $vat_total
 * @property list<array<string, mixed>> $lines
 * @property list<array<string, mixed>> $payments
 * @property int|null $original_receipt_id
 * @property bool $non_fiscal
 * @property string|null $fiscal_sign
 * @property string|null $receipt_number
 * @property string|null $qr_url
 * @property string|null $terminal_id
 * @property array<string, mixed>|null $provider_payload
 * @property string|null $error
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $next_attempt_at
 * @property \Illuminate\Support\Carbon|null $issued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read FiscalReceipt|null $originalReceipt
 * @property-read Store|null $store
 */
class FiscalReceipt extends Model
{
    use \App\Traits\HasPublicUuid;

    protected $guarded = ['id'];

    protected $hidden = ['provider_payload'];

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'non_fiscal' => false,
        'vat_total' => '0.00',
    ];

    protected function casts(): array
    {
        return [
            'type' => FiscalReceiptType::class,
            'payment_form' => FiscalPaymentForm::class,
            'status' => FiscalReceiptStatus::class,
            'total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'lines' => 'array',
            'payments' => 'array',
            'non_fiscal' => 'boolean',
            'provider_payload' => 'array',
            'attempts' => 'integer',
            'subject_id' => 'integer',
            'next_attempt_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * The sale a refund (or a credit receipt) belongs to, or the advance a sale offsets.
     *
     * @return BelongsTo<FiscalReceipt, $this>
     */
    public function originalReceipt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_receipt_id');
    }

    /**
     * Receipts that point at this one (refunds and credit receipts of a sale).
     *
     * @return HasMany<FiscalReceipt, $this>
     */
    public function linkedReceipts(): HasMany
    {
        return $this->hasMany(self::class, 'original_receipt_id');
    }

    /**
     * @param  Builder<FiscalReceipt>  $query
     * @return Builder<FiscalReceipt>
     */
    public function scopeForSubject(Builder $query, string $subjectType, int $subjectId): Builder
    {
        return $query->where('subject_type', $subjectType)->where('subject_id', $subjectId);
    }

    /**
     * Pending or failed: the sale has no registered receipt yet ("fiskal kutmoqda" / needs the owner).
     *
     * @param  Builder<FiscalReceipt>  $query
     * @return Builder<FiscalReceipt>
     */
    public function scopeUnissued(Builder $query): Builder
    {
        return $query->whereIn('status', [FiscalReceiptStatus::PENDING->value, FiscalReceiptStatus::FAILED->value]);
    }

    /**
     * The document as it was first sent, for a retry.
     */
    public function toDocument(): FiscalDocument
    {
        return new FiscalDocument(
            type: $this->type,
            storeId: (int) $this->store_id,
            branchId: $this->branch_id !== null ? (int) $this->branch_id : null,
            deviceId: $this->device_id !== null ? (int) $this->device_id : null,
            lines: array_map(fn (array $row) => FiscalSnapshot::line($row), $this->lines ?? []),
            payments: array_map(fn (array $row) => FiscalSnapshot::payment($row), $this->payments ?? []),
            total: Money::of($this->total),
            idempotencyKey: $this->idempotency_key,
            originalReceiptUuid: $this->originalReceipt?->uuid,
            subjectType: $this->subject_type,
            subjectId: $this->subject_id,
        );
    }

    public function toResult(): FiscalIssueResult
    {
        return new FiscalIssueResult(
            receiptUuid: $this->uuid,
            status: $this->status->value,
            fiscalSign: $this->fiscal_sign,
            qrUrl: $this->qr_url,
            error: $this->error,
        );
    }
}
