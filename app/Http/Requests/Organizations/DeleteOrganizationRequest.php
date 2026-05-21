<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class DeleteOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->route('organization'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $organization = $this->route('organization');

                if ($this->input('name') !== $organization->name) {
                    $validator->errors()->add('name', __('The organization name does not match.'));
                }
            },
        ];
    }}
