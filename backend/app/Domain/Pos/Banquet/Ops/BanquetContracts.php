<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Banquet\BanquetLineKind;
use App\Models\BanquetEvent;
use App\Models\BanquetOpsSetting;
use App\Models\BanquetPayment;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\StoreTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P6-54 (E5): the event's contract (shartnoma): the store's text template (banquet_ops_settings.contract_template, or
 * DEFAULT_TEMPLATE) with its {{placeholders}} filled from the event — the customer, the date and time, the halls, the
 * guests, the package, the totals, the payment schedule and the cancellation rules (P6-53's settings) — for the panel's
 * print view and the till's paper (pos_core). Unknown placeholders stay as typed. All amounts in so'm; a quote currency
 * is shown for reference only ("Dollarda aytilsa ham hisob so'mda").
 *
 * contract_no: "<year>-<seq>" (4 digits), numbered per store and calendar year (store time zone) under the store's
 * settings row lock, given once (number()) and audited.
 */
final class BanquetContracts
{
    public const DOCUMENT = 'array{contract_no: string|null, text: string, lines: list<string>, fields: array<string, string>, warnings: list<' . BanquetWarnings::WARNING . '>}';

    public const PLACEHOLDERS = [
        'contract_no', 'today', 'store_name', 'branch_name', 'customer_name', 'customer_phone', 'event_type', 'event_title',
        'event_date', 'slot', 'time', 'halls', 'guests_guaranteed', 'guests_minimum', 'package_name', 'per_person_price',
        'package_included', 'package_total', 'hall_total', 'extras', 'extras_total', 'total', 'paid_total', 'due_total',
        'quote', 'schedule', 'cancellation_rules',
    ];

    public const DEFAULT_TEMPLATE = <<<'TXT'
TADBIR O'TKAZISH SHARTNOMASI № {{contract_no}}

Sana: {{today}}

{{store_name}} ({{branch_name}}), keyingi o'rinlarda "Ijrochi", va {{customer_name}} (tel.: {{customer_phone}}), keyingi o'rinlarda "Buyurtmachi", quyidagilar haqida ushbu shartnomani tuzdilar.

1. Tadbir: {{event_type}}. Sana: {{event_date}}, vaqti: {{time}} ({{slot}}).
2. Zal: {{halls}}.
3. Mehmonlar: kafolatlangan {{guests_guaranteed}} kishi, minimal {{guests_minimum}} kishi. Hisob haqiqiy mehmonlar soni bo'yicha, lekin kafolatlangan va minimal sondan kam bo'lmagan holda yuritiladi.
4. Paket: {{package_name}}, kishi boshiga {{per_person_price}}. Paket summasi: {{package_total}}.
{{package_included}}
5. Zal ijarasi: {{hall_total}}.
6. Qo'shimchalar: {{extras_total}}.
{{extras}}
7. Jami: {{total}}. To'langan: {{paid_total}}. Qoldiq: {{due_total}}.
{{quote}}
8. To'lov jadvali:
{{schedule}}
9. Bekor qilish shartlari (to'langan summadan ushlab qolinadi):
{{cancellation_rules}}
10. Barcha hisob-kitoblar so'mda amalga oshiriladi. Har bir to'lovga fiskal chek beriladi; yakuniy hisobda zakladlar ayiriladi.

Ijrochi: ____________________          Buyurtmachi: ____________________
TXT;

    public function __construct(
        private readonly BanquetOpsSettings $settings,
        private readonly BanquetWarnings $warnings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed> DOCUMENT
     */
    public function document(BanquetEvent $event): array
    {
        $fields = $this->fields($event);
        $template = $this->settings->of((int) $event->store_id)['contract_template'];
        $text = self::render($template, $fields);

        return [
            'contract_no' => $event->contract_no,
            'text' => $text,
            'lines' => explode("\n", $text),
            'fields' => $fields,
            'warnings' => $this->warnings->of($event),
        ];
    }

    /**
     * Fills {{name}} placeholders (spaces inside the braces allowed); an unknown one stays as typed.
     *
     * @param  array<string, string>  $fields
     */
    public static function render(string $template, array $fields): string
    {
        $text = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn (array $match) => array_key_exists($match[1], $fields) ? $fields[$match[1]] : $match[0], str_replace("\r\n", "\n", $template));

        // A placeholder that filled to nothing on a line of its own leaves no blank line behind
        return (string) preg_replace("/\n{3,}/", "\n\n", $text);
    }

    /**
     * @return array<string, string>
     */
    public function fields(BanquetEvent $event): array
    {
        $store = Store::findOrFail($event->store_id);
        $branch = Branch::find($event->branch_id);
        $tz = StoreTimezone::of($store);
        $sum = fn (mixed $amount) => PosOwnerAlerts::sum(Money::of((string) $amount));
        $snapshot = is_array($event->package_snapshot) ? $event->package_snapshot : [];
        $lines = $event->lines()->get();

        $extras = $lines->filter(fn ($line) => $line->kind === BanquetLineKind::EXTRA)
            ->map(fn ($line) => '   - ' . $line->name . ($line->persons !== null ? " ({$line->persons} kishi)" : '') . ': ' . $sum($line->amount))
            ->values()->all();
        $included = array_values(array_filter(array_map(fn ($text) => is_string($text) ? trim($text) : '', (array) ($snapshot['included'] ?? []))));

        $schedule = [];
        foreach (BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get() as $i => $row) {
            $schedule[] = '   ' . ($i + 1) . '. ' . $row->kind->label()
                . ($row->due_date !== null ? ' — ' . $row->due_date->format('d.m.Y') : '')
                . ' — ' . $sum($row->amount)
                . ' (' . $row->status->label() . ')';
        }

        $rules = [];
        foreach ($this->cancellationRules((int) $event->store_id) as $rule) {
            $rules[] = '   - tadbirdan ' . $rule['min_days'] . ' kun va undan oldin: ' . rtrim(rtrim(number_format((float) $rule['retained_percent'], 2, '.', ''), '0'), '.') . '%';
        }

        $total = Money::of($event->total);
        $paid = BanquetAdvances::total($event);
        $quote = '';
        if (filled($event->quote_currency) && $event->quote_rate !== null && (float) $event->quote_rate > 0) {
            $inQuote = number_format($total->toFloat() / (float) $event->quote_rate, 2, '.', ' ');
            $quote = "Ma'lumot uchun: {$inQuote} {$event->quote_currency} (kurs: 1 {$event->quote_currency} = " . number_format((float) $event->quote_rate, 2, '.', ' ') . " so'm, shartnoma kuni). Hisob so'mda.";
        }

        return [
            'contract_no' => (string) ($event->contract_no ?? '________'),
            'today' => Carbon::now($tz)->format('d.m.Y'),
            'store_name' => (string) $store->name,
            'branch_name' => (string) ($branch->name ?? ''),
            'customer_name' => (string) $event->customer_name,
            'customer_phone' => (string) ($event->customer_phone ?? ''),
            'event_type' => $event->event_type->label(),
            'event_title' => (string) ($event->title ?? ''),
            'event_date' => $event->event_date->format('d.m.Y'),
            'slot' => $event->slot->label(),
            'time' => $event->starts_at->copy()->setTimezone($tz)->format('H:i') . '–' . $event->ends_at->copy()->setTimezone($tz)->format('H:i'),
            'halls' => implode(', ', $event->halls()->orderBy('halls.id')->pluck('halls.name')->map(fn ($name) => (string) $name)->all()),
            'guests_guaranteed' => (string) $event->guests_guaranteed,
            'guests_minimum' => (string) $event->guests_minimum,
            'package_name' => (string) ($snapshot['name'] ?? $event->package()->value('name') ?? '—'),
            'per_person_price' => $sum($snapshot['per_person_price'] ?? $event->package()->value('per_person_price') ?? '0'),
            'package_included' => $included !== [] ? '   Paketga kiradi: ' . implode('; ', $included) . '.' : '',
            'package_total' => $sum($event->package_total),
            'hall_total' => $sum($event->hall_total),
            'extras' => implode("\n", $extras),
            'extras_total' => $sum($event->extras_total),
            'total' => $sum($total),
            'paid_total' => $sum($paid),
            'due_total' => $sum(Money::max(Money::zero(), $total->minus($paid))),
            'quote' => $quote,
            'schedule' => $schedule !== [] ? implode("\n", $schedule) : "   To'lov jadvali hali tuzilmagan.",
            'cancellation_rules' => implode("\n", $rules),
        ];
    }

    /**
     * The store's cancellation rule table (P6-53's BanquetPaymentSettings), the largest band first.
     *
     * @return list<array{min_days: int, retained_percent: float}>
     */
    public function cancellationRules(int $storeId): array
    {
        $rules = app(\App\Domain\Pos\Banquet\Payments\BanquetPaymentSettings::class)->of($storeId)['cancellation_rules'];

        $out = [];
        foreach ($rules as $rule) {
            $out[] = ['min_days' => $rule['min_days'], 'retained_percent' => (float) $rule['retained_percent']];
        }
        usort($out, fn (array $a, array $b) => $b['min_days'] <=> $a['min_days']);

        return $out;
    }

    /**
     * Gives the event its contract number once (the same number on every later call).
     */
    public function number(BanquetEvent $event, User $actor): BanquetEvent
    {
        return DB::transaction(function () use ($event, $actor) {
            $event = BanquetEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (filled($event->contract_no)) {
                return $event;
            }
            $settings = BanquetOpsSetting::where('store_id', $event->store_id)->lockForUpdate()->first()
                ?? BanquetOpsSetting::create(['store_id' => $event->store_id, 'version' => 0]);
            $settings = BanquetOpsSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();

            $year = (int) Carbon::now(StoreTimezone::of(Store::find($event->store_id)))->format('Y');
            $seq = $settings->contract_year === $year ? (int) $settings->contract_seq : 0;
            do {
                $seq++;
                $number = sprintf('%d-%04d', $year, $seq);
            } while (BanquetEvent::where('store_id', $event->store_id)->where('contract_no', $number)->exists());

            $settings->forceFill(['contract_year' => $year, 'contract_seq' => $seq])->save();
            $event->forceFill(['contract_no' => $number, 'version' => (int) $event->version + 1])->save();

            $this->audit->record('banquet.contract_numbered', $event, [], ['contract_no' => $number], [], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);

            return $event;
        });
    }
}
