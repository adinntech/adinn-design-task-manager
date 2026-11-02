<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Creates the VAPID key pair Web Push needs and writes it into .env (like
 * key:generate). Refuses to replace existing keys without --force, because new
 * keys silently cut off every browser that already enabled notifications.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid {--force : Replace keys that are already set}';

    protected $description = 'Generate the VAPID keys for browser desktop notifications and save them in .env';

    public function handle(): int
    {
        $envPath = $this->laravel->environmentFilePath();
        $env = is_file($envPath) ? (string) file_get_contents($envPath) : '';

        if (preg_match('/^VAPID_PUBLIC_KEY=\S+/m', $env) && ! $this->option('force')) {
            $this->warn('VAPID keys already exist in .env — nothing changed (use --force to replace them; every browser will then have to enable notifications again).');

            return self::SUCCESS;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\RuntimeException $e) {
            // Windows PHP builds cannot create EC keys unless OpenSSL finds its
            // config file, which is only read from OPENSSL_CONF at process start.
            $this->error($e->getMessage().'. On Windows, set OPENSSL_CONF (e.g. D:\xampp\php\extras\ssl\openssl.cnf) before starting PHP and retry.');

            return self::FAILURE;
        }

        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $env = preg_match("/^{$name}=.*$/m", $env)
                ? preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env, "\r\n").PHP_EOL."{$name}={$value}".PHP_EOL;
        }

        file_put_contents($envPath, $env);

        $this->info('VAPID keys written to .env. Run `php artisan config:clear` if config is cached.');

        return self::SUCCESS;
    }
}
