<?php

namespace App\Http\Requests\Api\Concerns;

use App\Models\User;

trait ResolvesActor
{
    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
