<?php
namespace AbdulbasetRS\ActivityTracker\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use AbdulbasetRS\ActivityTracker\ActivityTrackerServiceProvider; // تأكد من مسار الكلاس الصحيح

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // توجيه بيئة الاختبار لمسار الـ migrations الخاص بالباكدج لتشغيله
        // تأكد من أن المسار يطابق هيكل مجلدات مشروعك الفعلي
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations'); 
    }

    protected function getPackageProviders($app)
    {
        // تسجيل الـ Service Provider الخاص بالباكدج ليتعرف عليه تطبيق الاختبار
        return [
            ActivityTrackerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // إعداد قاعدة البيانات لتكون SQLite في الذاكرة العشوائية
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}