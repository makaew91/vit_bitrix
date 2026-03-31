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
