<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Unit;

use Abdulbaset\ActivityTracker\Services\QueryClassifier;
use PHPUnit\Framework\TestCase;

final class QueryClassifierTest extends TestCase
{
    private QueryClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new QueryClassifier;
    }

    /**
     * @dataProvider classificationProvider
     */
    public function test_it_classifies_sql(string $sql, string $expected): void
    {
        $this->assertSame($expected, $this->classifier->classify($sql));
    }

    public static function classificationProvider(): array
    {
        return [
            'simple select' => ['select * from `users` where `id` = ?', 'select'],
            'select with join' => ['select * from `users` inner join `roles` on `roles`.`id` = `users`.`role_id`', 'select'],
            'count aggregate' => ['select count(*) as aggregate from `users`', 'count'],
            'sum aggregate' => ['select sum(`balance`) as aggregate from `users`', 'sum'],
            'avg aggregate' => ['select avg(`age`) as aggregate from `users`', 'avg'],
            'min aggregate' => ['select min(`id`) as aggregate from `users`', 'min'],
            'max aggregate' => ['select max(`id`) as aggregate from `users`', 'max'],
            'exists' => ['select exists(select * from `users` where `email` = ?) as `exists`', 'exists'],
            'insert' => ['insert into `users` (`name`, `email`) values (?, ?)', 'insert'],
            'update' => ['update `users` set `name` = ? where `id` = ?', 'update'],
            'delete' => ['delete from `users` where `id` = ?', 'delete'],
            'uppercase sql' => ['SELECT COUNT(*) AS AGGREGATE FROM `USERS`', 'count'],
            'unrecognized' => ['pragma table_info(users)', 'unknown'],
        ];
    }

    public function test_it_extracts_table_names(): void
    {
        $this->assertSame('users', $this->classifier->extractTable('select * from `users` where `id` = ?'));
        $this->assertSame('users', $this->classifier->extractTable('insert into `users` (`name`) values (?)'));
        $this->assertSame('users', $this->classifier->extractTable('update `users` set `name` = ?'));
        $this->assertSame('users', $this->classifier->extractTable('delete from `users` where `id` = ?'));
        $this->assertSame('users', $this->classifier->extractTable('select * from `app`.`users`'));
        $this->assertNull($this->classifier->extractTable('pragma table_info(users)'));
    }

    public function test_custom_patterns_can_extend_classification(): void
    {
        $this->classifier->extendPattern('/^explain/', 'diagnostic');

        $this->assertSame('diagnostic', $this->classifier->classify('EXPLAIN select * from users'));
    }
}
