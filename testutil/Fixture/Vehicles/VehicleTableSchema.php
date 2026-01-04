<?php

declare(strict_types=1);

namespace TestUtil\Fixture\Vehicles;

use SubstancePHP\SQL\TableSchema;

/** @extends TableSchema<self> */
class VehicleTableSchema extends TableSchema
{
    #[\Override] public function getTableName(): string
    {
        return 'vehicles';
    }

    #[\Override] public function getColumns(): array
    {
        return [
            'id',
            'kind',
            'make',
            'model',
            'year',
            'briefDescription' => 'brief_description',
        ];
    }
}
