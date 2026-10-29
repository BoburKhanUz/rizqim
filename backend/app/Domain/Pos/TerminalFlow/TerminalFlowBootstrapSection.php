<?php

namespace App\Domain\Pos\TerminalFlow;

use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;

/**
 * `terminal_flow` of GET /pos/bootstrap (P6-16): how the branch takes cards and what the till asks for, while the
 * branch has the `terminal_card_flow` module (null otherwise): the card mode, whether this terminal is a smart kassa
 * (and its provider kassa id), whether to ask the bank terminal's day total at the Z, whether the branch's tills share
 * one bank terminal settled once a day (`shared_terminal`: the day's total belongs at the last Z), whether the
 * installation checklist is complete. The card's last four digits are always optional. Review P6 S#5: the threshold
 * that alerts the owner is not sent to the till (it sees the difference only once the server compared it).
 */
final class TerminalFlowBootstrapSection implements PosBootstrapContributor
{
    public const SECTION = 'array{card_mode: \'terminal_only\'|\'smart_kassa\'|\'none\'|null, smart_kassa: bool, fiscal_kassa_id: string|null, ask_bank_total: bool, shared_terminal: bool, checklist_complete: bool}';

    public function __construct(
        private readonly PosModules $modules,
        private readonly TerminalSetup $setup,
    ) {}

    /**
     * @return array{terminal_flow: array<string, mixed>|null}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        if (!$this->modules->enabled($branch, PosModule::TERMINAL_CARD_FLOW)) {
            return ['terminal_flow' => null];
        }

        $setup = $this->setup->present($branch);

        return ['terminal_flow' => [
            'card_mode' => $setup['card_mode'],
            'smart_kassa' => $device !== null && (bool) $device->getAttribute('smart_kassa'),
            'fiscal_kassa_id' => $device?->getAttribute('fiscal_kassa_id'),
            'ask_bank_total' => (bool) $setup['ask_bank_total'],
            'shared_terminal' => (bool) $setup['shared_terminal'],
            'checklist_complete' => (bool) $setup['checklist_complete'],
        ]];
    }
}
