<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:token
                            {email=test@test.com : Email of the user to issue a token for}
                            {--name=postman : A label for the token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create (or reuse) a user and print a Sanctum API token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'API Test User',
                'password' => Hash::make('password'),
            ]
        );

        $token = $user->createToken($this->option('name'))->plainTextToken;

        $this->info("User: {$user->email}");
        $this->info("Token: {$token}");

        return self::SUCCESS;
    }
}
