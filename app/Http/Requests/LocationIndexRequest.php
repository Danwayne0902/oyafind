<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LocationIndexRequest extends FormRequest
{
    /**
     * Only the documented, bounded query parameters are supported.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];

        foreach (array_diff(array_keys($this->query->all()), array_keys($rules)) as $parameter) {
            $rules[$parameter] = ['prohibited'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * GET filters are accepted from the query string only.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->query->all();
    }
}
