<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'user_id',
        'client_id',
        'product_id',
        'warehouse_id', // Warehouse selection added
        'total_cycles',
        'cycle_type', // e.g., 12 for monthly (1 year), 52 for weekly
        'billing_cycle', // monthly, weekly, yearly
        'remaining_cycles', // Decreases with each payment
        'price_per_cycle',
        'price_per_unit',
        'quantity',
        'next_billing_date',
        'status', // active, canceled,
    ];

    protected $casts = [
        'client_id' => 'integer',
        'user_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'total_cycles' => 'integer',
        'remaining_cycles' => 'integer',
        'price_per_cycle' => 'double',
        'price_per_unit' => 'double',
        'quantity' => 'double',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function invoices()
    {
        return $this->hasMany(Sale::class, 'subscription_id');
    }

    public function generateInvoice()
    {
        if ($this->remaining_cycles > 0) {
            $sale = Sale::create([
                'date' => now(),
                'Ref' => 'SUB-'.strtoupper(uniqid()), // Unique reference
                'is_pos' => false,
                'subscription_id' => $this->id,
                'client_id' => $this->client_id,
                'GrandTotal' => $this->price_per_cycle,
                'warehouse_id' => $this->warehouse_id,
                'user_id' => $this->user_id,
                'statut' => 'completed',
                'discount' => 0,
                'shipping' => 0,
                'paid_amount' => 0,
                'payment_statut' => 'unpaid',
                'shipping_status' => 'pending',
            ]);

            // Fetch product unit for sale
            $product = Product::with('unitSale')->where('id', $this->product_id)->first();
            $unit = $product?->unitSale?->id ?? null;

            // Create Sale Detail
            SaleDetail::create([
                'sale_id' => $sale->id,
                'date' => now(),
                'sale_unit_id' => $unit,
                'quantity' => $this->quantity,
                'product_id' => $this->product_id,
                'total' => $this->price_per_cycle,
                'price' => $this->price_per_unit,
            ]);

            return $sale; // ✅ Important: return the created invoice

        }
    }
}
