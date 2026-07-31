<?php

use App\Models\Dimension;
use App\Models\DimensionValue;
use Illuminate\Database\QueryException;

it('holds its values', function () {
    $dimension = Dimension::factory()->create(['key' => 'branch', 'name' => 'Filial']);
    DimensionValue::factory()->create(['dimension_id' => $dimension->id, 'name' => 'Chilonzor']);

    expect($dimension->values)->toHaveCount(1);
});

it('rejects a duplicate key', function () {
    Dimension::factory()->create(['key' => 'branch']);

    Dimension::factory()->create(['key' => 'branch']);
})->throws(QueryException::class);
