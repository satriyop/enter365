<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\CheckSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCheckSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('check');

        return $record instanceof CheckSetting
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', CheckSetting::class);
    }

    public function rules(): array
    {
        $record = $this->route('check');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'filled', 'string', 'max:64', Rule::unique('check_settings', 'code')->ignore($record)],
            'name' => [$required, 'filled', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'journal_id' => [$required, 'integer', Rule::exists('journals', 'id')->where('type', 'bank'), Rule::unique('check_settings', 'journal_id')->ignore($record)],
            'next_number' => [$required, 'integer', 'min:1', 'max:2147483647'],
            'layout' => [$required, Rule::in(['top', 'middle', 'bottom'])],
            'manual_numbering' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This code is already in use.',
            'name.required' => 'Enter a name for this configuration.',
            'journal_id.exists' => 'Select a journal with the correct bank or cash type.',
        ];
    }
}
