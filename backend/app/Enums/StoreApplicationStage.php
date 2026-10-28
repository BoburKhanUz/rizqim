<?php

namespace App\Enums;

/**
 * P4-22: the onboarding pipeline of a store application (docs/PHASE4_PLAN.md §4.3): ariza → tekshiruv → menyu →
 * qurilmalar → o'qitish → sinov → faol, or rad etildi. Every stage has a checklist: an item is done when ticked by
 * hand or, for the ones marked `detect`, when the store shows it (App\Services\Admin\Stores\ApplicationPipeline).
 * Moving forward needs the required items of every stage passed; `menu` and later need the store created (convert).
 */
enum StoreApplicationStage: string
{
    case APPLICATION = 'application';
    case REVIEW = 'review';
    case MENU = 'menu';
    case DEVICES = 'devices';
    case TRAINING = 'training';
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::APPLICATION => 'Ariza',
            self::REVIEW => 'Tekshiruv',
            self::MENU => 'Menyu',
            self::DEVICES => 'Qurilmalar',
            self::TRAINING => "O'qitish",
            self::TRIAL => 'Sinov',
            self::ACTIVE => 'Faol',
            self::REJECTED => 'Rad etildi',
        };
    }

    /**
     * Position in the pipeline (rejected is outside it).
     */
    public function position(): int
    {
        return match ($this) {
            self::APPLICATION => 0,
            self::REVIEW => 1,
            self::MENU => 2,
            self::DEVICES => 3,
            self::TRAINING => 4,
            self::TRIAL => 5,
            self::ACTIVE => 6,
            self::REJECTED => -1,
        };
    }

    /**
     * Still being onboarded: not active, not rejected.
     */
    public function isOpen(): bool
    {
        return $this !== self::ACTIVE && $this !== self::REJECTED;
    }

    /**
     * From this stage on the application must have its store.
     */
    public function needsStore(): bool
    {
        return $this->position() >= self::MENU->position();
    }

    /**
     * Days the team has for the stage by default (the due date set when an application enters it).
     */
    public function defaultDays(): ?int
    {
        return match ($this) {
            self::APPLICATION => 1,
            self::REVIEW => 2,
            self::MENU => 2,
            self::DEVICES => 2,
            self::TRAINING => 1,
            self::TRIAL => 3,
            self::ACTIVE, self::REJECTED => null,
        };
    }

    /**
     * The checklist of the stage. `detect`: the item is also done when the store shows it (products, a paired till,
     * fiscal mode, a first order); `required`: moving past the stage needs it.
     *
     * @return list<array{key: string, label: string, required: bool, detect: bool}>
     */
    public function checklist(): array
    {
        $item = fn (string $key, string $label, bool $required = true, bool $detect = false) => ['key' => $key, 'label' => $label, 'required' => $required, 'detect' => $detect];

        return match ($this) {
            self::APPLICATION => [
                $item('contacted', "Egasi bilan bog'lanildi"),
            ],
            self::REVIEW => [
                $item('tin_checked', 'STIR tekshirildi'),
                $item('legal_name', 'Yuridik nom tasdiqlandi'),
                $item('bank_account', 'Bank hisob raqami olindi'),
                $item('documents', 'Hujjatlar olindi', false),
            ],
            self::MENU => [
                $item('menu_entered', 'Menyu kiritildi', true, true),
                $item('photos', "Taom rasmlari qo'yildi", false),
                $item('prices', 'Narxlar tekshirildi'),
            ],
            self::DEVICES => [
                $item('pos_paired', 'Kassa ulandi', true, true),
                $item('kds_paired', 'Oshxona ekrani ulandi', false, true),
                $item('printer', 'Printer sozlandi', false),
                $item('fiscal', 'Fiskal chek sozlandi', false, true),
            ],
            self::TRAINING => [
                $item('staff_trained', "Xodimlar o'qitildi"),
                $item('owner_panel', 'Egasi hamkor paneliga kirdi', true, true),
            ],
            self::TRIAL => [
                $item('first_order', 'Birinchi buyurtma', true, true),
                $item('owner_confirmed', 'Egasi ishga tushirishni tasdiqladi'),
            ],
            self::ACTIVE, self::REJECTED => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function pipeline(): array
    {
        return [self::APPLICATION, self::REVIEW, self::MENU, self::DEVICES, self::TRAINING, self::TRIAL, self::ACTIVE];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $stage) => $stage->value, self::cases());
    }
}
