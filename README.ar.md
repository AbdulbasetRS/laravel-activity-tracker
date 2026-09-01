# Activity Tracker لـ Laravel

*[Read this file in English](README.md)*

باكدج بتسجل كل حاجة بتحصل في الداتابيز بتاعتك أوتوماتيك، من غير ما تكتب سطر كود واحد في الـ Models أو الـ Controllers أو الـ Services بتاعتك.

`abdulbaset/activity-tracker` بيراقب الـ Eloquent models والاستعلامات في التطبيق بتاعك، ويحولها لسجلات "activity" منظمة — من غير ما تضيف trait، ولا observer، ولا حتى سطر كود واحد.

```bash
composer require abdulbaset/activity-tracker
php artisan migrate
```

خلاص كده، التتبع اشتغل.

---

## المحتويات

1. [بيعمل إيه](#بيعمل-إيه)
2. [ليه عملناه](#ليه-عملناه)
3. [التثبيت](#التثبيت)
4. [التفعيل الأوتوماتيكي](#التفعيل-الأوتوماتيكي)
5. [الإعدادات](#الإعدادات)
6. [الـ Migration](#الـ-migration)
7. [العمليات اللي بتتسجل](#العمليات-اللي-بتتسجل)
8. [تتبع الـ Retrieval](#تتبع-الـ-retrieval)
9. [العمليات الجماعية (Bulk)](#العمليات-الجماعية-bulk)
10. [تتبع الاستعلامات](#تتبع-الاستعلامات)
11. [حماية البيانات الحساسة](#حماية-البيانات-الحساسة)
12. [استثناء موديلات معينة](#استثناء-موديلات-معينة)
13. [تسجيل المستخدم (Causer)](#تسجيل-المستخدم-causer)
14. [بيانات الـ Request](#بيانات-الـ-request)
15. [الدعم مع الـ Queue](#الدعم-مع-الـ-queue)
16. [الـ Transactions](#الـ-transactions)
17. [قراءة الـ Activities](#قراءة-الـ-activities)
18. [لوحة التحكم (Dashboard)](#لوحة-التحكم-dashboard)
19. [الأحداث (Events)](#الأحداث-events)
20. [التوسعة](#التوسعة)
21. [الأداء](#الأداء)
22. [حدود الباكدج](#حدود-الباكدج)
23. [حل المشاكل](#حل-المشاكل)
24. [الاختبارات](#الاختبارات)
25. [المساهمة](#المساهمة)
26. [الرخصة](#الرخصة)

---

## بيعمل إيه

الباكدج بتشتغل على طبقتين تلقائيًا:

- **أحداث الـ Eloquent** (`eloquent.*`) للعمليات اللي ليها معنى واضح: created, updated (مع الفرق بين القديم والجديد)، deleted, restored, force-deleted, retrieved.
- **مستمع استعلامات الداتابيز** (`DB::listen`) لأي حاجة أحداث Eloquent مش بتشوفها: `count()`, `exists()`, `sum()`/`avg()`/`min()`/`max()`، الـ mass update/delete عن طريق query builder، واستعلامات `DB::table()` الخام.

فيه آلية ذكية بتتأكد إن العملية المنطقية الواحدة — زي `$user->update([...])` اللي بتطلع حدث Eloquent (updating/updated) واستعلام SQL (UPDATE) في نفس الوقت — تتسجل **مرة واحدة بس**، مش أكتر.

## ليه عملناه

معظم باكدجات الـ audit log بتطلب منك تضيف trait لكل موديل عايز تتبعه، أو تنادي method يدوي. الطريقة دي شغالة، بس فيها مشاكل:

- أي موديل جديد هيفضل من غير تتبع لحد ما حد يفتكر يضيف الـ trait.
- العمليات الجماعية أو الخام اللي بتتخطى أحداث الموديل مش هتتسجل خالص.
- عمليات القراءة التجميعية زي `count()` و`exists()` أساسًا مش هتتسجل خالص.

الباكدج دي بتراقب نظام الأحداث بتاع الفريمورك نفسه، فالتغطية بتبقى أوتوماتيكية ومتسقة على كل التطبيق، دلوقتي وبكرة، لأي موديل هتضيفه في المستقبل.

## التثبيت

```bash
composer require abdulbaset/activity-tracker
```

Laravel بيسجل الـ `ActivityTrackerServiceProvider` لوحده عن طريق الـ auto-discovery. بعد كده شغّل الـ migration المرفقة:

```bash
php artisan migrate
```

أو استخدم أمر التثبيت السريع، اللي كمان بيعرض عليك تنشر الإعدادات والـ migration لو عايز تعدلهم:

```bash
php artisan activity:install
```

## التفعيل الأوتوماتيكي

مش محتاج تعدل أي كود في التطبيق بتاعك خالص. الباكدج:

- بتسجل نفسها عن طريق الـ package discovery بتاع Laravel (`extra.laravel.providers` في `composer.json`).
- بتستمع للحدث الشامل `eloquent.*` والحدث `QueryExecuted`، والاتنين دول Laravel بيطلقهم لوحده لكل موديل وكل استعلام.
- الـ migration بتاعتها متضمنة جوه الباكدج نفسها (بتتحمل عن طريق `loadMigrationsFrom`)، فـ `php artisan migrate` هتشتغل حتى لو مانشرتش حاجة.

لو عندك package discovery متعطل، سجل الـ provider يدوي:

```php
// config/app.php
'providers' => [
    Abdulbaset\ActivityTracker\ActivityTrackerServiceProvider::class,
],
```

## الإعدادات

انشر ملف الإعدادات علشان تعدل السلوك:

```bash
php artisan vendor:publish --tag=activity-tracker-config
```

هيطلع لك `config/activity-tracker.php` وكل خيار فيه متشرح جوّاه: مفتاح التشغيل العام، الـ connection/table، إيه اللي هيتتبع، سلوك تتبع الـ retrieval، الأعمدة الحساسة، قوائم الاستثناء، تسجيل الاستعلامات، جمع بيانات الـ request، إعدادات الـ queue، ومدة الاحتفاظ بالبيانات.

## الـ Migration

جدول `activities` بيخزن:

| العمود | الغرض منه |
|---|---|
| `batch_id` / `request_id` | ربط الأنشطة اللي حصلت في نفس الـ request/job |
| `causer_type` / `causer_id` | Polymorphic — مين اللي عمل العملية |
| `action` | `created`, `updated`, `deleted`, `restored`, `force_deleted`, `retrieved`, `retrieved_many`, `count`, `exists`, `sum`/`avg`/`min`/`max`, `bulk_updated`, `bulk_deleted`, `raw_insert` |
| `subject_type` / `subject_id` | Polymorphic — العملية حصلت على إيه |
| `old_values` / `new_values` / `changed_values` | الفروقات بصيغة JSON لعمليات التحديث |
| `query` / `query_type` | الـ SQL المسجل للأنشطة الجاية من مستمع الاستعلامات |
| `result_count` | عدد الصفوف، لما يكون ممكن استخراجه (شوف [حدود الباكدج](#حدود-الباكدج)) |
| `ip_address`, `user_agent`, `route_name`, `http_method`, `url` | بيانات الـ HTTP، بتفضل null برا سياق الـ HTTP |
| `metadata` | JSON حر لأي حاجة تانية |

انشرها وعدلها لو محتاج:

```bash
php artisan vendor:publish --tag=activity-tracker-migrations
```

## العمليات اللي بتتسجل

| العملية | المصدر | الـ action المسجل |
|---|---|---|
| `Model::create()` | حدث Eloquent | `created` |
| `$model->update()` / `save()` | حدث Eloquent | `updated` (مع الفرق) |
| `$model->delete()` | حدث Eloquent | `deleted` |
| `$model->restore()` | حدث Eloquent | `restored` |
| `$model->forceDelete()` | حدث Eloquent | `force_deleted` |
| `Model::find()` / `first()` / `firstWhere()` | حدث Eloquent (متجمع) | `retrieved` |
| `Model::get()` / `all()` / `cursor()` | حدث Eloquent (متجمع) | `retrieved_many` |
| `Model::count()` / `where(...)->count()` | مستمع الاستعلامات | `count` |
| `Model::exists()` | مستمع الاستعلامات | `exists` |
| `sum()` / `avg()` / `min()` / `max()` | مستمع الاستعلامات | `sum` / `avg` / `min` / `max` |
| `Model::where(...)->update([...])` | مستمع الاستعلامات | `bulk_updated` |
| `Model::where(...)->delete()` | مستمع الاستعلامات | `bulk_deleted` |
| `DB::table(...)->insert()/update()/delete()` | مستمع الاستعلامات | `raw_insert` / `bulk_updated` / `bulk_deleted` |

تقدر تفعّل/تعطل أي واحدة من دول لوحدها تحت `track` في ملف الإعدادات.

## تتبع الـ Retrieval

`Model::find()`, `first()`, `firstWhere()`، وأي استدعاء بيرجع نتيجة واحدة بيولّد نشاط `retrieved` واحد بس.

`Model::get()`, `all()`، أو أي حاجة بتجيب مجموعة (collection) **مش** بتولّد نشاط لكل صف. بدل كده، عمليات الجلب بتتجمع طول مدة الـ request/console command/queue job الحالي وبتتفرّغ كنشاط **واحد** `retrieved_many` مع `result_count` بعدد الموديلات اللي اتجابت — سواء كانوا 3 أو 300,000.

```php
'retrieval' => [
    'track_single' => true,
    'track_many' => true,
    'store_ids' => false, // اختياري: تخزن الـ IDs اللي اتجابت في الـ metadata
    'max_ids' => 100,     // حد أقصى حتى لو store_ids مفعّل
],
```

تخزين الـ IDs للمجموعات الكبيرة اختياري ومحدود بالظبط للسبب اللي مكتوب في التعليق: تكلفة الذاكرة والتخزين.

## العمليات الجماعية (Bulk)

```php
User::where('status', 'inactive')->update(['status' => 'active']);
User::where('status', 'inactive')->delete();
```

الاتنين بيتخطوا أحداث Eloquent الخاصة بكل موديل لوحده (ده سلوك طبيعي في Eloquent، مش قصور في الباكدج). مستمع الاستعلامات بيكتشف الـ `UPDATE`/`DELETE` الناتج وبيسجل `bulk_updated` / `bulk_deleted` على الجدول، من غير ما يحمّل الموديلات المتأثرة في الذاكرة.

## تتبع الاستعلامات

فيه `QueryClassifier` مخصص بينظّم الـ SQL (الحروف الكبيرة/الصغيرة، المسافات، علامات الاقتباس) قبل ما يصنفه كـ `select`, `insert`, `update`, `delete`, `count`, `exists`, `sum`, `avg`, `min`, `max`, أو `unknown`. استعلامات الـ `select` العادية **مش** بتتسجل مباشرة — أساسًا هي متمثلة في `retrieved`/`retrieved_many`، وتسجيل الاتنين هيكرر كل عملية قراءة.

المصنف قابل للتوسعة من غير ما تعمل fork للباكدج:

```php
app(\Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface::class)
    ->extendPattern('/^explain/', 'diagnostic');
```

## حماية البيانات الحساسة

الأعمدة المحددة في `sensitive_columns` (باسورد، توكينز، سيكريتس، إلخ) بتتشال **تمامًا** من `old_values`/`new_values`/`changed_values` — مش بتتخفى، بتتشال خالص من غير ما تتخزن بأي شكل:

```php
'sensitive_columns' => [
    'password', 'password_confirmation', 'remember_token',
    'api_token', 'access_token', 'refresh_token', 'secret',
],
```

الـ SQL bindings الخام مش بتتخزن افتراضيًا (`query_log.store_bindings = false`) بالظبط لأن الـ bindings معندهاش أسماء أعمدة موثوقة تتقارن بـ `sensitive_columns`. لو فعّلتها، فيه heuristic بسيط بيخفي القيم الطويلة اللي شكلها زي التوكينز — شوف [حدود الباكدج](#حدود-الباكدج).

## استثناء موديلات معينة

```php
'ignored_models' => [
    App\Models\TemporaryLog::class,
],

'ignored_tables' => [
    'migrations', 'jobs', 'sessions', // ...وأكتر افتراضيًا
],
```

جدول `activities` بتاع الباكدج نفسه (وموديل `Activity`) مستثنى دايمًا — وده اللي بيمنع التكرار اللانهائي، بالإضافة للحماية الصريحة `TrackingContext::withoutTracking()`.

## تسجيل المستخدم (Causer)

الباكدج مش بتفترض إن `App\Models\User` هو موديل المصادقة بتاعك. بتسأل نظام الـ auth بتاع Laravel عن أي guard شغال حاليًا وبتخزن مرجع polymorphic:

```php
$activity->causer_type; // مثلاً App\Models\Admin, App\Models\User, null
$activity->causer_id;
$activity->causer;      // بيترجع عن طريق morphTo()
```

في السياقات اللي مفيهاش مصادقة (زوار، CLI، queue jobs من غير جلسة auth)، الحقلين دول بيبقوا `null`.

## بيانات الـ Request

لما الباكدج تكون شغالة جوه HTTP request، الأنشطة بتسجل `ip_address`, `user_agent`, `route_name`, `http_method`, و`url` (كل واحدة فيهم قابلة للتفعيل/الإلغاء لوحدها تحت `context` في الإعدادات). محتاجاش حاجة من دول تكون موجودة — أي accessor بيرجع `null` في الـ CLI، أوامر Artisan، وقوايم الانتظار، والباكدج شغالة فيهم بالكامل.

## الدعم مع الـ Queue

فعّل التخزين الغير متزامن علشان التتبع محتاجش يأخر الـ request اللي شغّله:

```php
'queue' => [
    'enabled' => true,
    'connection' => null, // بياخد الـ connection الافتراضي للتطبيق
    'queue' => 'default',
],
```

الـ payload المرسل للـ queue عبارة عن array عادي آمن للـ JSON (IDs وقيم بسيطة) — مش موديل Eloquent متسلسل — فمحتاجش الـ request الأصلي أو حالة الداتابيز تفضل موجودة لحد ما الـ worker ياخد الشغلانة.

الـ workers طويلة المدى متعامل معاها بشكل صريح: الـ `TrackingContext` (اللي فيه batch ID، request ID، وعمليات الـ retrieval المتجمعة) بيتصفّر في كل `JobProcessing`، وبيتفرّغ ويتصفّر تاني في `JobProcessed`، فمفيش حاجة بتسرب من job لتاني في نفس الـ worker process.

## الـ Transactions

```php
DB::transaction(function () use ($user) {
    $user->update(['status' => 'active']);
});
```

لو التخزين المتزامن مستخدم والـ transaction اتعمله commit، النشاط يبقى اتسجل خلاص وقت ما الـ transaction يخلص. لو الـ transaction اتعمله **rollback**، حدث `updated` بتاع الموديل أساسًا مش بيتطلق لو الـ UPDATE فشل في معظم حالات الـ rollback — بس لو وضع الـ queue مفعّل، ممكن الـ job يتبعت قبل ما الـ rollback يحصل. علشان تتأكد إن النشاط مش هيتسجل لشغل اتعمله rollback، سيب `queue.enabled` مقفول للأكواد اللي فيها transactions، أو لف العملية وأجّل الإرسال يدوي عن طريق `DB::afterCommit()` في الكود بتاعك. المفاضلة دي متكتوبة صراحة مش متخبية: تنفيذ buffering كامل الوعي بالـ transaction لكل إعداد queue ممكن كان معقد أكتر من الفايدة بتاعه في النسخة الأولى.

## قراءة الـ Activities

```php
use Abdulbaset\ActivityTracker\Models\Activity;

Activity::query()
    ->where('action', 'updated')
    ->latest()
    ->get();

Activity::causedBy($user)
    ->forSubject($post)
    ->whereAction('updated')
    ->latest()
    ->get();

Activity::inBatch($batchId)->get();
```

التتبع الأوتوماتيكي مش معتمد على الـ API ده خالص — هو موجود بس علشان تقدر تستعلم عن اللي اتسجل قبل كده.

## لوحة التحكم (Dashboard)

فيه لوحة تحكم إدارية كاملة واختيارية مبنية بـ Blade — نظرة عامة، جدول أنشطة قابل للبحث والفلترة والترتيب، وصفحة تفاصيل كاملة لكل نشاط. من غير React/Vue/Inertia/Livewire، من غير Node build step، ومن غير ما تحتاج تعمل publish لأي حاجة علشان تشتغل بشكل منسق.

```
/activity-tracker              -> نظرة عامة
/activity-tracker/activities   -> جدول قابل للبحث والفلترة والترتيب
/activity-tracker/activities/1 -> تفاصيل كاملة لنشاط واحد
/activity-tracker/statistics   -> إحصائيات وچارت بسيط عبر الوقت
```

### تفعيل / تعطيل اللوحة

اللوحة شغالة افتراضيًا. تقفلها خالص — مفيش routes بتتسجل أساسًا، مش مجرد مخفية ورا 403 — بكده:

```php
// config/activity-tracker.php
'ui' => [
    'enabled' => false,
],
```

### الـ Routes

كل route ليه اسم، تحت prefix اسمه `activity-tracker.`:

| الاسم | الغرض |
|---|---|
| `activity-tracker.dashboard` | صفحة النظرة العامة |
| `activity-tracker.activities.index` | جدول الأنشطة القابل للبحث والفلترة |
| `activity-tracker.activities.show` | تفاصيل نشاط واحد |
| `activity-tracker.statistics` | صفحة الإحصائيات والچارت |
| `activity-tracker.assets` | بيسيرف الـ CSS/JS بتاع اللوحة |

متكتبش رابط اللوحة يدوي أبدًا — استخدم دايمًا `route('activity-tracker.activities.index')` وأخواتها، علشان لو غيرت الـ `ui.prefix` الروابط متتكسرش.

### الصلاحيات

اللوحة مش بتظهر لمجرد إن المستخدم عامل تسجيل دخول. الوصول ليها محكوم بـ `Gate` اسمه `viewActivityTracker`، بيتفحص عن طريق middleware اسمه `can:` كل ما `ui.authorize` يكون `true` (وده الافتراضي).

**الباكدج جايبة سلوك افتراضي آمن ومقفول من الأساس**: الـ Gate الافتراضي بيسمح بالدخول بس لو `app()->environment('local')` — يعني شغال لوحده على جهازك المحلي، ومقفول لأي حد لما ترفع المشروع، لحد ما تقرر إنت غير كده. ده نفس سلوك Laravel Telescope وHorizon، وعن قصد مش بيفترض وجود موديل User، ولا عمود role، ولا method اسمه `isAdmin()` في تطبيقك.

علشان تتحكم في الوصول بنفسك، عرّف الـ Gate في الـ `AuthServiceProvider` بتاعك (وده هيلغي الافتراضي بتاع الباكدج لأنها بتشتغل قبله):

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewActivityTracker', function ($user) {
    return $user->isAdmin(); // أو ->hasRole('admin')، أو ->can('view-audit-log')، إلخ
});
```

علشان تشيل فحص الـ Gate خالص (مثلاً لو إنت بتتحكم في الوصول بالكامل عن طريق `ui.middleware`)، حط:

```php
'ui' => [
    'authorize' => false,
],
```

الـ `ui.middleware` (افتراضيًا `['web', 'auth']`) متطبق على كل routes اللوحة ما عدا route الأصول الثابتة، اللي عن قصد من غير شرط تسجيل دخول — لأنه بس بيسيرف CSS/JS، مفيهوش حاجة خاصة أو حساسة.

### الإعدادات

```php
'ui' => [
    'enabled' => true,
    'prefix' => 'activity-tracker',
    'middleware' => ['web', 'auth'],
    'authorize' => true,
    'per_page' => 25,
    'per_page_options' => [25, 50, 100, 250],
    'theme' => 'system', // 'light' أو 'dark' أو 'system'
],
```

### البحث والفلاتر والترتيب والصفحات

صفحة الأنشطة فيها خانة بحث واحدة (الوصف، الـ action، نوع الـ subject/الـ ID بتاعه، الـ causer ID، الـ IP، الـ route، request ID، batch ID)، وپانل فلاتر قابل للتوسيع (اختيار متعدد للـ actions، نوع الـ subject، الـ causer، مدى تاريخي، IP، HTTP method، الـ route، request ID، batch ID)، وترتيب للأعمدة. الأعمدة القابلة للترتيب وأحجام الصفحات محددة مسبقًا (whitelist) من جوه السيرفر (`Abdulbaset\ActivityTracker\Services\ActivityFilters`) — قيم الـ query string الخام مش بتوصل لـ `orderBy()` مباشرة أبدًا. الفلاتر بتفضل موجودة لما تتنقل بين الصفحات لأنها أساسًا مجرد query string parameters.

صفحة تفاصيل النشاط فيها لينكات بتوديك مباشرة لنفس الجدول بس مفلتر على batch/request (`?batch_id=...` / `?request_id=...`) علشان تشوف كل اللي حصل في request واحد أو عملية مترابطة واحدة.

### تخصيص الـ Views

```bash
php artisan vendor:publish --tag=activity-tracker-views
```

بتنشر في `resources/views/vendor/activity-tracker/`. أي حاجة هناك بتلغي نسخة الباكدج من نفس المسار — ده سلوك Laravel العادي لتخصيص الـ views.

### تخصيص الـ Assets

```bash
php artisan vendor:publish --tag=activity-tracker-assets
```

بتنشر في `public/vendor/activity-tracker/{css,js}/app.css|app.js`. لو فيه نسخة منشورة موجودة، route الأصول (`activity-tracker.assets`) بيسيرف النسخة دي بدل نسخة الباكدج — عدّل فيها زي ما تحب.

### الوضع الليلي (Dark Mode)

فيه زرار في الشريط العلوي بيبدّل بين الوضع الفاتح والغامق، ومتخزن في `localStorage` (من غير أي إعداد في الداتابيز). `ui.theme` بيتحكم في التفضيل الافتراضي لأول زيارة: `'light'`, `'dark'`, أو `'system'` (بيتبع تفضيل نظام التشغيل/المتصفح عن طريق `prefers-color-scheme`).

## الأحداث (Events)

```php
use Abdulbaset\ActivityTracker\Events\ActivityRecording; // قبل التخزين، الـ payload قابل للتعديل
use Abdulbaset\ActivityTracker\Events\ActivityRecorded;  // بعد التخزين
```

```php
Event::listen(ActivityRecording::class, function (ActivityRecording $event) {
    $event->payload['metadata']['tenant_id'] = tenant()->id;
});
```

## التوسعة

كل مكون رئيسي مرتبط بـ interface وممكن تستبدله عن طريق الـ container:

```php
$this->app->bind(
    \Abdulbaset\ActivityTracker\Contracts\ActivityStorageInterface::class,
    \App\Support\ElasticsearchActivityStorage::class,
);
```

الـ contracts المتاحة: `ActivityLoggerInterface`, `QueryClassifierInterface`, `ActivityTransformerInterface`, `SensitiveDataSanitizerInterface`, `ActivityStorageInterface`.

## الأداء

- استعلامات الـ `SELECT` العادية مش بتتسجل لوحدها خالص — بس العدد التجميعي هو اللي بيتسجل، مرة واحدة لكل request/job.
- تتبع المجموعات (collections) بياخد O(1) نشاط بغض النظر عن عدد الصفوف.
- الإعدادات بتتجمع مرة واحدة وقت الإقلاع وبتتقرأ من الـ config repository بتاع Laravel (متخزنة في الذاكرة أساسًا في الإنتاج عن طريق `config:cache`).
- العمليات الجماعية أبدًا مش بتحمّل الموديلات المتأثرة في الذاكرة.
- التخزين الغير متزامن (`queue.enabled`) بيشيل عملية الكتابة من مسار الـ request الحرج تمامًا.

## حدود الباكدج

من فضلك اقرأ الجزء ده قبل ما تعتمد على الباكدج لأي غرض تدقيقي (compliance) صارم:

- **`result_count` للأنشطة الجاية من مستمع الاستعلامات مش متوفر دايمًا.** حدث `QueryExecuted` بتاع Laravel بيدّي بس الـ SQL، الـ bindings، والتوقيت — مش النتيجة الفعلية للاستعلام ولا عدد الصفوف المتأثرة. أنشطة `count`, `exists`, `sum`/`avg`/`min`/`max`, `bulk_updated`, و`bulk_deleted` بتسجل *إن* العملية حصلت وعلى أي جدول، بس الرقم الفعلي مش متسجل. `retrieved`/`retrieved_many` هما الاستثناء — الـ `result_count` بتاعهم دقيق تمامًا لأنه جاي من عملية hydration بتاعة Eloquent نفسها، مش من مستمع الاستعلامات.
- **الاستعلامات الخام اللي على جدول بس مش ممكن تترابط بموديل Eloquent.** `DB::table('users')->count()` بيطلع `model_type = null, table = users` بالتصميم — الباكدج مش هتحاول تخمن أي موديل بيمثل الجدول.
- **مش ممكن تفرق بين update/delete جماعي عن طريق query builder وبين استدعاء `DB::table()` مماثل.** الاتنين بينتجوا نفس الـ SQL بالظبط. الاتنين بيتسجلوا كـ `bulk_updated`/`bulk_deleted` على الجدول.
- **الـ bindings مش بتتخزن افتراضيًا**، ولو فعّلتها، إخفاء القيم الحساسة فيها heuristic بسيط (نصوص طويلة من غير مسافات)، مش ضمان مؤكد — مفيش اسم عمود موثوق تتقارن بيه مع `sensitive_columns` على مستوى الـ binding.
- **الـ rollback في الـ transactions مش مضمون 100% إنه يمنع كتابة نشاط متبعت للـ queue** — شوف [الـ Transactions](#الـ-transactions).
- **اللوحة مش هتعرض حاجة الـ engine مسجلهاش أصلاً.** صفحة تفاصيل النشاط بتعرض "Not captured" للنتائج التجميعية وعدد الصفوف المتأثرة بدل ما تختلق رقم، للأسباب اللي فوق. وكمان أبدًا مش بتخمن لينك لصفحة الموديل بتاعة تطبيقك — لو عايز كده، ضيفه في نسخة منشورة ومعدلة من الـ view.

## حل المشاكل

**مفيش أي أنشطة بتتسجل خالص.**
تأكد من `activity-tracker.enabled` وإن جدول `activities` موجود (`php artisan migrate`). كمان تأكد إن الموديل/الجدول مش موجود في `ignored_models` / `ignored_tables`.

**بشوف أنشطة شكلها مكرر لاستدعاء `save()` واحد.**
ده مفروض ميحصلش — افتح issue وقولنا الـ traits بتاعة الموديل والاستدعاء بالظبط، غالبًا ده معناه فيه ثغرة في ربط توقعات creating/updating/deleting.

**الأنشطة مش بتتبعت للـ queue حتى لو `queue.enabled` مفعّل.**
تأكد إن فيه queue worker شغال (`php artisan queue:work`) وإن `queue.connection` بيوصل لـ connection شغال وموجود فعلاً.

**بياخدلي 403 وأنا داخل على اللوحة.**
ده متوقع برا بيئة الـ `local` لحد ما تعرّف الـ Gate بتاعك `viewActivityTracker` — شوف [الصلاحيات](#الصلاحيات).

**بترجعلي على صفحة login مش موجودة، بدل 403.**
ده middleware اسمه `auth` (موجود في `ui.middleware`)، مش من الباكدج — بيحوّل الزوار لـ route اسمه `login`. سجل دخول الأول، أو عدّل `ui.middleware` لو بتتحكم في الوصول بطريقة تانية.

**اللوحة طالعة من غير تنسيق، HTML عادي بس.**
تأكد إن route الأصول `activity-tracker.assets` شغال (وهو مستثنى عن قصد من شرط الـ auth بتاع `ui.middleware`) وإن مفيش حاجة في تطبيقك بتعترض `/activity-tracker/assets/*`.

## الاختبارات

```bash
composer install
composer test      # PHPUnit عن طريق Orchestra Testbench
composer stan       # PHPStan
composer format     # Laravel Pint
```

مجموعة الاختبارات بتغطي: create/update/delete/restore/force-delete، find/first/get، count/exists/aggregates، bulk update/delete، عمليات `DB::table()` الخام، الموديلات المستثناة، حذف الحقول الحساسة، الحماية من التكرار اللانهائي، وتجميع/تفريغ عمليات الـ retrieval — بالإضافة، بالنسبة للوحة التحكم: التحكم في الوصول (الـ Gate الافتراضي، الـ Gate المخصص، مفتاح `authorize`)، إمكانية إلغاء اللوحة بالكامل (`ui.enabled = false` بيلغي الـ routes بتاعتها)، البحث، كل فلتر، الترتيب، الصفحات، العرض المفلتر على batch/request، والتعامل الآمن مع subject أو causer محذوف في صفحة التفاصيل.

## المساهمة

الـ issues والـ pull requests مرحب بيها. من فضلك شغّل `composer stan` و`composer format` قبل ما تبعت، وضيف اختبارات لأي سلوك جديد.

## الرخصة

MIT. شوف [LICENSE](LICENSE).
