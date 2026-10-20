<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AvailableNickname;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-site') === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name', 'nickname', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'email' ? mb_strtolower($value) : $value]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('user')->id;
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'nickname' => ['nullable', 'string', 'max:80', 'alpha_dash', new AvailableNickname($id)],
            'email' => ['required', 'string', 'email:rfc,filter', 'max:255', Rule::unique('users', 'email')->ignore($id),
                function ($attribute, $value, $fail) use ($id) {
                    if (User::whereRaw('LOWER(email) = ?', [$value])->whereKeyNot($id)->exists()) $fail('Cette adresse e-mail est déjà utilisée.');
                }],
            'notify_opt_in' => ['sometimes', 'required', 'boolean'],
            'notify_email' => ['sometimes', 'required', 'boolean'],
            'notify_web' => ['sometimes', 'required', 'boolean'],
            'notify_platform_updates' => ['sometimes', 'required', 'boolean'],
            'directory_visible' => ['sometimes', 'required', 'boolean'],
            'share_real_name' => ['sometimes', 'required', 'boolean'],
            'is_admin' => ['prohibited'], 'email_verified_at' => ['prohibited'],
            'password' => ['prohibited'], 'password_confirmation' => ['prohibited'],
            'has_local_password' => ['prohibited'], 'remember_token' => ['prohibited'],
        ];
    }
}
