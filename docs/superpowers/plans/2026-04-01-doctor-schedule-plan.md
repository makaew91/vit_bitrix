# Doctor Schedule Module (vit.schedule) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a Bitrix module for managing a doctor's weekly schedule with an admin interface and a public component displaying available time slots.

**Architecture:** Custom DB table + D7 ORM DataManager for schedule storage (7 rows = 7 days). Module provides admin page (CAdminTabControl) for editing the weekly schedule. Public component reads schedule via ORM and generates time slots using SlotGenerator.

**Tech Stack:** PHP 8.1+, 1C-Bitrix D7 ORM, CAdminTabControl, COption, Bitrix component architecture.

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `local/modules/vit.schedule/install/index.php` | Create | Module installer: create/drop table, register module |
| `local/modules/vit.schedule/install/db/install.sql` | Create | SQL: CREATE TABLE + INSERT 7 default rows |
| `local/modules/vit.schedule/install/db/uninstall.sql` | Create | SQL: DROP TABLE |
| `local/modules/vit.schedule/include.php` | Create | Module autoload registration |
| `local/modules/vit.schedule/lib/ScheduleTable.php` | Create | D7 ORM DataManager for vit_schedule |
| `local/modules/vit.schedule/lib/SlotGenerator.php` | Create | Generate time slots from schedule row |
| `local/modules/vit.schedule/admin/vit_schedule_settings.php` | Create | Admin page: edit weekly schedule |
| `local/modules/vit.schedule/options.php` | Create | Module settings page (slot duration) |
| `local/modules/vit.schedule/admin/menu.php` | Create | Admin menu item registration |
| `local/components/vit/schedule.slots/class.php` | Create | Public component logic |
| `local/components/vit/schedule.slots/templates/.default/template.php` | Create | Public component template |
| `local/components/vit/schedule.slots/templates/.default/style.css` | Create | Minimal component styles |
| `README.md` | Create | Architecture description for submission |

---

### Task 1: Module Installer + SQL

**Files:**
- Create: `local/modules/vit.schedule/install/index.php`
- Create: `local/modules/vit.schedule/install/db/install.sql`
- Create: `local/modules/vit.schedule/install/db/uninstall.sql`

- [ ] **Step 1: Create install.sql**

```sql
CREATE TABLE IF NOT EXISTS vit_schedule (
    ID INT NOT NULL AUTO_INCREMENT,
    DAY_OF_WEEK INT NOT NULL,
    IS_WORKING CHAR(1) NOT NULL DEFAULT 'N',
    TIME_FROM VARCHAR(5) DEFAULT NULL,
    TIME_TO VARCHAR(5) DEFAULT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_day (DAY_OF_WEEK)
);

INSERT INTO vit_schedule (DAY_OF_WEEK, IS_WORKING, TIME_FROM, TIME_TO) VALUES
(1, 'Y', '09:00', '18:00'),
(2, 'Y', '09:00', '18:00'),
(3, 'Y', '09:00', '18:00'),
(4, 'Y', '09:00', '18:00'),
(5, 'Y', '09:00', '18:00'),
(6, 'N', NULL, NULL),
(7, 'N', NULL, NULL);
```

- [ ] **Step 2: Create uninstall.sql**

```sql
DROP TABLE IF EXISTS vit_schedule;
```

- [ ] **Step 3: Create install/index.php**

```php
<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\IO\Directory;

class vit_schedule extends CModule
{
    public $MODULE_ID = 'vit.schedule';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_VERSION_DATE = '2026-04-01';
        $this->MODULE_NAME = 'Расписание врача';
        $this->MODULE_DESCRIPTION = 'Управление рабочим расписанием врача';
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
    }

    public function DoUninstall(): void
    {
        $this->UnInstallDB();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB(): void
    {
        $connection = Application::getConnection();
        $sqlFile = __DIR__ . '/db/install.sql';
        $sql = file_get_contents($sqlFile);

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn(string $s) => $s !== ''
        );

        foreach ($statements as $statement) {
            $connection->queryExecute($statement);
        }
    }

    public function UnInstallDB(): void
    {
        $connection = Application::getConnection();
        $sqlFile = __DIR__ . '/db/uninstall.sql';
        $sql = file_get_contents($sqlFile);
        $connection->queryExecute(trim($sql, "; \n\r\t"));
    }

    public function InstallFiles(): void
    {
        CopyDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
    }

    public function UnInstallFiles(): void
    {
        DeleteDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add local/modules/vit.schedule/install/
git commit -m "feat: add module installer with SQL schema and seed data"
```

