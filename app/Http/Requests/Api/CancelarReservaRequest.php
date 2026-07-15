<?php

namespace App\Http\Requests\Api;

use App\Rules\PalavrasMinimas;
use Illuminate\Foundation\Http\FormRequest;

class CancelarReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_cancelamento' => ['required', 'string', new PalavrasMinimas(5)],
        ];
    }
}
