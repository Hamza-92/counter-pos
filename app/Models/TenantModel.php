<?php

namespace App\Models;

use App\Tenancy\TenancyManager;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base for newly introduced tenant-owned models. Existing business models are
 * migrated to this base incrementally after their isolation tests are added.
 */
abstract class TenantModel extends Model
{
    public function getConnectionName()
    {
        if (config('tenancy.enabled', false)) {
            if (! app(TenancyManager::class)->hasTenant()) {
                throw new LogicException('A tenant model was used without an active tenant context.');
            }

            return 'tenant';
        }

        return parent::getConnectionName();
    }
}
