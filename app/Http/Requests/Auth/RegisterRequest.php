<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            /*
             | No role at signup. An account is just an account; the person then
             | applies to be a creator, an editor, a brand, or several. Asking up
             | front forced a choice before they had seen the product, and made
             | "I am both a creator and an editor" impossible to express.
             |
             | Manager is absent from every path: nobody applies to be one.
             */
            'role' => ['sometimes', 'nullable', 'in:creator,editor,brand'],
        ];
    }
}
