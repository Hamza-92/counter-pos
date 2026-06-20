<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at', 'signed_at'];

    protected $fillable = [
        'contract_number', 'party_type', 'client_id', 'employee_id', 'project_id', 'subject', 'value', 'type',
        'start_date', 'end_date', 'description', 'hide_from_customer', 'status',
        'signer_name', 'signed_at', 'signed_ip',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'employee_id' => 'integer',
        'project_id' => 'integer',
        'value' => 'decimal:2',
        'hide_from_customer' => 'boolean',
        'signed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(ContractAttachment::class, 'contract_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(ContractComment::class, 'contract_id', 'id')->orderBy('created_at', 'desc');
    }

    public function renewals()
    {
        return $this->hasMany(ContractRenewal::class, 'contract_id', 'id')->orderBy('renewal_date', 'desc');
    }

    public function notes()
    {
        return $this->hasMany(ContractNote::class, 'contract_id', 'id')->orderBy('created_at', 'desc');
    }

    public function contractTasks()
    {
        return $this->hasMany(ContractTask::class, 'contract_id', 'id')->orderBy('due_date');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(Contract::class, 'renewed_from_contract_id', 'id');
    }

    public static function generateContractNumber()
    {
        $prefix = 'CON-' . date('Ymd') . '-';
        $last = static::withTrashed()->where('contract_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
        $seq = $last ? (int) substr($last->contract_number, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
