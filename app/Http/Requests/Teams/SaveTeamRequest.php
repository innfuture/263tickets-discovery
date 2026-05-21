<?php

namespace App\Http\Requests\Teams;

use App\Rules\TeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new TeamName],
            // Optional short description shown on the team card and on
            // the team-edit page. 180-char ceiling matches the event
            // short_description budget.
            'description' => ['nullable', 'string', 'max:180'],
        ];
    }
}
