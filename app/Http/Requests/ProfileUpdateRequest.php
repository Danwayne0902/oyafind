<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Support\PolicyConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'home_location_id' => ['sometimes', 'nullable', 'integer'],
            'interests' => ['sometimes', 'array', 'max:20'],
            'interests.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
        ];

        foreach (array_diff(array_keys($this->all()), array_keys($rules)) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->exists('home_location_id')
                && $this->input('home_location_id') !== null
                && is_numeric($this->input('home_location_id'))) {
                $policies = $this->container->make(PolicyConfiguration::class);
                $locationIsSelectable = $policies->isEnabled('location_catalogue')
                    && Location::query()
                        ->whereKey($this->input('home_location_id'))
                        ->where('is_active', true)
                        ->whereNotNull('reviewed_at')
                        ->whereNotNull('reviewed_by')
                        ->whereNotNull('catalogue_origin')
                        ->whereRaw("TRIM(catalogue_origin) <> ''")
                        ->exists();

                if (! $locationIsSelectable) {
                    $validator->errors()->add(
                        'home_location_id',
                        'The selected home area is unavailable.',
                    );
                }
            }
        });
    }
}
