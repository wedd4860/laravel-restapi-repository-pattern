<?php

namespace App\Http\Requests\Triumph;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GamesRequest extends FormRequest
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
            'device' => 'required|in:mobile,web,android,ios',
            'page' => 'sometimes|numeric',
            'search' => 'required|string', // 검색어
        ];
    }

    public function messages()
    {
        return [
            'service.required' => __('messages.Service is a required field'),
            'device.required' => __('messages.Device information is a required field'),
            'page.numeric' => __('messages.Page information must be provided as a number'),
            'search.required' => __('messages.Please enter a keyword to search'),
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
