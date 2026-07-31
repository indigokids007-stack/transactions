<?php

namespace App\Http\Resources;

use App\Models\TransactionRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionRevision */
class TransactionRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'actor' => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
            'snapshot' => $this->snapshot,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
