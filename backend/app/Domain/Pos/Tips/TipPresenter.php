<?php

namespace App\Domain\Pos\Tips;

use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * P6-22: the JSON of tips (amounts as decimal strings). `amount` is what the tip gives now (the tip and its
 * corrections), `original_amount` what was taken; `mode`/`staff` whom it goes to now (several holders after a
 * split correction: `holders`).
 */
final class TipPresenter
{
    public const HOLDER = 'array{mode: string, staff: array{id: int, name: string|null}|null, amount: string}';

    public const CORRECTION = 'array{amount: string, mode: string, staff_id: int|null, reason: string|null, by: array{id: int, name: string|null}|null, approved_by: int|null, created_at: string|null}';

    public const TIP = 'array{uuid: string, method: string, source: string|null, amount: string, original_amount: string, mode: string, staff: array{id: int, name: string|null}|null, holders: list<' . self::HOLDER . '>, business_date: string, order_uuid: string|null, bill_uuid: string|null, payment_uuid: string|null, shift_uuid: string|null, recorded_by: array{id: int, name: string|null}|null, closed: bool, corrections: list<' . self::CORRECTION . '>, created_at: string|null}';

    /**
     * @param  Collection<int, PosTip>  $tips  `kind` tip rows
     * @return list<array<string, mixed>>
     */
    public function many(Collection $tips): array
    {
        if ($tips->isEmpty()) {
            return [];
        }
        $corrections = PosTip::whereIn('corrects_id', $tips->pluck('id'))->orderBy('id')->get()->groupBy('corrects_id');
        $people = User::whereIn('id', $tips->pluck('staff_id')->merge($tips->pluck('recorded_by'))
            ->merge($corrections->flatten()->pluck('staff_id'))->merge($corrections->flatten()->pluck('recorded_by'))->filter()->unique())
            ->pluck('name', 'id');
        $orders = Order::whereIn('id', $tips->pluck('order_id')->filter())->pluck('uuid', 'id');
        $bills = OrderBill::whereIn('id', $tips->pluck('bill_id')->filter())->pluck('uuid', 'id');
        $payments = Payment::whereIn('id', $tips->pluck('payment_id')->filter())->pluck('uuid', 'id');
        $shifts = PosShift::whereIn('id', $tips->pluck('shift_id')->filter())->pluck('uuid', 'id');
        $person = fn (?int $id) => $id !== null ? ['id' => $id, 'name' => $people[$id] ?? null] : null;

        return $tips->map(function (PosTip $tip) use ($corrections, $person, $orders, $bills, $payments, $shifts) {
            /** @var Collection<int, PosTip> $own */
            $own = $corrections->get($tip->id, collect());
            $groups = [];
            foreach (collect([$tip])->merge($own) as $row) {
                $key = $row->mode->value . ':' . ($row->staff_id ?? 0);
                $groups[$key] ??= ['mode' => $row->mode->value, 'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null, 'amount' => Money::zero()];
                $groups[$key]['amount'] = $groups[$key]['amount']->plus($row->amount);
            }
            $holders = array_values(array_filter($groups, fn (array $group) => !$group['amount']->isZero()));
            $main = $holders[0] ?? ['mode' => $tip->mode->value, 'staff_id' => $tip->staff_id !== null ? (int) $tip->staff_id : null];

            return [
                'uuid' => $tip->uuid,
                'method' => $tip->method->value,
                'source' => $tip->source?->value,
                'amount' => Money::sum('0', $tip->amount, ...$own->pluck('amount')->all())->toString(),
                'original_amount' => Money::of($tip->amount)->toString(),
                'mode' => $main['mode'],
                'staff' => $person($main['staff_id']),
                'holders' => array_map(fn (array $holder) => ['mode' => $holder['mode'], 'staff' => $person($holder['staff_id']), 'amount' => $holder['amount']->toString()], $holders),
                'business_date' => $tip->business_date->toDateString(),
                'order_uuid' => $tip->order_id !== null ? ($orders[$tip->order_id] ?? null) : null,
                'bill_uuid' => $tip->bill_id !== null ? ($bills[$tip->bill_id] ?? null) : null,
                'payment_uuid' => $tip->payment_id !== null ? ($payments[$tip->payment_id] ?? null) : null,
                'shift_uuid' => $tip->shift_id !== null ? ($shifts[$tip->shift_id] ?? null) : null,
                'recorded_by' => $person($tip->recorded_by),
                'closed' => TipService::closedFor($tip),
                'corrections' => $own->map(fn (PosTip $row) => [
                    'amount' => Money::of($row->amount)->toString(),
                    'mode' => $row->mode->value,
                    'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null,
                    'reason' => $row->reason,
                    'by' => $person($row->recorded_by),
                    'approved_by' => $row->approved_by !== null ? (int) $row->approved_by : null,
                    'created_at' => $row->created_at?->toIso8601String(),
                ])->values()->all(),
                'created_at' => $tip->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function one(PosTip $tip): array
    {
        return $this->many(collect([$tip]))[0];
    }

    /**
     * The tips of a bill (what the guest gave on top of it).
     *
     * @return list<array<string, mixed>>
     */
    public function ofBill(int $billId): array
    {
        return $this->many(PosTip::where('bill_id', $billId)->where('kind', TipKind::TIP->value)->orderBy('id')->get());
    }

    /**
     * Σ of a bill's tips now (corrections included).
     */
    public static function billTotal(int $billId): Money
    {
        return Money::sum('0', ...PosTip::where('bill_id', $billId)->pluck('amount')->all());
    }

    /**
     * The tip taken with a payment, as taken (corrections are not the guest's money).
     */
    public static function paymentTip(int $paymentId): Money
    {
        return Money::sum('0', ...PosTip::where('payment_id', $paymentId)->where('kind', TipKind::TIP->value)->pluck('amount')->all());
    }
}
