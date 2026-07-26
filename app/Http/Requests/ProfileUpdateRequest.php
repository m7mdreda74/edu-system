<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'avatar' => ['nullable', 'image', 'max:2048'], // Max 2MB
        ];

        // A teacher's public profile is the platform's shop window: students
        // pick who to study with from the intro video and background.
        if ($this->user()?->hasRole('teacher')) {
            $rules += [
                'headline'              => ['nullable', 'string', 'max:255'],
                'bio'                   => ['nullable', 'string', 'max:2000'],
                'intro_video_url'       => ['nullable', 'url', 'max:2048'],
                'intro_video_thumbnail' => ['nullable', 'url', 'max:2048'],
                'years_experience'      => ['nullable', 'integer', 'min:0', 'max:70'],
            ];
        }

        return $rules;
    }
}
