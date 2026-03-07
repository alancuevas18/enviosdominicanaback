<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID public/private keys for Web Push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $publicKey = $keys['publicKey'];
        $privateKey = $keys['privateKey'];

        $this->updateEnv('VAPID_PUBLIC_KEY', $publicKey);
        $this->updateEnv('VAPID_PRIVATE_KEY', $privateKey);

        $this->info('VAPID keys generated and written to .env:');
        $this->line("  VAPID_PUBLIC_KEY={$publicKey}");
        $this->line("  VAPID_PRIVATE_KEY={$privateKey}");
        $this->newLine();
        $this->comment('Share VAPID_PUBLIC_KEY with your PWA frontend service worker.');

        return self::SUCCESS;
    }

    private function updateEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        if (str_contains($content, "{$key}=")) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= PHP_EOL . "{$key}={$value}";
        }

        file_put_contents($envPath, $content);
    }
}
