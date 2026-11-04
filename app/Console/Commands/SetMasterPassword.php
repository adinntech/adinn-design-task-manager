<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Sets the shared "inspection" password that works for every bd / designer /
 * designer_head account (never admin). Only a bcrypt hash is stored — in .env as
 * MASTER_LOGIN_PASSWORD_HASH — so the plain password never touches the repo,
 * the database or the logs. Logging in with it does not update last_login_at.
 */
class SetMasterPassword extends Command
{
    protected $signature = 'auth:set-master-password {--clear : Remove the master password (disables it)}';

    protected $description = 'Set or clear the shared inspection password for bd / designer / designer head accounts';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->error('.env not found.');

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $this->writeEnv($envPath, '');
            $this->call('config:clear');
            $this->info('Master password disabled.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('New master password (input is hidden)');

        if (strlen($password) < 8) {
            $this->error('Use at least 8 characters.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Type it again to confirm')) {
            $this->error('The two entries do not match. Nothing was changed.');

            return self::FAILURE;
        }

        $this->writeEnv($envPath, Hash::make($password));
        $this->call('config:clear');
        $this->info('Master password saved (hash only). It works for bd, designer and designer head accounts, never admin.');

        return self::SUCCESS;
    }

    private function writeEnv(string $path, string $hash): void
    {
        $contents = File::get($path);
        // Single quotes keep dotenv from expanding the "$" characters inside a bcrypt hash.
        $line = "MASTER_LOGIN_PASSWORD_HASH='{$hash}'";

        if (preg_match('/^MASTER_LOGIN_PASSWORD_HASH=.*$/m', $contents)) {
            $contents = preg_replace('/^MASTER_LOGIN_PASSWORD_HASH=.*$/m', addcslashes($line, '\\$'), $contents);
        } else {
            $contents = rtrim($contents)."\n\n".$line."\n";
        }

        File::put($path, $contents);
    }
}
