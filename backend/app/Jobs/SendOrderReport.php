<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Order\OrderReportService;
use App\Services\Telegram\TelegramBotApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * An owner's/manager's orders report, built by the worker as of the time it was requested and sent to Telegram
 * as a CSV document (retried like any other Telegram call).
 */
class SendOrderReport extends SendTelegramRequest
{
    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $storeId,
        public ?int $branchId,
        public string $timeframe,
        public ?string $startDate,
        public ?string $endDate,
        int|string $chatId,
        public CarbonImmutable $asOf,
    ) {
        parent::__construct('sendDocument', ['chat_id' => $chatId]);
    }

    protected function send(TelegramBotApi $telegram): Response
    {
        $reports = app(OrderReportService::class);
        $orders = $reports->orders($this->storeId, $this->branchId, $this->timeframe, $this->startDate, $this->endDate, $this->asOf);

        return $telegram->call('sendDocument', [
            'chat_id' => $this->params['chat_id'],
            'caption' => $reports->caption(Store::find($this->storeId)?->name, $this->timeframe, $this->startDate, $this->endDate, $orders),
            'parse_mode' => 'Markdown',
        ], [
            'name' => 'document',
            'contents' => $reports->csv($orders),
            'filename' => $reports->filename($this->timeframe, $this->asOf),
        ]);
    }
}
