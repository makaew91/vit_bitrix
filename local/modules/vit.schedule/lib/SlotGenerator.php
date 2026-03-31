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
