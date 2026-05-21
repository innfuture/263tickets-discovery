<?php

namespace App\Http\Requests\Organizations;

use App\Enums\Permission as PermissionEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $names = array_column(PermissionEnum::cases(), 'value');

        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'description' => ['nullable', 'string', 'max:190'],
            'level' => ['nullable', 'integer', 'between:10,90'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($names)],
        ];
    }
}
