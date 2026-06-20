<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'date', 'Ref', 'is_pos', 'client_id', 'GrandTotal', 'qte_retturn', 'TaxNet', 'tax_rate', 'notes',
        'total_retturn', 'warehouse_id', 'user_id', 'statut', 'discount', 'discount_Method', 'shipping', 'time', 'used_points', 'earned_points', 'discount_from_points',
        'paid_amount', 'payment_statut', 'created_at', 'updated_at', 'deleted_at', 'shipping_status', 'subscription_id', 'sales_agent_id',
        // Idempotency key for POS sales; nullable for legacy rows and non-POS flows
        'sale_uuid',
        'woocommerce_order_id',
        'woocommerce_order_number',
        'woocommerce_order_status',
        'quickbooks_invoice_id',
        'quickbooks_realm_id',
        'quickbooks_synced_at',
        'quickbooks_sync_error',
    ];

    protected $casts = [
        'is_pos' => 'integer',
        'GrandTotal' => 'double',
        'qte_retturn' => 'double',
        'total_retturn' => 'double',
        'user_id' => 'integer',
        'client_id' => 'integer',
        'warehouse_id' => 'integer',
        'sales_agent_id' => 'integer',
        'subscription_id' => 'integer',
        'discount' => 'double',
        'shipping' => 'double',
        'TaxNet' => 'double',
        'tax_rate' => 'double',
        'paid_amount' => 'double',
        'used_points' => 'double',
        'earned_points' => 'double',
        'discount_from_points' => 'double',
        'quickbooks_synced_at' => 'datetime',
        'woocommerce_order_id' => 'integer',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function details()
    {
        return $this->hasMany('App\Models\SaleDetail');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function facture()
    {
        return $this->hasMany('App\Models\PaymentSale');
    }

    public function warehouse()
    {
        return $this->belongsTo('App\Models\Warehouse');
    }

    public function salesAgent()
    {
        return $this->belongsTo('App\Models\SalesAgent', 'sales_agent_id');
    }

    public function saleCommissions()
    {
        return $this->hasMany('App\Models\SaleCommission', 'sale_id');
    }

    public function documents()
    {
        return $this->hasMany('App\Models\SaleDocument', 'sale_id');
    }

    protected static function booted()
    {
        static::updating(function ($sale) {
            if ($sale->isDirty('quickbooks_invoice_id')) {
                $original = $sale->getOriginal('quickbooks_invoice_id');
                if (! empty($original)) {
                    // lock it back to the original and ignore the change
                    $sale->quickbooks_invoice_id = $original;
                }
            }
        });
    }
}