---

### Task 2: Module Include + D7 ORM

**Files:**
- Create: `local/modules/vit.schedule/include.php`
- Create: `local/modules/vit.schedule/lib/ScheduleTable.php`

- [ ] **Step 1: Create include.php**

```php
<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vit.schedule', [
    'Vit\\Schedule\\ScheduleTable' => 'lib/ScheduleTable.php',
    'Vit\\Schedule\\SlotGenerator' => 'lib/SlotGenerator.php',
]);
```

- [ ] **Step 2: Create ScheduleTable.php**

```php
<?php

namespace Vit\Schedule;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;

class ScheduleTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'vit_schedule';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new IntegerField('DAY_OF_WEEK', [
                'required' => true,
            ]),
            new StringField('IS_WORKING', [
                'required' => true,
                'default_value' => 'N',
                'validation' => function () {
                    return [new LengthValidator(1, 1)];
                },
            ]),
            new StringField('TIME_FROM', [
                'validation' => function () {
                    return [new LengthValidator(null, 5)];
                },
            ]),
            new StringField('TIME_TO', [
                'validation' => function () {
                    return [new LengthValidator(null, 5)];
                },
            ]),
        ];
    }

    public static function getDayNames(): array
    {
        return [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
    }

    public static function getByDayOfWeek(int $dayOfWeek): ?array
    {
        $row = self::getList([
            'filter' => ['=DAY_OF_WEEK' => $dayOfWeek],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add local/modules/vit.schedule/include.php local/modules/vit.schedule/lib/ScheduleTable.php
git commit -m "feat: add module autoload and D7 ORM ScheduleTable"
```

---

### Task 3: SlotGenerator

**Files:**
- Create: `local/modules/vit.schedule/lib/SlotGenerator.php`

- [ ] **Step 1: Create SlotGenerator.php**

```php
<?php

namespace Vit\Schedule;

use Bitrix\Main\Config\Option;

class SlotGenerator
{
    public static function getSlotDuration(): int
    {
        return (int) Option::get('vit.schedule', 'slot_duration', '20');
    }

    public static function generateSlots(string $timeFrom, string $timeTo, int $duration = 0): array
    {
        if ($duration <= 0) {
            $duration = self::getSlotDuration();
        }

        $slots = [];
        $current = strtotime($timeFrom);
        $end = strtotime($timeTo);

        while ($current + $duration * 60 <= $end) {
            $slots[] = date('H:i', $current);
            $current += $duration * 60;
        }

        return $slots;
    }

    public static function getSlotsForDate(string $date): array
    {
        $dayOfWeek = self::getDayOfWeek($date);

        $row = ScheduleTable::getByDayOfWeek($dayOfWeek);

        if (!$row || $row['IS_WORKING'] !== 'Y') {
            return [];
        }

        return self::generateSlots($row['TIME_FROM'], $row['TIME_TO']);
    }

    /**
     * Convert PHP date('N') (1=Mon..7=Sun) to our DAY_OF_WEEK.
     * They match: ISO-8601 numeric day of week, 1=Monday.
     */
    public static function getDayOfWeek(string $date): int
    {
        return (int) date('N', strtotime($date));
    }

    public static function getWeekSchedule(string $date): array
    {
        $timestamp = strtotime($date);
        $currentDayOfWeek = (int) date('N', $timestamp);
        $mondayTimestamp = strtotime('-' . ($currentDayOfWeek - 1) . ' days', $timestamp);

        $week = [];
        $dayNames = ScheduleTable::getDayNames();

        $allDays = ScheduleTable::getList([
            'order' => ['DAY_OF_WEEK' => 'ASC'],
        ])->fetchAll();

        $scheduleByDay = [];
        foreach ($allDays as $row) {
            $scheduleByDay[$row['DAY_OF_WEEK']] = $row;
        }

        for ($i = 0; $i < 7; $i++) {
            $dayTimestamp = strtotime('+' . $i . ' days', $mondayTimestamp);
            $dayNum = $i + 1;
            $dayDate = date('Y-m-d', $dayTimestamp);

            $row = $scheduleByDay[$dayNum] ?? null;
            $isWorking = $row && $row['IS_WORKING'] === 'Y';

            $week[] = [
                'day_of_week' => $dayNum,
                'name' => $dayNames[$dayNum],
                'short_name' => mb_substr($dayNames[$dayNum], 0, 2),
                'date' => $dayDate,
                'date_formatted' => date('d.m', $dayTimestamp),
                'is_working' => $isWorking,
                'is_current' => $dayDate === $date,
                'slots' => $isWorking
                    ? self::generateSlots($row['TIME_FROM'], $row['TIME_TO'])
                    : [],
            ];
        }

        return $week;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add local/modules/vit.schedule/lib/SlotGenerator.php
git commit -m "feat: add SlotGenerator for time slot calculation"
```

