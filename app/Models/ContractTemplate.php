<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = ['name', 'content'];

    /**
     * Replace merge fields in content with contract data.
     *
     * @param  \App\Models\Contract  $contract
     * @return string
     */
    public function render(Contract $contract)
    {
        $contract->load('client', 'project');
        $replace = [
            '{contract_number}' => $contract->contract_number,
            '{customer_name}'   => $contract->client ? $contract->client->name : '',
            '{customer_email}' => $contract->client ? $contract->client->email : '',
            '{customer_phone}'  => $contract->client ? $contract->client->phone : '',
            '{customer_address}' => $contract->client ? $contract->client->adresse : '',
            '{project_name}'    => $contract->project ? $contract->project->title : '',
            '{subject}'         => $contract->subject,
            '{contract_value}'  => number_format($contract->value, 2),
            '{contract_type}'   => $contract->type ?? '',
            '{start_date}'      => $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->format('F j, Y') : '',
            '{end_date}'        => $contract->end_date ? \Carbon\Carbon::parse($contract->end_date)->format('F j, Y') : '',
            '{description}'     => $contract->description ?? '',
            '{status}'          => $contract->status,
            '{signer_name}'     => $contract->signer_name ?? '',
            '{signed_at}'       => $contract->signed_at ? $contract->signed_at->format('F j, Y H:i') : '',
            '{signed_ip}'       => $contract->signed_ip ?? '',
        ];
        return str_replace(array_keys($replace), array_values($replace), $this->content ?? '');
    }
}
