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

        $date = $this->arParams['DATE'] ?? $_GET['date'] ?? date('Y-m-d');

        if (!strtotime($date)) {
            $date = date('Y-m-d');
        }

        $this->arResult['CURRENT_DATE'] = $date;
        $this->arResult['WEEK'] = SlotGenerator::getWeekSchedule($date);

        $this->includeComponentTemplate();
    }
}