---

### Task 4: Admin Page

**Files:**
- Create: `local/modules/vit.schedule/admin/vit_schedule_settings.php`
- Create: `local/modules/vit.schedule/admin/menu.php`

- [ ] **Step 1: Create admin/vit_schedule_settings.php**

```php
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Vit\Schedule\ScheduleTable;

Loader::includeModule('vit.schedule');

/** @var CMain $APPLICATION */
$APPLICATION->SetTitle('Расписание врача');

$message = null;
$dayNames = ScheduleTable::getDayNames();

// Save handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $errors = [];

    for ($day = 1; $day <= 7; $day++) {
        $isWorking = ($_POST['IS_WORKING'][$day] ?? 'N') === 'Y' ? 'Y' : 'N';
        $timeFrom = trim($_POST['TIME_FROM'][$day] ?? '');
        $timeTo = trim($_POST['TIME_TO'][$day] ?? '');

        if ($isWorking === 'Y') {
            if ($timeFrom === '' || $timeTo === '') {
                $errors[] = $dayNames[$day] . ': укажите время начала и окончания';
                continue;
            }
            if ($timeFrom >= $timeTo) {
                $errors[] = $dayNames[$day] . ': время начала должно быть раньше окончания';
                continue;
            }
        }

        $row = ScheduleTable::getByDayOfWeek($day);
        if ($row) {
            ScheduleTable::update($row['ID'], [
                'IS_WORKING' => $isWorking,
                'TIME_FROM' => $isWorking === 'Y' ? $timeFrom : null,
                'TIME_TO' => $isWorking === 'Y' ? $timeTo : null,
            ]);
        }
    }

    if (!empty($errors)) {
        $message = new CAdminMessage([
            'MESSAGE' => implode('<br>', $errors),
            'TYPE' => 'ERROR',
        ]);
    } else {
        $message = new CAdminMessage([
            'MESSAGE' => 'Расписание сохранено',
            'TYPE' => 'OK',
        ]);
    }
}

// Load current data
$schedule = [];
$rows = ScheduleTable::getList(['order' => ['DAY_OF_WEEK' => 'ASC']])->fetchAll();
foreach ($rows as $row) {
    $schedule[$row['DAY_OF_WEEK']] = $row;
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$tabControl = new CAdminTabControl('tabSchedule', [
    ['DIV' => 'schedule', 'TAB' => 'Расписание', 'TITLE' => 'Настройка рабочей недели'],
]);

if ($message) {
    echo $message->Show();
}
?>

<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>

    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>

    <tr class="heading">
        <td colspan="2">Рабочие дни и время приёма</td>
    </tr>

    <?php for ($day = 1; $day <= 7; $day++): ?>
        <?php
        $row = $schedule[$day] ?? null;
        $isWorking = $row && $row['IS_WORKING'] === 'Y';
        $timeFrom = $row['TIME_FROM'] ?? '09:00';
        $timeTo = $row['TIME_TO'] ?? '18:00';
        ?>
        <tr>
            <td width="30%" style="vertical-align: middle;">
                <label>
                    <input type="hidden" name="IS_WORKING[<?= $day ?>]" value="N">
                    <input
                        type="checkbox"
                        name="IS_WORKING[<?= $day ?>]"
                        value="Y"
                        <?= $isWorking ? 'checked' : '' ?>
                        onchange="toggleTimeInputs(this, <?= $day ?>)"
                    >
                    <strong><?= $dayNames[$day] ?></strong>
                </label>
            </td>
            <td width="70%">
                <input
                    type="time"
                    name="TIME_FROM[<?= $day ?>]"
                    id="time_from_<?= $day ?>"
                    value="<?= htmlspecialcharsbx($timeFrom) ?>"
                    <?= !$isWorking ? 'disabled' : '' ?>
                >
                &mdash;
                <input
                    type="time"
                    name="TIME_TO[<?= $day ?>]"
                    id="time_to_<?= $day ?>"
                    value="<?= htmlspecialcharsbx($timeTo) ?>"
                    <?= !$isWorking ? 'disabled' : '' ?>
                >
            </td>
        </tr>
    <?php endfor; ?>

    <?php
    $tabControl->Buttons([
        'btnSave' => true,
        'btnApply' => false,
        'btnCancel' => false,
    ]);
    ?>

    <?php $tabControl->End(); ?>
</form>

<script>
function toggleTimeInputs(checkbox, day) {
    document.getElementById('time_from_' + day).disabled = !checkbox.checked;
    document.getElementById('time_to_' + day).disabled = !checkbox.checked;
}
</script>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
```

