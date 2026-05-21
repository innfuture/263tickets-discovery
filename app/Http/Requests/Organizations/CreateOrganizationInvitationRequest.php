<?php

namespace App\Http\Requests\Organizations;

use App\Enums\TeamRole;
use App\Rules\UniqueOrganizationInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrganizationInvitationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueOrganizationInvitation($this->route('organization'))],
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ];
    }
}
