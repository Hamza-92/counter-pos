<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('record_view')->default(false)->after('is_all_warehouses');
        });

        // Migrate existing users: set record_view based on their role's permission
        $users = User::with('roles')->get();
        
        foreach ($users as $user) {
            $hasRecordView = false;
            
            // Check if user's role has record_view permission
            $role = $user->roles()->first();
            if ($role) {
                $hasRecordView = $role->inRole('record_view');
            }
            
            // Update user's record_view field
            DB::table('users')
                ->where('id', $user->id)
                ->update(['record_view' => $hasRecordView]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('record_view');
        });
    }
};
