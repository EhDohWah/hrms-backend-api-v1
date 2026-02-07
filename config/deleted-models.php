<?php

return [
    /*
     * The model used to store deleted models.
     *
     * We extend Spatie's default to add SQL Server IDENTITY_INSERT support
     * in saveRestoredModel(), so restoration works on IDENTITY columns.
     */
    'model' => App\Models\SpatieDeletedModel::class,

    /*
     * After this amount of days, the records in `deleted_models` will be deleted
     *
     * This functionality uses Laravel's native pruning feature.
     */
    'prune_after_days' => 365,
];
