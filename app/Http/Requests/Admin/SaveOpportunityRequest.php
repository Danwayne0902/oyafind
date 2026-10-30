<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }

        if (is_string($this->input('organisation_name'))) {
            $this->merge(['organisation_name' => trim($this->input('organisation_name'))]);
        }

        if ($this->input('currency') === '') {
            $this->merge(['currency' => null]);
        }

        if ($this->input('location_id') === '') {
            $this->merge(['location_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['required', 'string', 'max:300'],
            'description' => ['required', 'string'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'organisation_name' => ['required', 'string', 'max:200'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'application_url' => ['nullable', 'url:http,https', 'max:2048'],
            'application_method' => ['nullable', 'string', 'max:60'],
            'application_instructions' => ['nullable', 'string', 'max:5000'],
            'opportunity_type' => ['nullable', 'string', 'max:60'],
            'work_mode' => ['nullable', Rule::in(['remote', 'hybrid', 'onsite'])],
            'eligibility' => ['nullable', 'string', 'max:5000'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0', 'gte:amount_min'],
            'currency' => ['nullable', 'string', 'size:3'],
            'deadline_at' => ['nullable', 'date', 'after:now'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Choose one of the active categories.',
            'deadline_at.after' => 'The deadline must be in the future.',
            'amount_max.gte' => 'The maximum amount cannot be below the minimum.',
        ];
    }
}
