<?php

namespace App\Services\Admin\Stores;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Jobs\SendStoreBroadcastJob;
use App\Models\Store;
use App\Models\StoreBroadcast;
use App\Models\StoreBroadcastRecipient;
use App\Models\User;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * P4-22: ommaviy e'lon — a Telegram message to the owners of all or selected stores.
 *
 *  - audience: `all` (every store not deleted, optionally narrowed by `statuses` and `region_id`) or `selected`
 *    (`store_ids`, up to 500); the recipients are the owners who joined the bot (an owner of several stores gets one
 *    message);
 *  - preview: how many stores, how many owners get it, how many have no Telegram, a few store names;
 *  - send: the member confirms the preview's `recipients` count (a different count now — the audience changed — is
 *    409 count_changed with the new preview), then a SendStoreBroadcastJob queues one message per owner. At most
 *    LIMIT_PER_DAY broadcasts a day platform-wide; the route also limits each member (throttle:admin-store-broadcast).
 *  - Wave M1: the messages leave in waves at ADMIN_BROADCAST_RATE_PER_SECOND (deliver), so a broadcast never floods
 *    the queue worker and the bot that also carry the order alerts; the preview says how long sending takes.
 */
class StoreBroadcastService
{
    public const LIMIT_PER_DAY = 10;

    public const MAX_SELECTED = 500;

    public function __construct(
        private readonly TelegramMessenger $telegram,
    ) {}

    /**
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     * @return array{stores: int, recipients: int, without_telegram: int, sample: list<string>, estimated_seconds: int}
     */
    public function preview(array $audience): array
    {
        $stores = $this->stores($audience);
        $storeCount = (clone $stores)->count();
        $owners = $this->owners($stores);
        $withTelegram = (clone $owners)->whereNotNull('telegram_id')->where('telegram_id', '!=', '')->where('telegram_id', 'not like', 'temp\_%');
        $recipients = (int) (clone $withTelegram)->distinct()->count('telegram_id');
        $storesReached = (int) (clone $withTelegram)->distinct()->count('store_id');

        return [
            'stores' => $storeCount,
            'recipients' => $recipients,
            'without_telegram' => max(0, $storeCount - $storesReached),
            'sample' => (clone $stores)->orderBy('name')->limit(5)->pluck('name')->map(fn ($name) => (string) $name)->all(),
            // Wave M1: how long sending takes at the configured rate
            'estimated_seconds' => self::estimatedSeconds($recipients),
        ];
    }

    /**
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     *
     * @throws StoreAdminException
     */
    public function send(array $audience, string $text, int $confirmedRecipients, User $member, string $reason): StoreBroadcast
    {
        if (!$this->telegram->isConfigured()) {
            throw new StoreAdminException("Telegram bot sozlanmagan: e'lon yuborib bo'lmaydi.", 'telegram_not_configured', 503);
        }

        return DB::transaction(function () use ($audience, $text, $confirmedRecipients, $member, $reason) {
            // One sender at a time, so two members cannot both pass the daily limit
            DB::select('select pg_advisory_xact_lock(?)', [crc32('admin:store-broadcasts')]);

            $today = StoreBroadcast::query()->where('created_at', '>=', now()->subDay())->count();
            if ($today >= self::LIMIT_PER_DAY) {
                throw new StoreAdminException("Bir kunda ko'pi bilan " . self::LIMIT_PER_DAY . " ta e'lon yuboriladi.", 'daily_limit', 429);
            }

            $preview = $this->preview($audience);
            if ($preview['recipients'] === 0) {
                throw new StoreAdminException("Bu e'lonni oladigan Telegram'li ovqatxona egasi yo'q.", 'no_recipient');
            }
            if ($preview['recipients'] !== $confirmedRecipients) {
                throw new StoreAdminException("Qabul qiluvchilar soni o'zgardi: qayta ko'rib chiqing.", 'count_changed', 409, ['preview' => $preview]);
            }

            $broadcast = StoreBroadcast::create([
                'created_by' => $member->id,
                'audience' => $audience['audience'],
                'store_ids' => $audience['audience'] === 'selected' ? array_map('intval', $audience['store_ids'] ?? []) : null,
                'text' => trim($text),
                'reason' => mb_substr(trim($reason), 0, 500),
                'stores' => $preview['stores'],
                'recipients' => $preview['recipients'],
                'status' => 'queued',
            ]);

            // Filters of an `all` broadcast travel with the job (the row keeps the store ids of `selected` only)
            DB::afterCommit(fn () => SendStoreBroadcastJob::dispatch($broadcast->id, array_intersect_key($audience, array_flip(['audience', 'store_ids', 'statuses', 'region_id']))));

            return $broadcast;
        });
    }

    /**
     * Start or continue a broadcast (the job). The first run claims it (queued -> sending) and lists its recipients
     * once (one row per chat); every run then hands one wave to the Telegram queue and schedules the next one, so the
     * messages leave at most `admin.store_broadcasts.rate_per_second` a second instead of all at once. Idempotent per
     * recipient (a chat is queued once, under the broadcast's row lock) and resumable: a run that stopped half way
     * continues with the chats not queued yet. A broadcast already sent (or failed) is left alone.
     *
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     */
    public function deliver(StoreBroadcast $broadcast, array $audience): void
    {
        // Claimed and listed together: a run that fails while listing leaves the broadcast queued for the retry
        DB::transaction(function () use ($broadcast, $audience) {
            $claimed = StoreBroadcast::query()->whereKey($broadcast->id)->where('status', 'queued')
                ->update(['status' => 'sending', 'started_at' => now()]);
            if ($claimed === 1) {
                $this->listRecipients($broadcast, $audience);
            }
        });

        $next = $this->sendWave($broadcast->id);
        if ($next !== null) {
            SendStoreBroadcastJob::dispatch($broadcast->id, $audience)->delay($next);
        }
    }

