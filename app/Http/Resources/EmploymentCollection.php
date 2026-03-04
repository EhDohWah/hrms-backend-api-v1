<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EmploymentCollection extends ResourceCollection
{
    public $collects = EmploymentResource::class;

    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
