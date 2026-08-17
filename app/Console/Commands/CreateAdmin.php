<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'webhook:admin {email?} {--password=} {--name=Admin}';

    protected $description = 'Admin felhasználó létrehozása vagy jelszavának cseréje';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('E-mail cím');
        $password = $this->option('password') ?: $this->secret('Jelszó');

        if (! $email || ! $password) {
            $this->error('E-mail és jelszó kötelező.');

            return self::FAILURE;
        }

        if (strlen($password) < 10) {
            $this->error('A jelszó legyen legalább 10 karakter.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => Hash::make($password)]
        );

        $this->info("Rendben: {$user->email} ({$user->name})");

        return self::SUCCESS;
    }
}
