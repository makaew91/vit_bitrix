<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vit.schedule', [
    'Vit\\Schedule\\ScheduleTable' => 'lib/ScheduleTable.php',
    'Vit\\Schedule\\SlotGenerator' => 'lib/SlotGenerator.php',
]);
