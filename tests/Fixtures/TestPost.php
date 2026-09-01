<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A plain Eloquent model with NO trait, base class, or observer registration
 * from the package — this is the entire point of the test: tracking must
 * "just work" against ordinary application models.
 */
final class TestPost extends Model
{
    use SoftDeletes;

    protected $table = 'test_posts';

    protected $guarded = [];
}
