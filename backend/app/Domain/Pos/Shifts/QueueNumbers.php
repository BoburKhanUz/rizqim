<?php

namespace App\Domain\Pos\Shifts;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\DeviceType;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\PosQueueBlock;
use Illuminate\Support\Facades\DB;

/**
 * P6-13 (A2, POS_SPEC 2.3 "Navbat raqami"): queue numbers of a branch, restarting from 1 every business day.
 *
 * With the `queue_numbers` module on, numbers come out of blocks (pos_queue_blocks): every till (a `pos` device)
 * takes a block of BLOCK_SIZE numbers from the server (in GET /pos/bootstrap or POST /pos/queue/blocks) and numbers
 * its orders from it, online and offline, so two tills never print the same number even without internet. The
 * branch's own block (no device) numbers the Telegram pickup orders and the orders opened without a till (a waiter
 * phone). Blocks are cut one after another under the branch and day lock, above every block and number of that day,
 * so the server never hands two tills the same block. A number a till printed offline (or proposes online) is taken
 * when it lies in one of that till's blocks of the order's business day and no order of the day has it; otherwise
 * the order gets the next number and the caller flags it (`queue_number_taken`).
 *
 * Without the module (the choyxona default) every POS order keeps the P3-18 numbering: the next number of the branch
 * and day (above any block of that day, should the module have been on earlier), nothing reserved; a number the
 * client proposes is ignored.
 *
 * Every number is taken under one advisory lock per branch and business day held until the transaction commits, and
 * orders.queue_number is unique per branch and business day.
 */
final class QueueNumbers
{
    /** Numbers in one block. */
    public const BLOCK_SIZE = 100;

    /** A till holding fewer unused numbers than this gets its next block (bootstrap, POST /pos/queue/blocks). */
    public const LOW_WATER = 20;

    public static function numbersOn(Branch $branch): bool
    {
        return app(PosModules::class)->enabled($branch, PosModule::from('queue_numbers'));
    }

    public static function callingOn(Branch $branch): bool
    {
        return app(PosModules::class)->enabled($branch, PosModule::from('queue_calling'));
    }

    /**
     * Numbers an order of $date (an order being created, OrderStamp; an online order entering the queue). A number
     * already set on it is the one its till proposes (printed offline or picked online): kept when allowed (with the
     * module; without it, ignored). Returns the proposed number when it was refused (the order got another), else
     * null. Call inside a transaction.
     */
    public function number(Order $order, Branch $branch, string $date): ?int
    {
        $this->lock($branch, $date);

        $proposed = $order->getAttribute('queue_number');
        $proposed = $proposed !== null ? (int) $proposed : null;
        $device = $this->tillOf($order, $branch);

        if (!self::numbersOn($branch)) {
            // Review P6 C#L4: no blocks, no talon: a number the client proposes is ignored (it could push the day's
            // numbering anywhere), the order gets the next one of the day
            $order->setAttribute('queue_number', $this->top($branch, $date) + 1);

            return null;
        }

        if ($proposed !== null && $this->take($branch, $date, $device, $proposed)) {
            return null;
        }
        $order->setAttribute('queue_number', $this->next($branch, $date, $device));

        return $proposed;
    }

    /**
     * The till's blocks of today as GET /pos/bootstrap and POST /pos/queue/blocks show them; with $reserve a till
     * (a `pos` device) running low (fewer than LOW_WATER numbers left, or none today) gets its next block first.
     *
     * @return array{numbers: bool, calling: bool, business_date: string, block_size: int, low_water: int, blocks: list<array{start: int, end: int, next: int}>, next_number: int|null}
     */
    public function state(Branch $branch, ?Device $device, bool $reserve): array
    {
        $date = BusinessDay::of($branch);
        $numbers = self::numbersOn($branch);
        $till = $device !== null && $device->type === DeviceType::POS && (int) $device->branch_id === (int) $branch->id ? $device : null;

        if ($numbers && $till !== null && $reserve) {
            DB::transaction(function () use ($branch, $date, $till) {
                $this->lock($branch, $date);
                $left = $this->blocks($branch, $date, $till)->sum(fn (PosQueueBlock $block) => $block->remaining());
                if ($left < self::LOW_WATER) {
                    $this->reserve($branch, $date, $till);
                }
            });
        }

        $blocks = $numbers && $till !== null
            ? $this->blocks($branch, $date, $till)->filter(fn (PosQueueBlock $block) => $block->remaining() > 0)->values()
            : collect();

        return [
            'numbers' => $numbers,
            'calling' => self::callingOn($branch),
            'business_date' => $date,
            'block_size' => self::BLOCK_SIZE,
            'low_water' => self::LOW_WATER,
            'blocks' => $blocks->map(fn (PosQueueBlock $block) => [
                'start' => (int) $block->start_number,
                'end' => (int) $block->end_number,
                'next' => max((int) $block->next_number, (int) $block->start_number),
            ])->all(),
            'next_number' => $blocks->isNotEmpty() ? max((int) $blocks->first()->next_number, (int) $blocks->first()->start_number) : null,
        ];
    }