- [ ] **Step 2: Create admin/menu.php**

```php
<?php

use Bitrix\Main\Loader;

if (Loader::includeModule('vit.schedule')) {
    $aModuleMenu = [
        [
            'parent_menu' => 'global_menu_services',
            'sort' => 500,
            'text' => 'Расписание врача',
            'title' => 'Управление расписанием врача',
            'url' => 'vit_schedule_settings.php',
            'icon' => 'util_menu_icon',
            'items_id' => 'vit_schedule',
        ],
    ];

    return $aModuleMenu;
}

return [];
```

- [ ] **Step 3: Commit**

```bash
git add local/modules/vit.schedule/admin/
git commit -m "feat: add admin page for weekly schedule management"
```

---

### Task 5: Module Options Page

**Files:**
- Create: `local/modules/vit.schedule/options.php`

- [ ] **Step 1: Create options.php**

```php
<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::includeModule('vit.schedule');

/** @var string $mid — module ID, set by Bitrix */
$mid = 'vit.schedule';

$slotOptions = [
    '15' => '15 минут',
    '20' => '20 минут',
    '30' => '30 минут',
    '45' => '45 минут',
    '60' => '60 минут',
];

// Save handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $slotDuration = $_POST['slot_duration'] ?? '20';

    if (array_key_exists($slotDuration, $slotOptions)) {
        Option::set($mid, 'slot_duration', $slotDuration);
    }
}

$currentDuration = Option::get($mid, 'slot_duration', '20');

$tabControl = new CAdminTabControl('tabOptions', [
    ['DIV' => 'settings', 'TAB' => 'Настройки', 'TITLE' => 'Настройки модуля'],
]);
?>

<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>

    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td width="40%">Длительность слота:</td>
        <td width="60%">
            <select name="slot_duration">
                <?php foreach ($slotOptions as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $value === $currentDuration ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>

    <?php
    $tabControl->Buttons([
        'btnSave' => true,
        'btnApply' => false,
        'btnCancel' => false,
    ]);
    ?>

    <?php $tabControl->End(); ?>
</form>
<?php
```

- [ ] **Step 2: Commit**

```bash
git add local/modules/vit.schedule/options.php
git commit -m "feat: add module options page with slot duration setting"
```

---

### Task 6: Public Component

**Files:**
- Create: `local/components/vit/schedule.slots/class.php`
- Create: `local/components/vit/schedule.slots/templates/.default/template.php`
- Create: `local/components/vit/schedule.slots/templates/.default/style.css`

- [ ] **Step 1: Create class.php**

```php
<?php

use Bitrix\Main\Loader;
use Vit\Schedule\SlotGenerator;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class VitScheduleSlotsComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('vit.schedule')) {
            ShowError('Модуль vit.schedule не установлен');
            return;
        }

        $date = $this->arParams['DATE'] ?? date('Y-m-d');

        if (!strtotime($date)) {
            $date = date('Y-m-d');
        }

        $this->arResult['CURRENT_DATE'] = $date;
        $this->arResult['WEEK'] = SlotGenerator::getWeekSchedule($date);

        $this->includeComponentTemplate();
    }
}
```

- [ ] **Step 2: Create template.php**

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
$week = $arResult['WEEK'];
$currentDate = $arResult['CURRENT_DATE'];
?>

