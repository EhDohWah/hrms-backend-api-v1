<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * Converts ISO 8601 date strings to SQL Server compatible format.
 *
 * When model data is serialized via toArray(), dates become ISO 8601 strings
 * like "2026-02-07T05:59:56.013000Z". SQL Server's ODBC driver cannot parse
 * this format when using query builder insert(). This trait converts them to
 * "Y-m-d H:i:s.v" format (e.g. "2026-02-07 05:59:56.013") which SQL Server accepts.
 */
trait ConvertsDatesForSqlServer
{
    /**
     * Convert ISO 8601 date/datetime strings in an array to SQL Server format.
     */
    protected function convertDatesForSqlServer(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                try {
                    $data[$key] = Carbon::parse($value)->format('Y-m-d H:i:s.v');
                } catch (\Exception $e) {
                    // Leave value as-is if parsing fails
                }
            }
        }

        return $data;
    }
}
