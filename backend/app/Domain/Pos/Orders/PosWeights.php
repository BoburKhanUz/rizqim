<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Support\Quantity;
use Illuminate\Validation\ValidationException;

/**
 * P6-25 (B3, module `weight_later`): "og'irlikni keyin kiritish" (docs/POS_SPEC.md "Og'irlikni keyin kiritish"). A dish
 * sold by the kg (a fish chosen from the ice) is added with an estimate and `weight_status = awaiting`; once it is
 * weighed the real kg is entered (PosOrderService::setWeight: who weighed and when, the price follows the weight). No
 * bill is made or printed while a line awaits its weight (BillService, 409 `weight_pending`); voiding the line frees
 * the bill. The receipt and the bill show the real weight ("Sazan (mangal) 1.35 kg × 245 000").
 *
 * A line awaits its weight when the request says so (`awaiting_weight: true`) or, by default, when its product has a
 * `weight_mode` (raw|cooked: which weight the kg price is for, kept on the line as `weight_basis`). Only kg/l dishes
 * that are not sold from a kazan batch can wait. The module gates the new lines only: an awaiting line is always
 * weighed or voided, whatever the module says later.
 */
class PosWeights
{
    /** The most one weighed line may hold (kg or l). */
    public const MAX_QUANTITY = '999';

    /** @var array<int, bool> branch id => module on */
    private array $enabled = [];

    public function __construct(
        private readonly PosModules $modules,
    ) {}

    /**
     * Whether the line waits for its weight, and on which basis; null: an ordinary line. Strict (online) refuses an
     * explicit request that cannot be kept (module off: 403 module_disabled; a piece dish or a kazan dish: 422);
     * lenient (offline sync) and the product's default just sell the line as it is.
     *
     * @param  array<string, mixed>  $line  a PosLineBuilder::parse() line (`awaiting_weight`: true|false|null)
     *
     * @throws ValidationException
     */
    public function basisFor(array $line, ProductVariant $variant, Branch $branch, bool $strict): ?WeightBasis
    {
        $explicit = $line['awaiting_weight'] ?? null;
        $mode = $variant->product?->weight_mode;
        $wanted = $explicit ?? ($mode !== null);
        if (!$wanted) {
            return null;
        }
        $refuse = $strict && $explicit === true;
        $index = (int) ($line['index'] ?? 0);

        $this->enabled[(int) $branch->id] ??= $this->modules->enabled($branch, PosModule::WEIGHT_LATER);
        if (!$this->enabled[(int) $branch->id]) {
            if ($refuse) {
                $this->modules->assertEnabled($branch, PosModule::WEIGHT_LATER);
            }

            return null;
        }

        $name = ($variant->product->name ?? '') . ($variant->name ? " ({$variant->name})" : '');
        if (!$variant->unit->isMeasured()) {
            if ($refuse) {
                throw ValidationException::withMessages(["items.{$index}.awaiting_weight" => "'{$name}' dona bilan sotiladi: og'irlikni keyin kiritib bo'lmaydi."]);
            }

            return null;
        }
        if ($variant->product?->batch_tracked) {
            if ($refuse) {
                throw ValidationException::withMessages(["items.{$index}.awaiting_weight" => "'{$name}' qozon partiyasidan sotiladi: og'irlikni keyin kiritib bo'lmaydi."]);
            }

            return null;
        }

        return WeightBasis::tryFrom((string) $mode) ?? WeightBasis::RAW;
    }

    /**
     * The weight a staff member typed: a positive kg/l amount with at most 3 decimals, at most MAX_QUANTITY.
     *
     * @throws ValidationException
     */
    public static function parseWeight(mixed $value): Quantity
    {
        $quantity = Quantity::tryOf($value);
        if ($quantity === null || !$quantity->isPositive() || Quantity::of(self::MAX_QUANTITY)->isLessThan($quantity)) {
            throw ValidationException::withMessages(['quantity' => "Og'irlik noto'g'ri: musbat son, ko'pi bilan 3 ta kasr xona (masalan 1.35)."]);
        }

        return $quantity;
    }
}
