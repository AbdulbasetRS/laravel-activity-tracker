<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A deliberately non-"App\Models\User"-named authenticatable model, used to
 * prove the package's causer resolution doesn't assume any specific class.
 */
final class TestUser extends Authenticatable
{
    protected $table = 'test_users';

    protected $guarded = [];
}
