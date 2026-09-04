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
8. [استراتيجية الـ Retrieval والقراءات الداخلية](#استراتيجية-الـ-retrieval-والقراءات-الداخلية)
9. [تتبع الـ Retrieval](#تتبع-الـ-retrieval)
10. [العمليات الجماعية (Bulk)](#العمليات-الجماعية-bulk)
11. [تتبع الاستعلامات](#تتبع-الاستعلامات)
12. [المدة الزمنية والأداء](#المدة-الزمنية-والأداء)
13. [الرابط الكامل، المسار، والـ Referrer](#الرابط-الكامل-المسار-والـ-referrer)
14. [تتبع الـ Exceptions](#تتبع-الـ-exceptions)
15. [حماية البيانات الحساسة](#حماية-البيانات-الحساسة)
16. [استثناء موديلات معينة](#استثناء-موديلات-معينة)
17. [تسجيل المستخدم (Causer)](#تسجيل-المستخدم-causer)
18. [بيانات الـ Request](#بيانات-الـ-request)
19. [الدعم مع الـ Queue](#الدعم-مع-الـ-queue)
20. [الـ Transactions](#الـ-transactions)
21. [قراءة الـ Activities](#قراءة-الـ-activities)
22. [لوحة التحكم (Dashboard)](#لوحة-التحكم-dashboard)
23. [تسمية الكلاسات](#تسمية-الكلاسات)
24. [الأحداث (Events)](#الأحداث-events)
25. [التوسعة](#التوسعة)
26. [الأداء](#الأداء)
27. [حدود الباكدج](#حدود-الباكدج)
28. [حل المشاكل](#حل-المشاكل)
29. [الاختبارات](#الاختبارات)
30. [المساهمة](#المساهمة)
31. [الرخصة](#الرخصة)

---

## بيعمل إيه

الباكدج بتشتغل على طبقتين تلقائيًا:

- **أحداث الـ Eloquent** (`eloquent.*`) للعمليات اللي ليها معنى واضح: created, updated (مع الفرق بين القديم والجديد)، deleted, restored, force-deleted, retrieved.
- **مستمع استعلامات الداتابيز** (`DB::listen`) لأي حاجة أحداث Eloquent مش بتشوفها: `sum()`/`avg()`/`min()`/`max()`، الـ mass update/delete عن طريق query builder، واستعلامات `DB::table()` الخام.

`count()` و`exists()` عن قصد **مش بيتسجلوا خالص** — شوف [العمليات اللي بتتسجل](#العمليات-اللي-بتتسجل) عشان تعرف ليه.

فيه آلية ذكية بتتأكد إن العملية المنطقية الواحدة — زي `$user->update([...])` اللي بتطلع حدث Eloquent (updating/updated) واستعلام SQL (UPDATE) في نفس الوقت — تتسجل **مرة واحدة بس**، مش أكتر. نفس المبدأ ده منطبق على قراءات الباكدج الداخلية بتاعتها: عرض اللوحة نفسها مش بيتحسب أبدًا كنشاط تطبيق — شوف [استراتيجية الـ Retrieval والقراءات الداخلية](#استراتيجية-الـ-retrieval-والقراءات-الداخلية).

## ليه عملناه

معظم باكدجات الـ audit log بتطلب منك تضيف trait لكل موديل عايز تتبعه، أو تنادي method يدوي. الطريقة دي شغالة، بس فيها مشاكل:

- أي موديل جديد هيفضل من غير تتبع لحد ما حد يفتكر يضيف الـ trait.
- العمليات الجماعية أو الخام اللي بتتخطى أحداث الموديل مش هتتسجل خالص.
- طريقة ساذجة زي "سجل أي قراءة Eloquent" بتولّد ضجيج ضخم — حتى من جوه نظام Laravel الداخلي نفسه (شوف تحت).

الباكدج دي بتراقب نظام الأحداث بتاع الفريمورك نفسه، فالتغطية بتبقى أوتوماتيكية ومتسقة على كل التطبيق، دلوقتي وبكرة، لأي موديل هتضيفه في المستقبل — مع فلترة فعّالة لأي ضجيج جاي من الفريمورك أو من الباكدج نفسها، بدل ما تسجله عمياني.

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
| `action` | `created`, `updated`, `deleted`, `restored`, `force_deleted`, `retrieved`, `retrieved_many`, `sum`/`avg`/`min`/`max`, `bulk_updated`, `bulk_deleted`, `raw_insert`, `exception` |
| `subject_type` / `subject_id` | Polymorphic — العملية حصلت على إيه |
| `old_values` / `new_values` / `changed_values` | الفروقات بصيغة JSON لعمليات التحديث |
| `query` / `query_type` / `database_connection` | الـ SQL المسجل للأنشطة الجاية من مستمع الاستعلامات |
| `result_count` | عدد الصفوف، لما يكون ممكن استخراجه (شوف [حدود الباكدج](#حدود-الباكدج)) |
| `duration_ms` / `memory_usage` / `memory_peak` | شوف [المدة الزمنية والأداء](#المدة-الزمنية-والأداء) |
| `ip_address`, `user_agent`, `route_name`, `http_method`, `url`, `path`, `referrer_url`, `http_status` | بيانات الـ HTTP، بتفضل null برا سياق الـ HTTP — شوف [الرابط الكامل، المسار، والـ Referrer](#الرابط-الكامل-المسار-والـ-referrer) |
| `execution_context` | `http`, `cli`, أو `queue` |
| `command` | اسم أمر الـ Artisan (الـ signature بتاعه)، في سياق الـ CLI |
| `job_name`, `queue_name`, `queue_connection`, `queue_attempt` | سياق الـ queue job — شوف [الدعم مع الـ Queue](#الدعم-مع-الـ-queue) |
| `exception_class`, `exception_message`, `exception_file`, `exception_line`, `stack_trace` | شوف [تتبع الـ Exceptions](#تتبع-الـ-exceptions) |
| `metadata` | JSON حر لأي حاجة تانية |

كل عمود اتضاف بعد الإصدار الأول nullable — النشر وتشغيل الـ migration الجديدة أبدًا ميلمسش أو يبطّل السجلات القديمة؛ هي بس بتفضل `null` في البيانات اللي مكنتش موجودة وقت ما اتسجلت.

انشرها وعدلها لو محتاج:

```bash
php artisan vendor:publish --tag=activity-tracker-migrations
```

## العمليات اللي بتتسجل

| العملية | المصدر | الـ action المسجل |
|---|---|---|
| `Model::create()` | حدث Eloquent | `created` (مع القيم اللي اتسجلت) |
| `$model->update()` / `save()` | حدث Eloquent | `updated` (مع الفرق) |
| `$model->delete()` | حدث Eloquent | `deleted` (مع القيم وقت الحذف) |
| `$model->restore()` | حدث Eloquent | `restored` (مع الفرق بتاعه) |
| `$model->forceDelete()` | حدث Eloquent | `force_deleted` (مع القيم وقت الحذف) |
| `Model::find()` / `first()` / `firstWhere()` | حدث Eloquent (متجمع) | `retrieved` |
| `Model::get()` / `all()` / `cursor()` | حدث Eloquent (متجمع) | `retrieved_many` |
| `sum()` / `avg()` / `min()` / `max()` | مستمع الاستعلامات | `sum` / `avg` / `min` / `max` |
| `Model::where(...)->update([...])` | مستمع الاستعلامات | `bulk_updated` |
| `Model::where(...)->delete()` | مستمع الاستعلامات | `bulk_deleted` |
| `DB::table(...)->insert()/update()/delete()` | مستمع الاستعلامات | `raw_insert` / `bulk_updated` / `bulk_deleted` |
| فتح الـ subject بتاع نشاط من صفحة تفاصيل النشاط | تسجيل صريح واختياري (`logIntentionalView()`) | `retrieved` (وعليه `metadata.context = "ui"`) |
| Exception مش متمسكة أو اتعمللها report | Exception handler decorator | `exception` — شوف [تتبع الـ Exceptions](#تتبع-الـ-exceptions) |

تقدر تفعّل/تعطل أي واحدة من دول لوحدها تحت `track` في ملف الإعدادات.

### `count()` و`exists()` مش بيتسجلوا خالص

النسخ الأولى كانت بتسجل `count()` و`exists()` عن طريق مستمع الاستعلامات. اتشالوا **خالص** — مش مخفيين من الواجهة، مش متعطلين افتراضيًا، فعليًا اتشالوا من منطق التتبع نفسه. السبب:

- حدث `QueryExecuted` بتاع Laravel أبدًا مش بيديك النتيجة الفعلية (الرقم أو الـ boolean) — شوف [حدود الباكدج](#حدود-الباكدج) — فأقصى حاجة كنا هنقدر نسجلها هي "استعلام count/exists حصل على الجدول ده"، وده مش ليه قيمة تدقيقية تذكر.
- عمليًا، كانوا من أكتر الأنشطة اللي الباكدج بتولّدها حجمًا وأقلها فايدة — أي تطبيق بيعمل عمليات `count()`/`exists()` أكتر بكتير من عمليات الكتابة المفيدة فعلاً.

ده متطبق كقاعدة صارمة جوه `ActivityTrackerManager`، مش معتمد على الإعدادات — مفيش أي مفتاح بيرجعهم تاني.

## استراتيجية الـ Retrieval والقراءات الداخلية

تتبع "retrieved" هو الجزء اللي ممكن يفاجئك أكتر حاجة لو سبته من غير إدارة، لأن Eloquent بيطلق حدث `retrieved` لكل عملية hydration — حتى اللي التطبيق بتاعك مطلبهاش أصلاً. فيه استثناءين بيخلوه ذو معنى:

**1. نظام الـ auth بتاع Laravel نفسه مستثنى افتراضيًا.** أي request بيعدي على middleware اسمه `auth`، أي فحص `Gate`/`can`، وأي نداء لـ `auth()->user()`، كل دول بيحلّوا المستخدم الحالي عن طريق استعلام Eloquent عادي (`Illuminate\Auth\EloquentUserProvider::retrieveById()`). ده ميكانيزم بتاع الفريمورك نفسه بيحصل في تقريبًا كل صفحة فيها مستخدم مسجل دخول في التطبيق كله، مش قراءة عمل حقيقية — فأي موديل متسجل تحت `auth.providers.*.model` (على مستوى كل الـ guards) مستثنى من تتبع `retrieved`/`retrieved_many`:

```php
'retrieval' => [
    // ...
    'exclude_auth_models' => true, // خليها false لو عايز تراقب قراءات تسجيل الدخول كمان
],
```

**2. قراءات اللوحة نفسها مستثناة.** كل controller في الباكدج بيلف الاستعلامات الداخلية بتاعته — تحميل الأنشطة للجدول، الـ subject/causer للعرض، إحصائيات الداشبورد — جوه `TrackingContext::withoutTracking()`:

```php
app(\Abdulbaset\ActivityTracker\Support\TrackingContext::class)->withoutTracking(function () {
    // أي حاجة اتسجلت جوه هنا بتتقفل، وبترجع تشتغل تلقائيًا بعد كده — حتى لو الكود جوه رمى exception.
    $user = User::find($id);
});
```

الآلية دي قابلة للتداخل (nestable)، آمنة مع الـ exceptions، وهي نفس الآلية اللي الباكدج بتستخدمها داخليًا عشان تضمن إن **فتح `/activity-tracker` أبدًا ميولّدش ضجيج تتبع عن نفسه** (شوف [لوحة التحكم](#لوحة-التحكم-dashboard)). استخدمها في الكود بتاعك إنت لأي قراءة مش مفروض تتحسب كحدث عمل.

**3. المشاهدات المتعمدة من الـ UI آلية منفصلة تمامًا — أبدًا مش مستنتجة من hydration بتاع Eloquent.** صفحة تفاصيل النشاط بتنادي:

```php
app(\Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface::class)
    ->logIntentionalView($subjectModel, ['via' => 'activity_details']);
```

مرة واحدة بالظبط لكل ما الصفحة تتفتح، وبتسجل نشاط `retrieved` حقيقي عليه `metadata.context = "ui"`. ده عن قصد منفصل عن الـ listener الأوتوماتيكي (اللي مقفول أصلاً لنفس القراءة دي عن طريق `withoutTracking()` فوق)، فمستحيل يتكرر معاه — وكمان **مش خاضع لاستثناء موديلات الـ auth**، لأن حدث "الأدمن شاف الريكورد ده عن قصد من خلال أداة التدقيق" هو بالظبط النوع اللي يستاهل يتسجل، حتى لو الموديل ده كان هيتستثنى عادي كضجيج auth. تقدر تتحكم فيها بـ `retrieval.track_ui_views`.

**حد صريح لازم تعرفه:** حدث `retrieved` العادي من Eloquent معندوش أي معلومة عن *سبب* القراءة. الاستثناءين اللي فوق بيغطوا أكتر مصدرين شائعين للضجيج (حل الـ auth بتاع الفريمورك، ولوحة التحكم بتاعة الباكدج نفسها)، بس أي قراءة على مستوى التطبيق بيعملها الكود بتاعك إنت لسبب انت شايفه مش مهم (زي middleware أو policy عملتهم إنت) هتتسجل عادي زي أي retrieval تاني لحد ما تلفها إنت بنفسك في `withoutTracking()`. مفيش طريقة عامة وموثوقة تستنتج بيها "القراءة دي كانت مهمة ولا لأ؟" من حدث Eloquent لوحده.

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

فيه `ActivityTrackerQueryClassifier` مخصص بينظّم الـ SQL (الحروف الكبيرة/الصغيرة، المسافات، علامات الاقتباس) قبل ما يصنفه كـ `select`, `insert`, `update`, `delete`, `count`, `exists`, `sum`, `avg`, `min`, `max`, أو `unknown`. استعلامات الـ `select` العادية **مش** بتتسجل مباشرة — أساسًا هي متمثلة في `retrieved`/`retrieved_many`، وتسجيل الاتنين هيكرر كل عملية قراءة. الـ `count` و`exists` بيتصنّفوا فعلاً (المعلومة موجودة في شكل الـ SQL نفسه)، بس أبدًا **مش** بيتحولوا لنشاط — شوف [العمليات اللي بتتسجل](#العمليات-اللي-بتتسجل).

المصنف قابل للتوسعة من غير ما تعمل fork للباكدج:

```php
app(\Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface::class)
    ->extendPattern('/^explain/', 'diagnostic');
```

## المدة الزمنية والأداء

كل نشاط بيتسجل تلقائيًا بيحمل `duration_ms` — مدة زمنية بدقة عالية بالميلي ثانية، متقاسة بأكبر دقة ممكنة حوالين العملية المتتبعة نفسها، مش الـ HTTP request كله:

- **Create/update/delete/restore/force-delete**: متقاسة بـ `hrtime(true)` بين الـ pre-hook بتاع Eloquent (`creating`/`updating`/`deleting`/`restoring`) والـ post-hook المقابل له — يعني أساسًا مدة كتابة الداتابيز نفسها.
- **الـ Aggregates، bulk updates/deletes، الاستعلامات الخام**: بنستخدم `QueryExecuted::$time` بتاع Laravel نفسه مباشرة — هو أصلاً رقم دقيق بالميلي ثانية لنفس الاستعلام ده بالظبط، فمفيش داعي نعمل تايمر تاني من الصفر.
- **`retrieved`/`retrieved_many`**: عن قصد `null`. عملية جلب متجمعة (collection) معندهاش مدة واحدة ذات معنى نقدر نقولها — شوف [تتبع الـ Retrieval](#تتبع-الـ-retrieval).
- **المشاهدات المتعمدة من الـ UI**: `null` — مش عملية متقاسة أصلاً.

```php
'performance' => [
    'enabled' => true,
    'track_duration' => true,

    // memory_get_usage()/memory_get_peak_usage() بتضيف تكلفة بسيطة بس حقيقية
    // لكل عملية متتبعة — متقفولة افتراضيًا.
    'track_memory' => false,

    'slow_ms' => 100,
    'very_slow_ms' => 1000,
],
```

الـ `slow_ms`/`very_slow_ms` بس بيتحكموا في تصنيف Fast/Normal/Slow/Very Slow اللي اللوحة بتعرضه (`Abdulbaset\ActivityTracker\Support\DurationFormatter`) وفلتر "الأنشطة البطيئة بس" — أبدًا مش بيغيروا إيه اللي بيتتبع. اللوحة بتنسّق المدة بذكاء (`0.42 ms`, `845.20 ms`, `1.42 s`) بدل ما تعرض رقم عشري خام.

الباكدج دي أداة audit/observability، مش profiler — رقم المدة والذاكرة (اختياري) موجودين علشان تلاحظ العمليات البطيئة فعلاً، مش علشان يحلّوا محل Blackfire أو Telescope أو أداة APM حقيقية.

## الرابط الكامل، المسار، والـ Referrer

**الرابط الكامل بتاع الـ request** — مش اسم الـ route — هو الحقيقة الأساسية لـ "فين حصلت العملية دي"، لأن الـ route ممكن يتغير اسمه أو يبقى من غير اسم أو closure، بينما الرابط هو ببساطة اللي حصل فعلاً:

```
url:        https://example.com/admin/users/15?tab=permissions
path:       admin/users/15
route_name: admin.users.show   (بيانات ثانوية، لسه بتتسجل)
```

الـ HTTP header اسمه `Referer` (آه، مكتوب غلط أصلاً في مواصفة HTTP نفسها) بيتسجل كـ `referrer_url` لما يكون موجود، وبيفضل `null` — أبدًا مش بنختلقه — لو مش موجود. الاتنين `url` و`referrer_url`:

- بيتعمللهم **إخفاء لأي query parameters حساسة** قبل التخزين (`token`, `password`, `api_key`, `secret`, `access_token`, `refresh_token`, `client_secret`, `signature` افتراضيًا — وسّعها عن طريق `sensitive_query_parameters`): يعني `?token=abc123` بتبقى `?token=[REDACTED]`، وباقي الرابط زي ما هو.
- بيتعمللهم **قطع (truncation)** عند طول محدد (`context.max_url_length`, `context.max_referrer_length` — الاتنين افتراضيًا 2048)، لأن الاتنين مدخلات غير موثوقة ممكن المهاجم يتحكم فيها.
- بيتعمللهم **escape وقت العرض** في كل مكان اللوحة بتعرضهم فيه (نفس الـ escaping الافتراضي بتاع Blade `{{ }}`) — أبدًا مش بيتعرضوا كـ HTML خام، ولا بيتنفذوا.

### كود حالة الـ HTTP (Status Code)

معظم الأنشطة بتتسجل *وسط الـ request*، قبل ما الـ response — وبالتالي كود الحالة — يكون موجود أصلاً. الـ `http_status` بيتعمله backfill مرة واحدة، بعد ما الـ response يتبعت فعليًا، عن طريق `ActivityTrackerRequestLifecycleMiddleware::terminate()` (متسجل على الـ middleware stack العام بتاع Laravel، مش بس group الـ "web"، فبيغطي حتى تطبيقات الـ API الخالصة): استعلام `UPDATE` واحد بس، بيتقفل تمامًا لو الـ request مفيهوش أي حاجة اتسجلت أصلاً. الـ exceptions اللي معاها كود حالة من نفسها (زي `HttpException` مرمية) بتسجل الكود فورًا؛ أي حاجة تانية بتاخد كود الـ response الحقيقي بعد لحظة.

## تتبع الـ Exceptions

الـ exceptions اللي مش متمسكة أو اتعمللها report بتتسجل كـ نشاط مخصص اسمه `exception` — أبدًا مش متنكرة كأنها عملية CRUD، ودايمًا واضحة بصريًا في اللوحة (badge أحمر مخصص، وقسم كامل ليها في صفحة التفاصيل).

### إزاي بتتوصل بـ Laravel

`ActivityTrackerExceptionHandlerDecorator` بيلف (عن طريق `Container::extend()`) أي حاجة متسجلة أصلاً كـ `Illuminate\Contracts\Debug\ExceptionHandler` — الـ Handler المخصص بتاعك إنت، أو الافتراضي بتاع Laravel. أبدًا مش بيستبدله:

- `report()`, `shouldReport()`, `render()`, و`renderForConsole()` كلهم بيتحولوا للـ handler الأصلي من غير تغيير — منطق `render()`/`register()` المخصص بتاعك لسه شغال بالظبط زي ما كان.
- تسجيل الـ exception ملفوف في `try`/`catch` خاص بيه جوه `ActivityTrackerExceptionService`: لو بناء أو تخزين النشاط فشل لأي سبب، الـ exception *الأصلي* لسه بيوصل لـ `$handler->report($e)` عادي. فشل الـ tracker أبدًا ميقدرش يستبدل أو يقفل معالجة الخطأ الحقيقية بتاعة تطبيقك.
- نفس الـ exception instance أبدًا مش بيتسجل مرتين — الـ deduplication بيبقى عن طريق الـ object identity (`spl_object_id()`)، مش عن طريق الرسالة أو محتوى الـ trace (اللي ممكن exceptions مختلفة تمامًا تشتركوا فيهم).

### إيه اللي بيتسجل

`exception_class`, `exception_message`, `exception_file`, `exception_line`، وبشكل قابل للتحكم `stack_trace`، بالإضافة لنفس بيانات الـ request اللي أي نشاط تاني بياخدها (`url`, `execution_context`, `request_id`, الـ causer، إلخ) و`http_status` مستنتج فورًا لو الـ exception نفسها معاها كود حالة (`Symfony\...\HttpExceptionInterface`)، أو بيتعمله backfill زي أي نشاط تاني لو مش كده.

### الـ Exceptions المستثناة ("المتوقعة")

من غير فلترة، **كل** محاولة تسجيل دخول فاشلة وكل route مش موجود هيعملوا نشاط exception — نفس مشكلة الضجيج اللي خلتنا نشيل تتبع `count()`/`exists()`. دول مستثنيين افتراضيًا:

```php
'exceptions' => [
    'enabled' => true,
    'store_trace' => true,
    'max_trace_length' => 10000,
    'ignored_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    ],
],
```

ضيف أو شيل كلاسات زي ما تحب (الكلاسات الفرعية بتتفحص عن طريق `instanceof`).

### أمان الـ Stack Trace — اقرأ ده قبل ما تفعّله في الإنتاج

الـ `store_trace` مفعّل افتراضيًا (`true`) وناتج `getTraceAsString()` بيتقطع عند `max_trace_length` (10,000 حرف) — بس **الشكل الافتراضي بتاع PHP للـ stack trace ممكن يحتوي على قيم scalar فعلية اتبعتت كـ arguments** لدوال في سلسلة الاستدعاء. لو باسورد أو توكن اتبعت كـ string عادي كـ argument في أي حتة في السلسلة دي، ممكن يظهر في الـ trace. ده سلوك أساسي في PHP/Laravel نفسه، مش حاجة نقدر نمسحها بشكل انتقائي من نص الـ trace بعد ما يتكتب. للتطبيقات الحساسة جدًا، حط `'store_trace' => false` — الـ class/message/file/line لسه بيتسجلوا بالكامل في الحالتين.

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

### سياق الـ Job

لما أي عملية متتبعة تحصل وقت ما queue job شغال، الـ `execution_context` بيبقى `"queue"` والأعمدة دي بتتسجل مباشرة من الـ job اللي Laravel بعتها لـ `JobProcessing`:

```
job_name:         App\Jobs\SyncUserOrders   (كلاس الـ job نفسه)
queue_name:       default
queue_connection: redis
queue_attempt:    1
```

زي أي حاجة تانية، ده بيتصفّر بين كل job وتاني — مفيش سياق job يقدر يسرب للـ job اللي بعده في نفس الـ worker process.

ملاحظة: الـ `sync` queue connection (الافتراضي في Laravel لو مفيش حاجة تانية متظبطة) أبدًا مش بيطلق `JobProcessing`/`JobProcessed` خالص — الـ jobs بتشتغل مباشرة من غير ما تعدي على worker حقيقي — فالـ `execution_context` للعمليات المتتبعة جوه job متبعت بـ `sync` هيفضل زي ما كان قبل كده (غالبًا `"http"`، لأن jobs الـ `sync` عادة بتشتغل وسط الـ request). ده سلوك بتاع Laravel نفسه، مش قصور في الباكدج.

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

صفحة الأنشطة فيها خانة بحث واحدة (الوصف، الـ action، نوع الـ subject/الـ ID بتاعه، الـ causer ID، الـ IP، الـ route، request ID، batch ID)، وپانل فلاتر قابل للتوسيع (اختيار متعدد للـ actions، نوع الـ subject، الـ causer، مدى تاريخي، IP، HTTP method، الـ route، request ID، batch ID) وعليه مؤشر "N نشط" بيتحدث لحظيًا، وترتيب للأعمدة — شامل `id`, `created_at`, `action`, `subject_type`, `subject_id`, و`causer`. الأعمدة القابلة للترتيب وأحجام الصفحات محددة مسبقًا (whitelist) من جوه السيرفر (`Abdulbaset\ActivityTracker\Services\ActivityTrackerFilters`) — قيم الـ query string الخام مش بتوصل لـ `orderBy()` مباشرة أبدًا، وأي قيمة `sort` غريبة بترجع تلقائي لـ `created_at`. الفلاتر بتفضل موجودة لما تتنقل بين الصفحات لأنها أساسًا مجرد query string parameters.

صفحة تفاصيل النشاط فيها لينكات بتوديك مباشرة لنفس الجدول بس مفلتر على batch/request (`?batch_id=...` / `?request_id=...`) علشان تشوف كل اللي حصل في request واحد أو عملية مترابطة واحدة.

### سلوك الـ AJAX

جدول الأنشطة بيتحمّل ويتحدّث عن طريق `XMLHttpRequest` — البحث، كل فلتر، الترتيب، الصفحات، واختيار عدد النتائج في الصفحة، كلهم بيحدّثوا الجدول في مكانه من غير أي إعادة تحميل كاملة للصفحة:

- **البحث فيه debounce** (400 مللي ثانية) وانت بتكتب؛ الضغط على Enter أو زرار "Search" بيبعت الطلب فورًا.
- **الطلبات متسلسلة، مش بس متبعوتة** — أي طلب جديد بيلغي أي طلب لسه شغال، فمستحيل رد قديم يجيلك بعد رد أحدث منه ويكتب فوقه.
- **الرابط بيعكس حالة الفلاتر** عن طريق `history.pushState()` (تغيير فلتر/بحث) أو `history.replaceState()` (صفحات/ترتيب — بره تاريخ التصفح عشان مايتكدسش)، فزرار الرجوع/التقدم في المتصفح، وكمان نسخ ولزق الرابط، كلهم شغالين، والـ `popstate` بيرجّع الصفحة الصح من غير ما يضيف سجل جديد في التاريخ.
- **حالة التحميل** عبارة عن spinner صغير فوق الجدول القديم (اللي فاضل ظاهر بس باهت شوية) — أبدًا مش شاشة تحميل كاملة — والنتائج القديمة فاضلة على الشاشة لحد ما الجديدة تجهز.
- **الأخطاء** بتستبدل منطقة النتائج برسالة "Unable to load activities. Please try again." وزرار Retry؛ أي exception خام أبدًا مش بيوصل للمتصفح.
- **الرجوع للسلوك العادي لو الـ JS متعطل**: كل عنصر تحكم هو `<a>`/`<form>` حقيقي بـ `href`/`action` حقيقي من الأساس. الـ JavaScript بيلقط الضغطة/الإرسال عشان يعمل السلوك اللي فوق؛ لو الـ JavaScript متقفل، كل واحد فيهم لسه شغال زي أي تنقل عادي على نفس الـ route المسمّى.

الـ endpoint هو نفس الـ route المسمّى بتاع الصفحة نفسها (`activity-tracker.activities.index`) — `$request->ajax()` بتاع Laravel (اللي بيعتمد على الـ header `X-Requested-With: XMLHttpRequest` اللي المتصفح بيحطه لوحده مع `XMLHttpRequest`) هو اللي بيحدد إذا كان الرد JSON:

```json
{
    "success": true,
    "data": {
        "html": "<div class=\"at-table-wrap\">...</div>",
        "total": 8421,
        "hasActiveFilters": true
    }
}
```

مفيش أي رابط متكتوب يدوي جوه الـ JavaScript — الـ container بتاع النتائج شايل رابط الـ index route في attribute اسمه `data-at-index-url`، وأي لينك بيلقطه الـ JS أصلاً معاه `href` حقيقي متولّد من `route(...)` في الـ Blade.

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

فيه زرار في الشريط العلوي بيبدّل بين الوضع الفاتح والغامق، ومتخزن في `localStorage` (من غير أي إعداد في الداتابيز). `ui.theme` بيتحكم في التفضيل الافتراضي لأول زيارة: `'light'`, `'dark'`, أو `'system'` (بيتبع تفضيل نظام التشغيل/المتصفح عن طريق `prefers-color-scheme`). الأنيميشن (تحديث الصفوف، فتح/قفل پانل الفلاتر، الـ toasts) خفيف ومبني على CSS transitions، وبيتقلل تلقائيًا لو المستخدم مفعّل `prefers-reduced-motion: reduce` في نظام التشغيل — من غير ما يأثر على أي وظيفة.

### عزل الـ JavaScript والـ CSS

كل الـ JavaScript بتاع الباكدج تحت متغير global واحد بس، `window.ActivityTracker` — مفيش أي حاجة تانية بتتضاف لـ `window`. كل كلاسات الـ CSS مبدوءة بـ `.at-` (`.at-card`, `.at-table`, `.at-btn`, ...) وكل صفحة ملفوفة جوه container واحد اسمه `.at-scope`، فاللوحة مستحيل تتعارض مع أو تكتب فوق الـ Bootstrap أو Tailwind أو أي كلاسات `.card`/`.table`/`.button`/`.modal` بتاعة التطبيق المضيف نفسه على نفس الدومين.

## تسمية الكلاسات

كل كلاس خاص بالباكدج اتسمى بطريقة تخليك تعرف إنه بتاعها من أول نظرة في stack trace أو سطر لوج — مش لأن فيه تعارض حقيقي في الـ namespace بتاع PHP (مفيش أصلاً)، بس لأن أسامي زي "ActivityController" أو "ActivityService" شكلها عام جدًا ممكن تبقى بتاعة أي تطبيق:

| الدور | الكلاس |
|---|---|
| متخذ قرار التتبع المركزي | `Services\ActivityTrackerManager` |
| كنترولر الأنشطة/التفاصيل | `Http\Controllers\ActivityTrackerActivityController` |
| كنترولر نظرة اللوحة العامة | `Http\Controllers\ActivityTrackerDashboardController` |
| كنترولر صفحة الإحصائيات | `Http\Controllers\ActivityTrackerStatisticsController` |
| كنترولر CSS/JS بتاع اللوحة | `Http\Controllers\ActivityTrackerAssetController` |
| البحث/الفلترة/الترتيب/الصفحات | `Services\ActivityTrackerFilters` |
| استعلامات إحصائيات اللوحة | `Services\ActivityTrackerStatisticsService` |
| طبقة تخزين الأنشطة | `Services\ActivityTrackerRepository` |
| تصنيف الـ SQL | `Services\ActivityTrackerQueryClassifier` |
| مستمع أحداث `eloquent.*` | `Observers\ActivityTrackerObserver` |
| مستمع `QueryExecuted` | `Listeners\ActivityTrackerQueryListener` |
| تجميع الـ retrieval وتفريغها كنشاط | `Services\ActivityTrackerRetrievalFlusher` |

فيه كام كلاس عن قصد سايبينهم بأسمائهم القصيرة، لأننا شفنا إن إضافة بادئة ليهم هتزود ضجة من غير ما تزود وضوح فعلي:

- **`Models\Activity`** — ده الـ API العام بتاع الباكدج (`Activity::query()->...`, متوثق بالتفصيل فوق)؛ تغيير اسمه هيبقى breaking change من غير أي فايدة توضيح حقيقية جوه باكدج أصلاً موضوعها "تتبع الأنشطة".
- **`Support\TrackingContext`, `CauserResolver`, `RequestContextResolver`, `Services\SensitiveDataSanitizer`, `Services\ActivityTransformer`** — كلاسات مساعدة داخلية ضيقة النطاق، واضحة أصلاً من السياق، ومحدش بيستخدمها مباشرة من كود التطبيق.
- **الـ Contracts (`ActivityLoggerInterface`, `QueryClassifierInterface`, ...)، والـ Events، والـ job بتاع الـ queue (`Jobs\StoreActivity`)، وأوامر الـ Console** — واضحة أصلاً من الـ namespace بتاعها ومن أسمائها المبنية على السلوك.

أسامي الـ routes (`activity-tracker.*`)، namespace الـ views (`activity-tracker::...`)، ومفتاح الإعدادات (`activity-tracker`) كانوا متسقين من الأول ولسه زي ما هم.

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

- **`result_count` للأنشطة الجاية من مستمع الاستعلامات مش متوفر دايمًا.** حدث `QueryExecuted` بتاع Laravel بيدّي بس الـ SQL، الـ bindings، والتوقيت — مش النتيجة الفعلية للاستعلام ولا عدد الصفوف المتأثرة. أنشطة `sum`/`avg`/`min`/`max`, `bulk_updated`, و`bulk_deleted` بتسجل *إن* العملية حصلت وعلى أي جدول، بس الرقم الفعلي مش متسجل (وده بالظبط سبب إننا شلنا `count()` و`exists()` خالص بدل ما نسجلهم برقم فاضي دايمًا — شوف [العمليات اللي بتتسجل](#العمليات-اللي-بتتسجل)). `retrieved`/`retrieved_many` هما الاستثناء — الـ `result_count` بتاعهم دقيق تمامًا لأنه جاي من عملية hydration بتاعة Eloquent نفسها، مش من مستمع الاستعلامات.
- **حدث `retrieved` العادي من Eloquent معندوش أي معلومة عن نية القراءة.** الباكدج بتستثني أكتر مصدرين شائعين لضجيج القراءة الوهمي (حل الـ auth بتاع Laravel نفسه، وقراءات اللوحة نفسها) — شوف [استراتيجية الـ Retrieval والقراءات الداخلية](#استراتيجية-الـ-retrieval-والقراءات-الداخلية) — بس مش هتعرف بشكل عام إن قراءة تانية بيعملها كود التطبيق بتاعك مش مهمة بالنسبالك. لفها إنت بنفسك في `TrackingContext::withoutTracking()` لو كده.
- **الاستعلامات الخام اللي على جدول بس مش ممكن تترابط بموديل Eloquent.** `DB::table('users')->sum('balance')` بيطلع `model_type = null, table = users` بالتصميم — الباكدج مش هتحاول تخمن أي موديل بيمثل الجدول.
- **مش ممكن تفرق بين update/delete جماعي عن طريق query builder وبين استدعاء `DB::table()` مماثل.** الاتنين بينتجوا نفس الـ SQL بالظبط. الاتنين بيتسجلوا كـ `bulk_updated`/`bulk_deleted` على الجدول.
- **الـ bindings مش بتتخزن افتراضيًا**، ولو فعّلتها، إخفاء القيم الحساسة فيها heuristic بسيط (نصوص طويلة من غير مسافات)، مش ضمان مؤكد — مفيش اسم عمود موثوق تتقارن بيه مع `sensitive_columns` على مستوى الـ binding.
- **الـ rollback في الـ transactions مش مضمون 100% إنه يمنع كتابة نشاط متبعت للـ queue** — شوف [الـ Transactions](#الـ-transactions).
- **اللوحة مش هتعرض حاجة الـ engine مسجلهاش أصلاً.** صفحة تفاصيل النشاط بتعرض "Not captured" للنتائج التجميعية وعدد الصفوف المتأثرة بدل ما تختلق رقم، للأسباب اللي فوق. وكمان أبدًا مش بتخمن لينك لصفحة الموديل بتاعة تطبيقك — لو عايز كده، ضيفه في نسخة منشورة ومعدلة من الـ view.
- **الـ backfill بتاع `http_status` هو best-effort، مش لحظي.** بيتحدث بعد ما الـ response يتبعت، محدد بـ `request_id`، عن طريق middleware قابل للإنهاء (terminable). عملية اتقفلت بشكل غير طبيعي (خطأ فادح الـ handler مشافوش، `exit()` اتنادى وسط الـ request، worker اتقتل) ممكن تسيب `http_status` قيمته `null` للأنشطة بتاعة الـ request ده — وده أحسن بكتير من إننا نخمن كود حالة أصلاً مااتوصلش له.
- **الـ stack traces ممكن تحتوي على قيم scalar من سلسلة الاستدعاء** — شوف [تتبع الـ Exceptions § أمان الـ Stack Trace](#تتبع-الـ-exceptions) قبل ما تفعّل `store_trace` لتطبيق حساس جدًا.

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

مجموعة الاختبارات بتغطي: create/update/delete/restore/force-delete، find/first/get، الـ aggregates، bulk update/delete، عمليات `DB::table()` الخام، الموديلات المستثناة، حذف الحقول الحساسة، الحماية من التكرار اللانهائي، وتجميع/تفريغ عمليات الـ retrieval؛ اختبارات صريحة بتأكد إن `count()`/`exists()` بيسجلوا صفر نشاط حتى لو حد فعّلهم غصب عن الإعدادات؛ استثناء موديلات الـ auth (وإنه ممكن يتعطل)؛ الـ nesting والأمان مع الـ exceptions بتاع `withoutTracking()`؛ وآلية المشاهدة المتعمدة من الـ UI اللي بتسجل مرة واحدة بالظبط من غير ما تتكرر مع الـ listener الأوتوماتيكي المقفول؛ تسجيل المدة الزمنية (موجودة، رقمية، موجبة، قابلة للتعطيل، وغائبة صح لـ retrieved/retrieved_many)؛ تسجيل الرابط الكامل/query string/المسار/الـ route عن طريق HTTP requests حقيقية، وإخفاء الـ query parameters الحساسة في الاتنين `url` و`referrer_url`، وقطع الـ referrer الطويل، وbackfill بتاع `http_status` بعد ما الـ response يتبعت؛ سياق الـ job (job_name/queue_name/queue_connection/queue_attempt) وإنه مبيسربش بين job وتاني؛ ونظام الـ exceptions بالكامل — تسجيل الـ class/message/file/line/trace، قطع وتعطيل الـ trace، قائمة الـ exceptions المستثناة الافتراضية (وإنها قابلة للتعديل)، منع التكرار عن طريق object identity لو نفس الـ exception اتعمللها report مرتين، استنتاج كود الحالة من `HttpExceptionInterface`، وإن تعطيل تتبع الـ exceptions أبدًا مايوقفش الـ handler *الأصلي* من إنه يشتغل — بالإضافة، بالنسبة للوحة التحكم: التحكم في الوصول (الـ Gate الافتراضي، الـ Gate المخصص، مفتاح `authorize`)، إمكانية إلغاء اللوحة بالكامل (`ui.enabled = false` بيلغي الـ routes بتاعتها)، البحث، كل فلتر، الترتيب بالـ ID وباقي الأعمدة مع اختبار whitelist ضد مدخلات خبيثة، الصفحات، endpoint الـ AJAX، العرض المفلتر على batch/request، التعامل الآمن مع subject أو causer محذوف في صفحة التفاصيل، وإثبات end-to-end إن فتح كل صفحات اللوحة مايولّدش أي نشاط غير المشاهدة المتعمدة الواحدة بس.

## المساهمة

الـ issues والـ pull requests مرحب بيها. من فضلك شغّل `composer stan` و`composer format` قبل ما تبعت، وضيف اختبارات لأي سلوك جديد.

## الرخصة

MIT. شوف [LICENSE](LICENSE).
