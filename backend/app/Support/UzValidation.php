<?php

namespace App\Support;

/**
 * Uzbek messages for the common validation rules (the app ships no lang/ files, so Laravel's own are English). The
 * P2-12 management endpoints pass these to validate(); a controller's own messages for a field win over them.
 */
final class UzValidation
{
    /**
     * @param  array<string, string>  $overrides  field.rule => message
     * @return array<string, string|array<string, string>>
     */
    public static function messages(array $overrides = []): array
    {
        return $overrides + [
            'required' => ':attribute majburiy.',
            'required_with' => ':attribute :values bilan birga kiritilishi kerak.',
            'required_if' => ':attribute majburiy.',
            'present' => ':attribute yuborilishi kerak.',
            'string' => ':attribute matn bo\'lishi kerak.',
            'integer' => ':attribute butun son bo\'lishi kerak.',
            'numeric' => ':attribute son bo\'lishi kerak.',
            'boolean' => ':attribute true yoki false bo\'lishi kerak.',
            'array' => ':attribute ro\'yxat yoki obyekt bo\'lishi kerak.',
            'decimal' => ':attribute ko\'pi bilan :decimal xonali kasr bo\'lishi mumkin.',
            // Size rules: one message per attribute type (Laravel picks it by the value's type)
            'min' => [
                'numeric' => ':attribute kamida :min bo\'lishi kerak.',
                'string' => ':attribute kamida :min belgidan iborat bo\'lishi kerak.',
                'array' => ':attribute kamida :min ta bo\'lishi kerak.',
                'file' => ':attribute kamida :min KB bo\'lishi kerak.',
            ],
            'max' => [
                'numeric' => ':attribute ko\'pi bilan :max bo\'lishi mumkin.',
                'string' => ':attribute ko\'pi bilan :max belgi bo\'lishi mumkin.',
                'array' => ':attribute ko\'pi bilan :max ta bo\'lishi mumkin.',
                'file' => ':attribute ko\'pi bilan :max KB bo\'lishi mumkin.',
            ],
            'between' => [
                'numeric' => ':attribute :min va :max oralig\'ida bo\'lishi kerak.',
                'string' => ':attribute :min–:max belgi bo\'lishi kerak.',
                'array' => ':attribute :min–:max ta bo\'lishi kerak.',
                'file' => ':attribute :min–:max KB bo\'lishi kerak.',
            ],
            'in' => ':attribute qiymati noto\'g\'ri.',
            'regex' => ':attribute noto\'g\'ri formatda.',
            'date' => ':attribute sana bo\'lishi kerak.',
            'date_format' => ':attribute :format ko\'rinishida bo\'lishi kerak.',
            'after_or_equal' => ':attribute :date dan oldin bo\'lishi mumkin emas.',
            'timezone' => ':attribute vaqt mintaqasi noto\'g\'ri (masalan Asia/Tashkent).',
            'exists' => ':attribute topilmadi.',
            'unique' => ':attribute allaqachon band.',
        ];
    }

    /**
     * Max 18 digits: always within the bigint id columns (RowId::ROUTE_PATTERN), so a huge number is a 422, not a 500.
     *
     * @return list<string>
     */
    public static function rowId(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999999999999999'];
    }
}
