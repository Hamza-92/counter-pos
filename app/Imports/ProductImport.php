<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements WithHeadingRow
{
    public function collection(Collection $rows) {}
}
