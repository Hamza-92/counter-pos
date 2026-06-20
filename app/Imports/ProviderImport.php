<?php

namespace App\Imports;

use App\Models\Provider;
use DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProviderImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return new Provider([
            'name' => $row['name'],
            'code' => $this->getNumberOrder(),
            'adresse' => $row['address'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'country' => $row['country'] ?? null,
            'city' => $row['city'] ?? null,
            'tax_number' => $row['tax_number'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
        ];
    }

    private function getNumberOrder()
    {
        $last = DB::table('providers')->latest('id')->first();

        return $last ? $last->code + 1 : 1;
    }
}
