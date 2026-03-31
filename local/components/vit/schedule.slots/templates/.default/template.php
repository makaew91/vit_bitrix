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
