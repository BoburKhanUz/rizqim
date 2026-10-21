<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The body of POST /owner/modifier-groups and PUT /owner/modifier-groups/{id} (P1-21; P2-30: a form request, so the
 * OpenAPI document and the generated clients carry it). The permission (menu.manage) is the route's; the checks that
 * need the store (linked variants, bounds) stay in ModifierGroupController.
 */
class ModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            /** @var array<string, string>|null The name in other languages: {"ru": "..."} */
            'name_i18n' => 'nullable|array',
            'name_i18n.*' => 'nullable|string|max:255',
            'min_select' => 'nullable|integer|min:0|max:50',
            'max_select' => 'nullable|integer|min:1|max:50',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'modifiers' => 'sometimes|array|max:100',
            'modifiers.*.id' => 'nullable|integer',
            'modifiers.*.name' => 'required|string|max:255',
            /** @var array<string, string>|null The name in other languages: {"ru": "..."} (each at most 255 characters) */
            'modifiers.*.name_i18n' => 'nullable|array',
            'modifiers.*.price_delta' => 'nullable|numeric|min:0|max:100000000|decimal:0,2',
            'modifiers.*.max_quantity' => 'nullable|integer|min:1|max:50',
            'modifiers.*.product_variant_id' => 'nullable|integer',
            'modifiers.*.sort_order' => 'nullable|integer',
            'modifiers.*.is_active' => 'nullable|boolean',
        ];
    }
}
