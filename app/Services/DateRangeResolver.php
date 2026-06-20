<?php

namespace App\Services;

use Carbon\Carbon;

class DateRangeResolver
{
    /**
     * Resolve a range keyword to date_from and date_to
     *
     * @param string $range Keyword like 'today', 'yesterday', 'this_week', etc.
     * @return array ['date_from' => string, 'date_to' => string]
     */
    public static function resolve(string $range): array
    {
        $today = Carbon::today();
        
        switch ($range) {
            case 'today':
                return [
                    'date_from' => $today->toDateString(),
                    'date_to' => $today->toDateString(),
                ];
            
            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                return [
                    'date_from' => $yesterday->toDateString(),
                    'date_to' => $yesterday->toDateString(),
                ];
            
            case 'previous_day':
                $yesterday = $today->copy()->subDay();
                return [
                    'date_from' => $yesterday->toDateString(),
                    'date_to' => $yesterday->toDateString(),
                ];
            
            case 'this_week':
                return [
                    'date_from' => $today->copy()->startOfWeek()->toDateString(),
                    'date_to' => $today->copy()->endOfWeek()->toDateString(),
                ];
            
            case 'last_week':
                $lastWeekStart = $today->copy()->subWeek()->startOfWeek();
                $lastWeekEnd = $today->copy()->subWeek()->endOfWeek();
                return [
                    'date_from' => $lastWeekStart->toDateString(),
                    'date_to' => $lastWeekEnd->toDateString(),
                ];
            
            case 'this_month':
                return [
                    'date_from' => $today->copy()->startOfMonth()->toDateString(),
                    'date_to' => $today->copy()->endOfMonth()->toDateString(),
                ];
            
            case 'last_month':
                $lastMonth = $today->copy()->subMonth();
                return [
                    'date_from' => $lastMonth->copy()->startOfMonth()->toDateString(),
                    'date_to' => $lastMonth->copy()->endOfMonth()->toDateString(),
                ];
            
            default:
                // If unknown range, return today as fallback
                return [
                    'date_from' => $today->toDateString(),
                    'date_to' => $today->toDateString(),
                ];
        }
    }

    /**
     * Resolve compare range (e.g., previous_day means day before the main range)
     *
     * @param string $compareRange
     * @param string $mainDateFrom
     * @param string $mainDateTo
     * @return array
     */
    public static function resolveCompare(string $compareRange, string $mainDateFrom, string $mainDateTo): array
    {
        $mainFrom = Carbon::parse($mainDateFrom);
        $mainTo = Carbon::parse($mainDateTo);
        $daysDiff = $mainFrom->diffInDays($mainTo) + 1;

        switch ($compareRange) {
            case 'previous_day':
                $compareFrom = $mainFrom->copy()->subDay();
                $compareTo = $mainTo->copy()->subDay();
                return [
                    'date_from' => $compareFrom->toDateString(),
                    'date_to' => $compareTo->toDateString(),
                ];
            
            case 'previous_week':
                $compareFrom = $mainFrom->copy()->subWeek();
                $compareTo = $mainTo->copy()->subWeek();
                return [
                    'date_from' => $compareFrom->toDateString(),
                    'date_to' => $compareTo->toDateString(),
                ];
            
            case 'previous_month':
                $compareFrom = $mainFrom->copy()->subMonth();
                $compareTo = $mainTo->copy()->subMonth();
                return [
                    'date_from' => $compareFrom->toDateString(),
                    'date_to' => $compareTo->toDateString(),
                ];
            
            default:
                // Default to previous period of same length
                $compareFrom = $mainFrom->copy()->subDays($daysDiff);
                $compareTo = $mainFrom->copy()->subDay();
                return [
                    'date_from' => $compareFrom->toDateString(),
                    'date_to' => $compareTo->toDateString(),
                ];
        }
    }
}
