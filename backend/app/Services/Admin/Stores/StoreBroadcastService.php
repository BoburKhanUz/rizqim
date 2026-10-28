<?php

namespace App\Services\Admin\Stores;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Jobs\SendStoreBroadcastJob;
use App\Models\Store;
use App\Models\StoreBroadcast;
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
     * @return array{stores: int, recipients: int, without_telegram: int, sample: list<string>}
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
     * Queue the messages of a broadcast (the job). Idempotent: a broadcast already sent is left alone.
     *
     * @param  array{audience: string, store_ids?: list<int>|null, statuses?: list<string>|null, region_id?: int|null}  $audience
     */
    public function deliver(StoreBroadcast $broadcast, array $audience): void
    {
        $claimed = StoreBroadcast::query()->whereKey($broadcast->id)->where('status', 'queued')
            ->update(['status' => 'sending', 'started_at' => now()]);
        if ($claimed === 0) {
            return;
        }

        $chats = $this->owners($this->stores($audience))
            ->whereNotNull('telegram_id')->where('telegram_id', '!=', '')->where('telegram_id', 'not like', 'temp\_%')
            ->orderBy('id')
            ->get(['id', 'telegram_id'])
            ->unique('telegram_id');

        $queued = 0;
        foreach ($chats as $owner) {
            // Plain text (no parse mode): the announcement reaches the chat as the member typed it
            $this->telegram->sendMessage((string) $owner->telegram_id, "📣 RIZQIM e'loni\n\n" . $broadcast->text, [], [
                'user_id' => $owner->id,
                'type' => 'admin_store_broadcast',
            ]);
            $queued++;
        }

        $broadcast->forceFill(['status' => 'sent', 'queued' => $queued, 'finished_at' => now()])->save();
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
