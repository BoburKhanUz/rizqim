<?php

namespace App\Domain\Pos\TerminalFlow;

use App\Models\Branch;
use App\Models\BranchTerminalSetup;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * A branch's card flow and installation checklist (P6-16, D5 c), read with the defaults: no card mode chosen, nothing
 * ticked, a difference threshold of 0 (any difference between the bank terminal and the Z alerts the owner), the till
 * asking for the bank terminal's day total at the Z. Changes are the owner's (settings.manage) and audited.
 */
final class TerminalSetup
{
    public const ITEM = 'array{key: string, label: string, hint: string, done: bool, done_at: string|null, done_by: array{id: int, name: string|null}|null}';

    public const VIEW = 'array{branch_id: int, card_mode: \'terminal_only\'|\'smart_kassa\'|\'none\'|null, card_mode_label: string|null, checklist: list<' . self::ITEM . '>, checklist_complete: bool, difference_threshold: string, ask_bank_total: bool, updated_at: string|null}';

    public function __construct(private readonly AuditLogger $audit) {}

    public function row(Branch $branch): ?BranchTerminalSetup
    {
        return BranchTerminalSetup::where('branch_id', $branch->id)->first();
    }

    public function threshold(Branch $branch): Money
    {
        $row = $this->row($branch);

        return Money::of($row !== null ? $row->difference_threshold : '0');
    }

    public function cardMode(Branch $branch): ?CardMode
    {
        return $this->row($branch)?->card_mode;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Branch $branch, ?BranchTerminalSetup $row = null): array
    {
        $row ??= $this->row($branch);
        $stored = is_array($row?->checklist) ? $row->checklist : [];
        $people = User::whereIn('id', array_filter(array_map(fn (array $item) => $item['done_by'] ?? null, $stored)))->pluck('name', 'id');

        $items = [];
        foreach (TerminalChecklist::ITEMS as $key => [$label, $hint]) {
            $item = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $by = isset($item['done_by']) ? (int) $item['done_by'] : null;
            $items[] = [
                'key' => $key,
                'label' => $label,
                'hint' => $hint,
                'done' => (bool) ($item['done'] ?? false),
                'done_at' => isset($item['done_at']) ? (string) $item['done_at'] : null,
                'done_by' => $by !== null ? ['id' => $by, 'name' => $people[$by] ?? null] : null,
            ];
        }

        return [
            'branch_id' => (int) $branch->id,
            'card_mode' => $row?->card_mode?->value,
            'card_mode_label' => $row?->card_mode?->label(),
            'checklist' => $items,
            'checklist_complete' => array_reduce($items, fn (bool $all, array $item) => $all && $item['done'], true),
            'difference_threshold' => Money::of($row !== null ? $row->difference_threshold : '0')->toString(),
            'ask_bank_total' => $row !== null ? (bool) $row->ask_bank_total : true,
            'updated_at' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Applies a validated change: `card_mode` (null: not chosen), `checklist` (item => done; ticking stamps who and
     * when, unticking clears it), `difference_threshold`, `ask_bank_total`. Keys left out are kept.
     *
     * @param  array{card_mode?: string|null, checklist?: array<string, bool>, difference_threshold?: string, ask_bank_total?: bool}  $input
     * @return array<string, mixed> the setup as present() shows it
     */
    public function update(Branch $branch, array $input, User $actor): array
    {
        return DB::transaction(function () use ($branch, $input, $actor) {
            $row = BranchTerminalSetup::where('branch_id', $branch->id)->lockForUpdate()->first()
                ?? new BranchTerminalSetup(['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
            $before = $row->exists ? $this->present($branch, $row) : null;

            if (array_key_exists('card_mode', $input)) {
                $row->card_mode = $input['card_mode'] !== null ? CardMode::from($input['card_mode']) : null;
            }
            if (array_key_exists('difference_threshold', $input)) {
                $row->difference_threshold = Money::of($input['difference_threshold'])->toString();
            }
            if (array_key_exists('ask_bank_total', $input)) {
                $row->ask_bank_total = (bool) $input['ask_bank_total'];
            }
            if (isset($input['checklist'])) {
                $checklist = is_array($row->checklist) ? $row->checklist : [];
                foreach ($input['checklist'] as $key => $done) {
                    $was = (bool) ($checklist[$key]['done'] ?? false);
                    if ($done && !$was) {
                        $checklist[$key] = ['done' => true, 'done_at' => now()->toIso8601String(), 'done_by' => (int) $actor->id];
                    } elseif (!$done) {
                        $checklist[$key] = ['done' => false, 'done_at' => null, 'done_by' => null];
                    }
                }
                $row->checklist = $checklist;
            }
            $row->updated_by = $actor->id;
            $row->save();

            $after = $this->present($branch, $row);
            $this->audit->record('settings.terminal_flow_changed', $branch, $before ?? [], $after, [], [
                'actor' => $actor,
                'store_id' => (int) $branch->store_id,
                'branch_id' => (int) $branch->id,
            ]);

            return $after;
        });
    }
}