    /**
     * The branch and day lock every number and block is taken under (PostgreSQL; elsewhere the transaction alone).
     */
    public function lock(Branch $branch, string $date): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(?, ?)', [(int) $branch->id % 2147483647, (int) str_replace('-', '', $date)]);
        }
    }

    /**
     * The till whose blocks number the order: its `pos` device in the same branch; null for the branch's own block.
     */
    private function tillOf(Order $order, Branch $branch): ?Device
    {
        $deviceId = $order->getAttribute('device_id');
        if ($deviceId === null) {
            return null;
        }
        $device = Device::find((int) $deviceId);

        return $device !== null && $device->type === DeviceType::POS && (int) $device->branch_id === (int) $branch->id ? $device : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosQueueBlock>
     */
    private function blocks(Branch $branch, string $date, ?Device $device): \Illuminate\Support\Collection
    {
        return PosQueueBlock::where('branch_id', $branch->id)
            ->where('business_date', $date)
            ->when($device !== null, fn ($query) => $query->where('device_id', $device?->id), fn ($query) => $query->whereNull('device_id'))
            ->orderBy('start_number')
            ->get();
    }

    /**
     * Takes $number for the till's (or the branch's) order: it must lie in one of its blocks of the day and be free.
     */
    private function take(Branch $branch, string $date, ?Device $device, int $number): bool
    {
        if ($number < 1) {
            return false;
        }
        $block = $this->blocks($branch, $date, $device)->first(fn (PosQueueBlock $block) => $block->contains($number));
        if ($block === null || $this->used($branch, $date, $number)) {
            return false;
        }
        if ($number + 1 > (int) $block->next_number) {
            $block->forceFill(['next_number' => $number + 1])->save();
        }

        return true;
    }

    /**
     * The next free number of the till's (or the branch's) blocks; a new block when they are used up.
     */
    private function next(Branch $branch, string $date, ?Device $device): int
    {
        foreach ($this->blocks($branch, $date, $device) as $block) {
            $from = max((int) $block->next_number, (int) $block->start_number);
            if ($from > (int) $block->end_number) {
                continue;
            }
            $used = array_flip(Order::withTrashed()
                ->where('branch_id', $branch->id)
                ->where('business_date', $date)
                ->whereBetween('queue_number', [$from, (int) $block->end_number])
                ->pluck('queue_number')
                ->map(fn ($value) => (int) $value)
                ->all());
            for ($number = $from; $number <= (int) $block->end_number; $number++) {
                if (!isset($used[$number])) {
                    $block->forceFill(['next_number' => $number + 1])->save();

                    return $number;
                }
            }
            $block->forceFill(['next_number' => (int) $block->end_number + 1])->save();
        }

        $block = $this->reserve($branch, $date, $device);
        $block->forceFill(['next_number' => (int) $block->start_number + 1])->save();

        return (int) $block->start_number;
    }

    /**
     * Cuts the next block of the day, above every block and number of the branch's day (under the lock).
     */
    private function reserve(Branch $branch, string $date, ?Device $device): PosQueueBlock
    {
        $start = $this->top($branch, $date) + 1;

        return PosQueueBlock::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'device_id' => $device?->id,
            'business_date' => $date,
            'start_number' => $start,
            'end_number' => $start + self::BLOCK_SIZE - 1,
            'next_number' => $start,
        ]);
    }

    /**
     * The highest number of the branch's day: given to an order or reserved in a block.
     */
    private function top(Branch $branch, string $date): int
    {
        $orders = (int) Order::withTrashed()->where('branch_id', $branch->id)->where('business_date', $date)->max('queue_number');
        $blocks = (int) PosQueueBlock::where('branch_id', $branch->id)->where('business_date', $date)->max('end_number');

        return max($orders, $blocks);
    }

    private function used(Branch $branch, string $date, int $number): bool
    {
        return Order::withTrashed()->where('branch_id', $branch->id)->where('business_date', $date)->where('queue_number', $number)->exists();
    }
}
