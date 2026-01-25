<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;

/**
 * LeaveCalculationService
 *
 * Calculates working days for leave requests by excluding:
 * - Weekends (Saturday and Sunday)
 * - Organization holidays (traditional day-off / public holidays)
 *
 * Usage:
 *   $service = new LeaveCalculationService();
 *   $workingDays = $service->calculateWorkingDays('2025-01-15', '2025-01-22');
 *
 * Example:
 *   Leave request: January 15-22, 2025
 *   - January 18-19 (weekend) = excluded
 *   - January 17 (public holiday) = excluded (if defined)
 *   - Actual counted days: 5 working days
 */
class LeaveCalculationService
{
    /**
     * Calculate working days between two dates, excluding weekends and holidays.
     *
     * @param  string|Carbon  $startDate  Start date of leave
     * @param  string|Carbon  $endDate  End date of leave
     * @return int Number of working days
     */
    public function calculateWorkingDays($startDate, $endDate): int
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        // Get all holidays within the date range
        $holidayDates = Holiday::getDatesInRange($start, $end);

        $workingDays = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            // Skip weekends (Saturday = 6, Sunday = 0 in Carbon)
            if (! $current->isWeekend()) {
                // Skip holidays
                if (! in_array($current->format('Y-m-d'), $holidayDates)) {
                    $workingDays++;
                }
            }

            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Calculate working days with detailed breakdown.
     *
     * Returns an array with:
     * - working_days: Number of working days
     * - total_days: Total calendar days
     * - weekend_days: Number of weekend days
     * - holiday_days: Number of holiday days
     * - excluded_dates: List of excluded dates with reasons
     *
     * @param  string|Carbon  $startDate  Start date of leave
     * @param  string|Carbon  $endDate  End date of leave
     * @return array Detailed breakdown of days
     */
    public function calculateWorkingDaysDetailed($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        // Get holidays within the date range with full details
        $holidays = Holiday::active()
            ->betweenDates($start, $end)
            ->get()
            ->keyBy(fn ($h) => $h->date->format('Y-m-d'));

        $workingDays = 0;
        $weekendDays = 0;
        $holidayDays = 0;
        $excludedDates = [];
        $workingDates = [];

        $current = $start->copy();

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            $isWeekend = $current->isWeekend();
            $holiday = $holidays->get($dateStr);

            if ($isWeekend) {
                $weekendDays++;
                $excludedDates[] = [
                    'date' => $dateStr,
                    'day_name' => $current->format('l'),
                    'reason' => 'weekend',
                ];
            } elseif ($holiday) {
                $holidayDays++;
                $excludedDates[] = [
                    'date' => $dateStr,
                    'day_name' => $current->format('l'),
                    'reason' => 'holiday',
                    'holiday_name' => $holiday->name,
                    'holiday_name_th' => $holiday->name_th,
                ];
            } else {
                $workingDays++;
                $workingDates[] = [
                    'date' => $dateStr,
                    'day_name' => $current->format('l'),
                ];
            }

            $current->addDay();
        }

        $totalDays = $start->diffInDays($end) + 1;

        return [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'working_days' => $workingDays,
            'total_calendar_days' => $totalDays,
            'weekend_days' => $weekendDays,
            'holiday_days' => $holidayDays,
            'excluded_dates' => $excludedDates,
            'working_dates' => $workingDates,
        ];
    }

    /**
     * Check if a specific date is a working day.
     *
     * @param  string|Carbon  $date  Date to check
     * @return bool True if it's a working day
     */
    public function isWorkingDay($date): bool
    {
        $carbonDate = Carbon::parse($date);

        // Check if weekend
        if ($carbonDate->isWeekend()) {
            return false;
        }

        // Check if holiday
        if (Holiday::isHoliday($carbonDate)) {
            return false;
        }

        return true;
    }

    /**
     * Get all non-working dates within a range.
     *
     * @param  string|Carbon  $startDate  Start date
     * @param  string|Carbon  $endDate  End date
     * @return array List of non-working dates with reasons
     */
    public function getNonWorkingDates($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        $holidays = Holiday::active()
            ->betweenDates($start, $end)
            ->get()
            ->keyBy(fn ($h) => $h->date->format('Y-m-d'));

        $nonWorkingDates = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            $holiday = $holidays->get($dateStr);

            if ($current->isWeekend()) {
                $nonWorkingDates[] = [
                    'date' => $dateStr,
                    'type' => 'weekend',
                    'reason' => $current->isSaturday() ? 'Saturday' : 'Sunday',
                ];
            } elseif ($holiday) {
                $nonWorkingDates[] = [
                    'date' => $dateStr,
                    'type' => 'holiday',
                    'reason' => $holiday->name,
                    'reason_th' => $holiday->name_th,
                ];
            }

            $current->addDay();
        }

        return $nonWorkingDates;
    }

    /**
     * Get the next working day from a given date.
     *
     * @param  string|Carbon  $date  Starting date
     * @return Carbon The next working day
     */
    public function getNextWorkingDay($date): Carbon
    {
        $current = Carbon::parse($date)->startOfDay();

        // Get holidays for the next 30 days (reasonable buffer)
        $endDate = $current->copy()->addDays(30);
        $holidayDates = Holiday::getDatesInRange($current, $endDate);

        while (true) {
            if (! $current->isWeekend() && ! in_array($current->format('Y-m-d'), $holidayDates)) {
                return $current;
            }
            $current->addDay();
        }
    }

    /**
     * Calculate the end date given a start date and number of working days.
     *
     * @param  string|Carbon  $startDate  Start date
     * @param  int  $workingDays  Number of working days needed
     * @return Carbon The calculated end date
     */
    public function calculateEndDate($startDate, int $workingDays): Carbon
    {
        $current = Carbon::parse($startDate)->startOfDay();

        // Get holidays for a reasonable range (working days * 2 to account for weekends/holidays)
        $bufferDays = $workingDays * 2;
        $endBuffer = $current->copy()->addDays($bufferDays);
        $holidayDates = Holiday::getDatesInRange($current, $endBuffer);

        $daysAdded = 0;

        // Start from day 1 (inclusive of start date)
        while ($daysAdded < $workingDays) {
            if (! $current->isWeekend() && ! in_array($current->format('Y-m-d'), $holidayDates)) {
                $daysAdded++;
            }

            if ($daysAdded < $workingDays) {
                $current->addDay();
            }
        }

        return $current;
    }

    /**
     * Get summary statistics for a year's holidays.
     *
     * @param  int  $year  Year to analyze
     * @return array Holiday statistics
     */
    public function getYearStatistics(int $year): array
    {
        $holidays = Holiday::active()->forYear($year)->get();

        $weekdayHolidays = $holidays->filter(function ($holiday) {
            return ! Carbon::parse($holiday->date)->isWeekend();
        });

        return [
            'year' => $year,
            'total_holidays' => $holidays->count(),
            'weekday_holidays' => $weekdayHolidays->count(),
            'weekend_holidays' => $holidays->count() - $weekdayHolidays->count(),
            'holidays' => $holidays->map(function ($h) {
                $date = Carbon::parse($h->date);

                return [
                    'name' => $h->name,
                    'date' => $date->format('Y-m-d'),
                    'day_name' => $date->format('l'),
                    'is_weekend' => $date->isWeekend(),
                ];
            }),
        ];
    }
}
