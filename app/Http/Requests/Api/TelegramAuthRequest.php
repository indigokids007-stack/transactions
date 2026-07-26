<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TelegramAuthRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'init_data' => ['required', 'string'],
        ];
    }

    public function initData(): string
    {
        return $this->string('init_data')->toString();
    }
}
