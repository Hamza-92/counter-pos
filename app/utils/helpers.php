<?php

namespace App\utils;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class helpers
{
    //  Helper Multiple Filter
    public function filter($model, $columns, $param, $request)
    {
        // Loop through the fields checking if they've been input, if they have add
        //  them to the query.
        $fields = [];
        for ($key = 0; $key < count($columns); $key++) {
            $fields[$key]['param'] = $param[$key];
            $fields[$key]['value'] = $columns[$key];
        }

        foreach ($fields as $field) {
            $model->where(function ($query) use ($request, $field, $model) {
                return $model->when($request->filled($field['value']),
                    function ($query) use ($request, $model, $field) {
                        $field['param'] = 'like' ?
                        $model->where($field['value'], 'like', "{$request[$field['value']]}")
                        : $model->where($field['value'], $request[$field['value']]);
                    });
            });
        }

        // Finally return the model
        return $model;
    }

    //  Check If Hass Permission Show All records
    public function Show_Records($model)
    {
        $user = Auth::user();
        
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $ShowRecord = false;
        
        if (isset($user->record_view)) {
            // Use user-level record_view field
            $ShowRecord = (bool) $user->record_view;
        } else {
            // Fallback to role permission check for backward compatibility
            $Role = $user->roles()->first();
            if ($Role) {
                $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');
            }
        }

        if (! $ShowRecord) {
            return $model->where('user_id', '=', Auth::user()->id);
        }

        return $model;
    }
    
    //  Check If User Has Record View Permission (with backward compatibility)
    public function HasRecordView($user = null)
    {
        if ($user === null) {
            $user = Auth::user();
        }
        
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        if (isset($user->record_view)) {
            // Use user-level record_view field
            return (bool) $user->record_view;
        } else {
            // Fallback to role permission check for backward compatibility
            $Role = $user->roles()->first();
            if ($Role) {
                return Role::findOrFail($Role->id)->inRole('record_view');
            }
        }
        
        return false;
    }

    // Get Currency
    public function Get_Currency()
    {
        $settings = Setting::with('Currency')->where('deleted_at', '=', null)->first();

        if ($settings && $settings->currency_id) {
            if (Currency::where('id', $settings->currency_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $symbol = $settings['Currency']->symbol;
            } else {
                $symbol = '';
            }
        } else {
            $symbol = '';
        }

        return $symbol;
    }

    // Get Currency COde
    public function Get_Currency_Code()
    {
        $settings = Setting::with('Currency')->where('deleted_at', '=', null)->first();

        if ($settings && $settings->currency_id) {
            if (Currency::where('id', $settings->currency_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $code = $settings['Currency']->code;
            } else {
                $code = 'usd';
            }
        } else {
            $code = 'usd';
        }

        return $code;
    }

    /**
     * Format price for display based on price_format setting
     * 
     * @param float $number The number to format
     * @param int $decimals Number of decimal places (default: 2)
     * @param string|null $priceFormat The price format key ('comma_dot', 'dot_comma', 'space_comma', or null for default)
     * @return string Formatted price string
     */
    public function formatPriceDisplay($number, $decimals = 2, $priceFormat = null)
    {
        $number = (float) $number;
        $decimals = (int) $decimals;
        
        // If no price format specified, use default number_format
        if (empty($priceFormat)) {
            return number_format($number, $decimals, '.', ',');
        }
        
        // Format based on price_format setting
        switch ($priceFormat) {
            case 'comma_dot':
                // 1,234.56 (thousand , decimal .)
                return number_format($number, $decimals, '.', ',');
                
            case 'dot_comma':
                // 1.234,56 (thousand . decimal ,)
                return number_format($number, $decimals, ',', '.');
                
            case 'space_comma':
                // 1 234,56 (thousand space, decimal ,)
                return number_format($number, $decimals, ',', ' ');
                
            default:
                // Fallback to default format
                return number_format($number, $decimals, '.', ',');
        }
    }
}
