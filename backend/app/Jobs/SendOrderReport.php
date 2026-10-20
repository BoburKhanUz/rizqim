<?php

namespace App\Jobs;

use App\Services\Order\OrderReportService;
use App\Services\Telegram\TelegramBotApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * An owner's/manager's orders report, built by the worker as of the time it was requested and sent to Telegram
 * as a CSV document (retried like any other Telegram call). It goes to the queue in config('orders.reports.queue'),
 * which a worker of its own serves, so a long report never delays the Telegram alerts on the default queue.
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

        $this->onQueue((string) config('orders.reports.queue', 'reports'));
    }

    protected function send(TelegramBotApi $telegram): Response
    {
        $reports = app(OrderReportService::class);
        $report = $reports->build($this->storeId, $this->branchId, $this->timeframe, $this->startDate, $this->endDate, $this->asOf);

        // The CSV stream is read as the request is sent; PHP closes it (and drops its temporary file) once the request
        // no longer holds it
        return $telegram->call('sendDocument', [
            'chat_id' => $this->params['chat_id'],
            'caption' => $report['caption'],
            'parse_mode' => 'Markdown',
        ], [
            'name' => 'document',
            'contents' => $report['csv'],
            'filename' => $reports->filename($this->timeframe, $this->asOf),
        ]);
    }
}
