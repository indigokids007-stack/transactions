<?php

namespace App\Http\Resources;

use App\Models\Dimension;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dimension */
class DimensionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'is_required' => $this->is_required,
            'values' => $this->values->map(fn ($value) => [
                'id' => $value->id,
                'name' => $value->name,
            ]),
        ];
    }
}
