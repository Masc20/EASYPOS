<?php

namespace Tests\Unit\Identity;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FloorPinVerificationTest extends TestCase
{
    public function test_pin_is_hashed_and_verified_by_laravel(): void
    {
        $user = new User(['pin_code' => '0123']);
        $this->assertNotSame('0123', $user->pin_code);
        $this->assertTrue(Hash::check('0123', $user->pin_code));
        $this->assertTrue($user->verifyPin('0123'));
        $this->assertFalse($user->verifyPin('1230'));
    }

    public function test_missing_pin_cannot_authenticate(): void
    {
        $this->assertFalse((new User())->verifyPin('1234'));
    }

    public function test_serialization_hides_pin_password_and_remember_token(): void
    {
        $user = new User(['pin_code' => '0123', 'password' => 'test-password']);
        $user->remember_token = 'test-token';
        foreach (['pin_code', 'password', 'remember_token'] as $key) {
            $this->assertArrayNotHasKey($key, $user->toArray());
        }
    }
}
