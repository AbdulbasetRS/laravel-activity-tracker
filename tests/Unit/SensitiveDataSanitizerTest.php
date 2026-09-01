<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Unit;

use Abdulbaset\ActivityTracker\Services\SensitiveDataSanitizer;
use PHPUnit\Framework\TestCase;

final class SensitiveDataSanitizerTest extends TestCase
{
    public function test_it_strips_configured_sensitive_keys(): void
    {
        $sanitizer = new SensitiveDataSanitizer(['password', 'remember_token']);

        $result = $sanitizer->sanitizeAttributes([
            'name' => 'Ahmed',
            'password' => 'secret',
            'remember_token' => 'abc123',
        ]);

        $this->assertSame(['name' => 'Ahmed'], $result);
    }

    public function test_it_matches_sensitive_keys_case_insensitively_and_by_substring(): void
    {
        $sanitizer = new SensitiveDataSanitizer(['token']);

        $result = $sanitizer->sanitizeAttributes([
            'name' => 'Ahmed',
            'API_TOKEN' => 'xyz',
            'access_token_hash' => 'xyz',
        ]);

        $this->assertSame(['name' => 'Ahmed'], $result);
    }

    public function test_it_redacts_secret_shaped_bindings_when_bindings_storage_is_enabled(): void
    {
        $sanitizer = new SensitiveDataSanitizer([]);

        $result = $sanitizer->sanitizeBindings([
            'Ahmed',
            'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
            42,
        ]);

        $this->assertSame('Ahmed', $result[0]);
        $this->assertSame('***REDACTED***', $result[1]);
        $this->assertSame(42, $result[2]);
    }
}
