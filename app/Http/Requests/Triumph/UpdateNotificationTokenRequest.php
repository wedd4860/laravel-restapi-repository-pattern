<?php

namespace App\Http\Requests\Triumph;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateNotificationTokenRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service' => 'required|string',
            'is_push' => 'required|in:Y,N',
            'token' => 'required|string|min:140|max:200'
        ];
    }

    public function messages()
    {
        return [
            'service.required' => __('messages.Service is a required field'),
            'device.required' => __('messages.Device information is a required field'),
            'token.required' => __('messages.No token information available'),
            'token.min' => __('messages.The token length is incorrect'),
            'token.max' => __('messages.The token length is incorrect'),
            'is_push.in' => __('messages.Bad Request'),
        ];
    }

    // 상태코드 커스텀
    protected function failedValidation(Validator $validator)
    {
        $iCode = 400;
        $response = response()->json([
            'status' => 'fail',
            'code' => $iCode,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()
        ], $iCode);
        throw new HttpResponseException($response);
    }
}
