<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Projects\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', 'string', Rule::in(array_keys(Task::getPriorities()))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul tugas harus diisi.',
            'title.max' => 'Judul tugas maksimal 255 karakter.',
            'assigned_to.exists' => 'User yang dipilih tidak ditemukan.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo harus sama atau setelah tanggal mulai.',
        ];
    }
}
