<?php
$content = file_get_contents('app/Http/Controllers/Api/V1/Auth/TelegramAuthController.php');
$content = str_replace(
    'public function authenticate(Request $request): JsonResponse',
    "public function authenticate(Request $request): JsonResponse\n    {\n        \Illuminate\Support\Facades\Log::info('AUTH_ATTEMPT', \$request->all());",
    $content
);
$content = str_replace(
    "return \$this->error('Bot token is not configured.', [], 404);",
    "\Illuminate\Support\Facades\Log::error('Bot token is not configured.'); return \$this->error('Bot token is not configured.', [], 404);",
    $content
);
$content = str_replace(
    "return \$this->error('Invalid Telegram authentication data.', [], 401);",
    "\Illuminate\Support\Facades\Log::error('Invalid Telegram authentication data.', ['telegramUser' => \$telegramUser ?? null]); return \$this->error('Invalid Telegram authentication data.', [], 401);",
    $content
);
$content = str_replace(
    "return \$this->error('Store not found or bot not configured.', [], 404);",
    "\Illuminate\Support\Facades\Log::error('Store not found or bot not configured.'); return \$this->error('Store not found or bot not configured.', [], 404);",
    $content
);
file_put_contents('app/Http/Controllers/Api/V1/Auth/TelegramAuthController.php', $content);
