<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::first();
if (! $user) {
    fwrite(STDERR, "No users found. Create a user first.\n");
    exit(1);
}
$token = $user->createToken('cli')->plainTextToken;
echo $token . PHP_EOL;
