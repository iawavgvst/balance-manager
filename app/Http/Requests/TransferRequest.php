<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
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
            'from_user_id' => 'required|integer|exists:users,id',
            'to_user_id' => 'required|integer|exists:users,id|different:from_user_id',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'comment' => 'string|nullable|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'from_user_id.required' => 'The user ID is required',
            'from_user_id.integer' => 'The user ID must be an integer',
            'to_user_id.required' => 'The user ID is required',
            'to_user_id.integer' => 'The user ID must be an integer',
            'amount.required' => 'The amount is required',
            'amount.numeric' => 'The amount must be a number',
            'amount.min' => 'The amount must be at least 0.01',
            'comment.string' => 'The comment must be a string',
        ];
    }
}
