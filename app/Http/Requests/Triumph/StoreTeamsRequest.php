<?php

namespace App\Http\Requests\Triumph;

use App\Rules\Triumph\TeamGameIdRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class StoreTeamsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service' => ['required', 'string'],
            'game_id' => ['required', 'numeric', new TeamGameIdRule()],
            'name' => ['required', 'string', 'max:20', 'specialChar1'],
            'image_url' => ['sometimes', 'nullable', 'url'],
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
