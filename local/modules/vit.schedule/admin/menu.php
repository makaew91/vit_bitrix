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
