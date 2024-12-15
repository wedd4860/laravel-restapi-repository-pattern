<?php

namespace App\Http\Requests\Triumph;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateMemberRequest extends FormRequest
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
            'name' => 'sometimes|string|min:1|max:20|specialChar1',
            'image_url' => 'sometimes|url',
            'language' => 'sometimes|in:kr,ko,en,jp,zh',
            'push' => 'sometimes|in:0,1',
            'device' => 'required|in:mobile,web,android,ios',
            'service' => 'required|string',
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
             'name.string' => __('messages.Bad Request'),
             'name.min' => __('messages.Bad Request'),
             'name.max' => __('messages.Bad Request'),
             'name.specialChar1' => __('messages.Bad Request'),
             'image_url.url' => __('messages.Bad Request'),
             'push.in' => __('messages.Bad Request'),
             'service.required' => __('messages.Bad Request'),
             'device.required' => __('messages.Bad Request'),
             'device.in' => __('messages.Bad Request'),
             'language.in' => __('messages.Bad Request'),
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
