<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'webhook:admin {email?} {--password=} {--name=Admin}';

    public function __construct()
    {
        // Set before parent::__construct(), which is what copies the value onto
        // the underlying Symfony command.
        $this->description = __('webhookhub.console.admin_description');

        parent::__construct();
    }

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask(__('webhookhub.console.admin_email'));
        $password = $this->option('password') ?: $this->secret(__('webhookhub.console.admin_password'));

        if (! $email || ! $password) {
            $this->error(__('webhookhub.console.admin_required'));

            return self::FAILURE;
        }

        if (strlen($password) < 10) {
            $this->error(__('webhookhub.console.admin_password_short'));

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->option('name'), 'password' => Hash::make($password)]
        );

        $this->info(__(
            $user->wasRecentlyCreated ? 'webhookhub.console.admin_created' : 'webhookhub.console.admin_updated',
            ['email' => $user->email]
        ));

        return self::SUCCESS;
    }
}
