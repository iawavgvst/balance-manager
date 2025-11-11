<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'comment' => 'string|nullable|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required',
            'user_id.integer' => 'The user ID must be an integer',
            'amount.required' => 'The amount is required',
            'amount.numeric' => 'The amount must be a number',
            'amount.min' => 'The amount must be at least 0.01',
            'comment.string' => 'The comment must be a string',
        ];
    }
}