    /**
     * Continue a broadcast that failed half way (its job gave up): the chats not queued yet get it, the others not
     * again. False when there is nothing to resume (not failed, or it never listed its recipients).
     */
    public function resume(StoreBroadcast $broadcast): bool
    {
        $listed = StoreBroadcastRecipient::query()->where('store_broadcast_id', $broadcast->id)->exists();
        $resumed = $listed && StoreBroadcast::query()->whereKey($broadcast->id)->where('status', 'failed')
            ->update(['status' => 'sending', 'finished_at' => null]) === 1;
        if ($resumed) {
            SendStoreBroadcastJob::dispatch($broadcast->id, ['audience' => $broadcast->audience, 'store_ids' => $broadcast->store_ids]);
        }

        return $resumed;
    }

    /**
     * Seconds a broadcast to this many chats takes at the configured rate.
     */
    public static function estimatedSeconds(int $recipients): int
    {
        return $recipients <= 0 ? 0 : (int) ceil($recipients / self::rate());
    }

    /**
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     */
    private function listRecipients(StoreBroadcast $broadcast, array $audience): void
    {
        $this->owners($this->stores($audience))
            ->whereNotNull('telegram_id')->where('telegram_id', '!=', '')->where('telegram_id', 'not like', 'temp\_%')
            ->select(['id', 'telegram_id'])
            ->chunkById(500, function ($owners) use ($broadcast) {
                $now = now();
                // An owner of several stores is one chat; a chat listed by an earlier run stays as it was
                StoreBroadcastRecipient::query()->insertOrIgnore($owners->unique('telegram_id')->map(fn (User $owner) => [
                    'store_broadcast_id' => $broadcast->id,
                    'user_id' => $owner->id,
                    'telegram_id' => (string) $owner->telegram_id,
                    'created_at' => $now,
                ])->values()->all());
            });
    }

    /**
     * Hands the next wave to the Telegram queue, each message delayed so that at most `rate` leave in a second.
     * Returns the seconds until the next wave, or null when the broadcast is finished (or not sending).
     */
    private function sendWave(int $broadcastId): ?int
    {
        $rate = self::rate();
        $size = max(1, (int) config('admin.store_broadcasts.wave_size', 20));

        return DB::transaction(function () use ($broadcastId, $rate, $size) {
            $broadcast = StoreBroadcast::query()->whereKey($broadcastId)->lockForUpdate()->first();
            if ($broadcast === null || $broadcast->status !== 'sending') {
                return null;
            }

            $wave = StoreBroadcastRecipient::query()->where('store_broadcast_id', $broadcastId)->whereNull('queued_at')
                ->orderBy('id')->limit($size)->get();
            foreach ($wave->values() as $i => $recipient) {
                // Plain text (no parse mode): the announcement reaches the chat as the member typed it. Queued once
                // this transaction commits (SendTelegramRequest), so a wave rolled back sends nothing.
                $this->telegram->sendMessageLater(intdiv($i, $rate), $recipient->telegram_id, "📣 RIZQIM e'loni\n\n" . $broadcast->text, [], [
                    'user_id' => $recipient->user_id,
                    'type' => 'admin_store_broadcast',
                ]);
            }
            if ($wave->isNotEmpty()) {
                StoreBroadcastRecipient::query()->whereKey($wave->modelKeys())->update(['queued_at' => now()]);
            }

            $remaining = StoreBroadcastRecipient::query()->where('store_broadcast_id', $broadcastId)->whereNull('queued_at')->exists();
            $broadcast->forceFill([
                'queued' => StoreBroadcastRecipient::query()->where('store_broadcast_id', $broadcastId)->whereNotNull('queued_at')->count(),
                ...($remaining ? [] : ['status' => 'sent', 'finished_at' => now()]),
            ])->save();

            return $remaining ? max(1, (int) ceil($wave->count() / $rate)) : null;
        });
    }

    private static function rate(): int
    {
        return max(1, (int) config('admin.store_broadcasts.rate_per_second', 5));
    }

    /**
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     * @return Builder<Store>
     */
    private function stores(array $audience): Builder
    {
        $query = Store::query();

        if ($audience['audience'] === 'selected') {
            $query->whereIn('id', array_slice(array_map('intval', $audience['store_ids'] ?? []), 0, self::MAX_SELECTED));
        } else {
            $statuses = array_values(array_filter($audience['statuses'] ?? [], fn ($status) => StoreStatus::tryFrom((string) $status) !== null));
            if ($statuses !== []) {
                $query->whereIn('status', $statuses);
            }
            if (!empty($audience['region_id'])) {
                $query->whereHas('branches', fn ($branches) => $branches->where('region_id', (int) $audience['region_id']));
            }
        }

        return $query;
    }

    /**
     * @param  Builder<Store>  $stores
     * @return Builder<User>
     */
    private function owners(Builder $stores): Builder
    {
        return User::query()
            ->where('role', UserRole::OWNER->value)
            ->whereIn('store_id', (clone $stores)->select('stores.id'));
    }
}
