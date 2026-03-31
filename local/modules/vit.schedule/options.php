<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

/** @global CMain $APPLICATION */
global $APPLICATION;

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