<div class="vit-schedule">
    <div class="vit-schedule__title">Расписание приёма</div>

    <div class="vit-schedule__days">
        <?php foreach ($week as $day): ?>
            <a
                href="?date=<?= $day['date'] ?>"
                class="vit-schedule__day <?= $day['is_current'] ? 'vit-schedule__day--active' : '' ?> <?= !$day['is_working'] ? 'vit-schedule__day--off' : '' ?>"
            >
                <span class="vit-schedule__day-name"><?= $day['short_name'] ?></span>
                <span class="vit-schedule__day-date"><?= $day['date_formatted'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    $currentDay = null;
    foreach ($week as $day) {
        if ($day['is_current']) {
            $currentDay = $day;
            break;
        }
    }
    ?>

    <?php if ($currentDay && $currentDay['is_working']): ?>
        <div class="vit-schedule__slots">
            <?php foreach ($currentDay['slots'] as $slot): ?>
                <div class="vit-schedule__slot"><?= $slot ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="vit-schedule__off-message">Выходной</div>
    <?php endif; ?>
</div>
```

- [ ] **Step 3: Create style.css**

```css
.vit-schedule {
    max-width: 600px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.vit-schedule__title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 16px;
    color: #333;
}

.vit-schedule__days {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    overflow-x: auto;
}

.vit-schedule__day {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    cursor: pointer;
    min-width: 52px;
    transition: background-color 0.15s, border-color 0.15s;
}

.vit-schedule__day:hover {
    background-color: #f5f5f5;
}

.vit-schedule__day--active {
    background-color: #4a90d9;
    border-color: #4a90d9;
    color: #fff;
}

.vit-schedule__day--active:hover {
    background-color: #3a7bc8;
}

.vit-schedule__day--off {
    opacity: 0.5;
}

.vit-schedule__day-name {
    font-size: 13px;
    font-weight: 500;
}

.vit-schedule__day-date {
    font-size: 12px;
    margin-top: 2px;
}

.vit-schedule__slots {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.vit-schedule__slot {
    padding: 8px 16px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    transition: background-color 0.15s, border-color 0.15s;
}

.vit-schedule__slot:hover {
    background-color: #e8f0fe;
    border-color: #4a90d9;
}

.vit-schedule__off-message {
    padding: 20px;
    text-align: center;
    color: #999;
    font-size: 16px;
}
```

- [ ] **Step 4: Commit**

```bash
git add local/components/vit/schedule.slots/
git commit -m "feat: add public schedule.slots component with template"
```

---

### Task 7: README

**Files:**
- Create: `README.md`

- [ ] **Step 1: Create README.md**

```markdown
# vit.schedule — Модуль управления расписанием врача

## Архитектура решения

### Подход

Собственная таблица в БД + D7 ORM DataManager. Выбран вместо инфоблоков и HL-блоков:
- 7 фиксированных записей (дни недели) — инфоблоки/HL избыточны для такой структуры
- Полный контроль над схемой данных
- Чистый install/uninstall модуля

### Структура

**Модуль** (`local/modules/vit.schedule/`) — ядро:
- `ScheduleTable` — D7 ORM-модель таблицы `vit_schedule`
- `SlotGenerator` — генерация временных слотов из расписания
- Админ-страница для настройки рабочей недели
- Страница настроек модуля (длительность слота)

**Компонент** (`local/components/vit/schedule.slots/`) — публичная часть:
- Отображение доступных слотов на выбранную дату
- Навигация по дням недели

### БД: таблица `vit_schedule`

| Поле        | Тип        | Описание           |
|-------------|------------|--------------------|
| ID          | int, PK    | Автоинкремент      |
| DAY_OF_WEEK | int (1–7)  | 1=Пн, 7=Вс        |
| IS_WORKING  | char(1)    | Y — рабочий, N — выходной |
| TIME_FROM   | varchar(5) | Начало работы (HH:MM) |
| TIME_TO     | varchar(5) | Конец работы (HH:MM)  |

Длительность слота хранится в настройках модуля (`COption`, ключ `slot_duration`).

### Установка

1. Скопировать `local/` в корень сайта Битрикс
2. Админка → Marketplace → Установленные решения → Установить модуль «vit.schedule»
3. Расписание доступно в меню: Сервисы → Расписание врача

### Подключение компонента

```php
$APPLICATION->IncludeComponent("vit:schedule.slots", "", [
    "DATE" => date("Y-m-d")
]);
```
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add README with architecture description"
```

---

## Verification Checklist

After all tasks are complete, verify:

- [ ] All files exist at their specified paths
- [ ] `ScheduleTable::getMap()` field names match column names in `install.sql`
- [ ] `SlotGenerator::generateSlots()` uses `<=` for end boundary (last slot must fit entirely before end time)
- [ ] Admin page sends hidden `IS_WORKING[N]=N` before checkbox so unchecked days submit correctly
- [ ] `options.php` validates `slot_duration` against whitelist before saving
- [ ] Component `class.php` checks module loaded before using ORM classes
- [ ] `menu.php` returns array in correct Bitrix admin menu format
