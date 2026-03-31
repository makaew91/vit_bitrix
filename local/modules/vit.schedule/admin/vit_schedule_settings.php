<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Vit\Schedule\ScheduleTable;

/** @global CUser $USER */
/** @global CMain $APPLICATION */

Loader::includeModule('vit.schedule');

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Нет доступа');
}

$APPLICATION->SetTitle('Расписание врача');

$message = null;
$dayNames = ScheduleTable::getDayNames();

// Save handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $errors = [];
    $data = [];

    // Validate all days first
    for ($day = 1; $day <= 7; $day++) {
        $isWorking = ($_POST['IS_WORKING'][$day] ?? 'N') === 'Y' ? 'Y' : 'N';
        $timeFrom = trim($_POST['TIME_FROM'][$day] ?? '');
        $timeTo = trim($_POST['TIME_TO'][$day] ?? '');

        if ($isWorking === 'Y') {
            if ($timeFrom === '' || $timeTo === '') {
                $errors[] = $dayNames[$day] . ': укажите время начала и окончания';
            } elseif ($timeFrom >= $timeTo) {
                $errors[] = $dayNames[$day] . ': время начала должно быть раньше окончания';
            }
        }

        $data[$day] = [
            'IS_WORKING' => $isWorking,
            'TIME_FROM' => $isWorking === 'Y' ? $timeFrom : null,
            'TIME_TO' => $isWorking === 'Y' ? $timeTo : null,
        ];
    }

    // Save only if no errors
    if (empty($errors)) {
        for ($day = 1; $day <= 7; $day++) {
            $row = ScheduleTable::getByDayOfWeek($day);
            if ($row) {
                ScheduleTable::update($row['ID'], $data[$day]);
            }
        }

        $message = new CAdminMessage([
            'MESSAGE' => 'Расписание сохранено',
            'TYPE' => 'OK',
        ]);
    } else {
        $message = new CAdminMessage([
            'MESSAGE' => implode('<br>', $errors),
            'TYPE' => 'ERROR',
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
