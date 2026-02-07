<?php

namespace App\Models;

use App\Traits\ConvertsDatesForSqlServer;
use Illuminate\Database\Eloquent\Model;
use Spatie\DeletedModels\Models\DeletedModel as BaseSpatieDeletedModel;

/**
 * Custom Spatie DeletedModel with SQL Server IDENTITY_INSERT support.
 *
 * Spatie's default saveRestoredModel() uses forceFill() + save(), which fails
 * on SQL Server because save() tries to INSERT with an explicit ID value into
 * an IDENTITY column. SQL Server requires SET IDENTITY_INSERT ON for this,
 * and the SET command must use unprepared() (PDO::exec) rather than statement()
 * (PDO::prepare) because the ODBC driver doesn't reliably carry session-level
 * SET state across prepared statements.
 */
class SpatieDeletedModel extends BaseSpatieDeletedModel
{
    use ConvertsDatesForSqlServer;

    protected function saveRestoredModel(Model $model): void
    {
        $connection = $model->getConnection();
        $table = $model->getTable();
        $originalId = $model->getKey();

        if ($originalId && $connection->getDriverName() === 'sqlsrv') {
            // Filter attributes to only actual table columns.
            // attributesToKeep() / forceFill() may include accessors or appended data.
            $columns = $connection->getSchemaBuilder()->getColumnListing($table);
            $data = array_intersect_key($model->getAttributes(), array_flip($columns));

            // Convert ISO 8601 date strings to SQL Server format.
            // toArray() serializes dates as "2026-02-07T05:59:56.000000Z" which
            // SQL Server's ODBC driver cannot parse via query builder insert().
            $data = $this->convertDatesForSqlServer($data);

            // unprepared() uses PDO::exec() which reliably sets session state.
            $connection->unprepared("SET IDENTITY_INSERT [{$table}] ON");
            try {
                $connection->table($table)->insert($data);
            } finally {
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            }

            // Set model state so callers get a proper "saved" model
            $model->exists = true;
            $model->wasRecentlyCreated = true;

            return;
        }

        // Non-SQL Server: use default Eloquent save
        $model->save();
    }
}
