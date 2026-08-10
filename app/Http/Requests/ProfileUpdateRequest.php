<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AltafawwuqEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => AltafawwuqEmail::normalize($this->input('email')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isTeacher = $this->user()?->hasRole('teacher') ?? false;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                new AltafawwuqEmail(),
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // A teacher's photo appears on the public browse pages, so the
            // platform owns it — an admin sets it from the users screen.
            'avatar' => $isTeacher
                ? ['prohibited']
                : ['nullable', 'image', 'max:2048'], // Max 2MB
        ];

        // A teacher's public profile is the platform's shop window: students
        // pick who to study with from the intro video and background.
        if ($isTeacher) {
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.prohibited' => 'الصورة الشخصية للمعلم تُحدَّد من قبل إدارة المنصة.',
        ];
    }
}
