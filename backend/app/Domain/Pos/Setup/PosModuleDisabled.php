<?php

namespace App\Domain\Pos\Setup;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * P6-11: a request needs a POS module the branch's format has switched off (PosModules, `pos.module:<key>`). The API's
 * answer, never reported: 403 {success: false, message, code: "module_disabled", module, data: {reason:
 * "module_disabled", module, module_label, profile}}. The owner switches it on in the partner panel's "Format" page.
 */
class PosModuleDisabled extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly PosModule $module,
        public readonly ?FormatProfile $profile,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(PosModule $module, ?FormatProfile $profile): self
    {
        return new self($module, $profile, "\"{$module->label()}\" moduli bu filialda o'chirilgan. Uni hamkor panelidagi \"Format\" sahifasida yoqish mumkin.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'module_disabled',
            'module' => $this->module->value,
            'data' => [
                'reason' => 'module_disabled',
                'module' => $this->module->value,
                'module_label' => $this->module->label(),
                'profile' => $this->profile?->value,
            ],
        ], 403);
    }
}
