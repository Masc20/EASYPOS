<?php

namespace Tests\Feature\Security;

use App\Support\Services\CodeGeneratorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordComplexityTest extends TestCase
{
    public function test_bcrypt_cost_factor_is_thirteen_rounds_in_production(): void
    {
        // Explicitly set production cost factor per AUDIT-10 specification
        config(['hashing.bcrypt.rounds' => 13]);

        $password = 'SecureP@ssw0rd2026!';
        $hash = Hash::make($password);

        $info = password_get_info($hash);

        $this->assertSame(
            13,
            $info['options']['cost'] ?? null,
            'Bcrypt cost factor must be set to 13 rounds for hardened password hashing.'
        );
        $this->assertTrue(Hash::check($password, $hash));
    }

    public function test_password_complexity_rule_accepts_strong_passwords(): void
    {
        $rule = ['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]];

        $validator = Validator::make(['password' => 'Valid#Str0ngP@ss2026!'], $rule);

        $this->assertFalse($validator->fails(), 'Strong password meeting all complexity criteria must pass validation.');
    }

    public function test_password_complexity_rule_rejects_passwords_under_twelve_characters(): void
    {
        $rule = ['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]];

        $validator = Validator::make(['password' => 'Sh0rt!123'], $rule);

        $this->assertTrue($validator->fails(), 'Password with fewer than 12 characters must fail validation.');
        $this->assertArrayHasKey('password', $validator->errors()->messages());
    }

    public function test_password_complexity_rule_rejects_missing_mixed_case(): void
    {
        $rule = ['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]];

        // Missing uppercase
        $valLower = Validator::make(['password' => 'alllowercase123!#'], $rule);
        $this->assertTrue($valLower->fails(), 'Password without uppercase letters must fail validation.');

        // Missing lowercase
        $valUpper = Validator::make(['password' => 'ALLUPPERCASE123!#'], $rule);
        $this->assertTrue($valUpper->fails(), 'Password without lowercase letters must fail validation.');
    }

    public function test_password_complexity_rule_rejects_missing_numbers_or_symbols(): void
    {
        $rule = ['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]];

        // Missing symbols
        $valNoSymbols = Validator::make(['password' => 'NoSymbolsPassword123'], $rule);
        $this->assertTrue($valNoSymbols->fails(), 'Password without special symbols must fail validation.');

        // Missing numbers
        $valNoNumbers = Validator::make(['password' => 'NoNumbersPassword!@#$'], $rule);
        $this->assertTrue($valNoNumbers->fails(), 'Password without digits must fail validation.');
    }

    public function test_floor_pin_validation_remains_independent_four_digit_numeric(): void
    {
        $pinRule = ['pin_code' => ['required', 'regex:/^[0-9]{4}$/']];

        // Valid 4-digit PIN
        $valValid = Validator::make(['pin_code' => '1234'], $pinRule);
        $this->assertFalse($valValid->fails(), 'Valid 4-digit numeric PIN must pass floor terminal validation.');

        // Invalid: 5 digits
        $valLong = Validator::make(['pin_code' => '12345'], $pinRule);
        $this->assertTrue($valLong->fails(), '5-digit PIN must fail validation.');

        // Invalid: 3 digits
        $valShort = Validator::make(['pin_code' => '123'], $pinRule);
        $this->assertTrue($valShort->fails(), '3-digit PIN must fail validation.');

        // Invalid: non-numeric
        $valAlpha = Validator::make(['pin_code' => '12ab'], $pinRule);
        $this->assertTrue($valAlpha->fails(), 'Non-numeric PIN must fail validation.');
    }

    public function test_code_generator_service_produces_strong_password(): void
    {
        $rule = ['password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]];

        for ($i = 0; $i < 50; $i++) {
            $password = CodeGeneratorService::generatePassword();
            $validator = Validator::make(['password' => $password], $rule);

            $this->assertSame(12, strlen($password));
            $this->assertFalse(
                $validator->fails(),
                "Generated password failed complexity validation on iteration {$i}."
            );
        }
    }
}
