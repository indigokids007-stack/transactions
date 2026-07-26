<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telegram_id' => (int) $this->telegram_id,
            'name' => $this->name,
            'username' => $this->username,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'locale' => $this->locale,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null,
            'permissions' => [
                'can_see_all' => $this->canSeeEverything(),
                'can_manage' => $this->canSeeEverything() || $this->managedDepartments->isNotEmpty(),
            ],
        ];
    }
}
