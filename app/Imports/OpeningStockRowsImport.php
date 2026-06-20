<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OpeningStockRowsImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        // $array is already rows as associative arrays with headings
        return $array;
    }
}
