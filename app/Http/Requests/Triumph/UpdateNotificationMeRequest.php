<?php

namespace App\Http\Requests\Triumph;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateNotificationMeRequest extends FormRequest
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
            'notification_id' => 'required|array',
            'notification_id.*' => 'string',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
     public function messages()
     {
         return [
             'service.required' => __('messages.Service is a required field'),
             'last_evaluated_key.array' => __('messages.The format of the Last Evaluated Key is incorrect'),
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
