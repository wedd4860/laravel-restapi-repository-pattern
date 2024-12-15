<?php

namespace App\Http\Requests\Triumph;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GamesEventSearchRequest extends FormRequest
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
            'event_status' => 'sometimes|in:ready,completed,progress,finished',
            'bracket_type' => 'sometimes|in:tournament', // format 토너먼트 타입
            'entry_type' => 'sometimes|numeric', // team_size 개인전, 팀전
            'search' => 'sometimes|string', // 검색어
            'order_type' => 'sometimes|in:date,status'
        ];
    }

    public function messages()
    {
        return [
            'service.required' => __('messages.Service is a required field'),
            'device.required' => __('messages.Device information is a required field'),
            'page.numeric' => __('messages.Page information must be provided as a number'),
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
