<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\User;
use App\Services\Push\FcmClient;
use App\Services\Push\PushContent;
use App\Services\Push\PushSender;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * php artisan push:test {target}: a test push straight to a user's devices or to one device (not through the queue and
 * not subject to the user's preferences), to check the Firebase setup. Tokens are never printed in full.
 */
class PushTestCommand extends Command
{
    protected $signature = 'push:test
        {target : User id, user phone, or device uuid}
        {--title=Rizqim sinov : Push title}
        {--body=Push xabarlar ishlayapti. : Push text}
        {--dry-run : Only show the FCM message, send nothing}';

    protected $description = "Sinov push xabarini foydalanuvchining qurilmalariga yoki bitta qurilmaga yuboradi (docs/PUSH.md)";

    public function handle(PushSender $sender, FcmClient $fcm): int
    {
        $devices = $this->devicesFor((string) $this->argument('target'));

        if ($devices->isEmpty()) {
            $this->error('Bunday foydalanuvchi yoki qurilma topilmadi.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $content = new PushContent((string) $this->option('title'), (string) $this->option('body'), ['type' => 'test'], 'push-test');

        if (!$dryRun && !$sender->isConfigured()) {
            $this->error("Firebase sozlanmagan: FIREBASE_CREDENTIALS yoki FIREBASE_CREDENTIALS_JSON ni .env ga yozing (docs/PUSH.md).");

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($devices as $device) {
            $label = "{$device->uuid} ({$device->type->value}, {$device->name})";

            if (blank($device->push_token)) {
                $this->warn("{$label}: push token yo'q, o'tkazib yuborildi.");
                continue;
            }

            if ($dryRun) {
                $this->line("{$label}: " . json_encode($fcm->payload($content, '...' . substr((string) $device->push_token, -6)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                continue;
            }

            $outcome = $sender->send($device, $content);

            if ($outcome->sent()) {
                $this->info("{$label}: yuborildi.");
            } else {
                $failed++;
                $this->error("{$label}: {$outcome->status->value}" . ($outcome->detail ? " - {$outcome->detail}" : ''));
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, Device>
     */
    protected function devicesFor(string $target): Collection
    {
        $target = trim($target);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $target)) {
            return Device::where('uuid', strtolower($target))->active()->get();
        }

        $user = ctype_digit($target) && strlen($target) < 10
            ? User::find((int) $target)
            : User::where('phone', $target)->first();

        return $user ? Device::where('user_id', $user->id)->active()->orderBy('id')->get() : new Collection();
    }
}
