<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * There is no self-registration; accounts for the team are created here.
 */
#[Signature('app:create-user')]
#[Description('Create an editor or admin account')]
class CreateUser extends Command
{
    /** Presence colours, assigned round-robin so editors are easy to tell apart. */
    private const COLORS = ['#0b6374', '#c2410c', '#7c3aed', '#be123c', '#15803d', '#1d4ed8'];

    public function handle(): int
    {
        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: fn (string $value) => Validator::make(
            ['email' => $value],
            ['email' => ['email', 'unique:users,email']],
        )->errors()->first('email') ?: null);
        $password = password('Password', required: true, validate: fn (string $value) => strlen($value) < 8 ? 'Use at least 8 characters.' : null);
        $role = select('Role', ['editor' => 'Editor', 'admin' => 'Admin'], default: 'editor');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'color' => self::COLORS[User::count() % count(self::COLORS)],
        ]);

        $this->info("Created {$user->role} {$user->email}.");

        return self::SUCCESS;
    }
}
