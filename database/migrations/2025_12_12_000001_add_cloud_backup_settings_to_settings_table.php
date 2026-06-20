<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Optional cloud backup destination (local backup remains the default)
            $table->boolean('backup_cloud_enabled')->default(false)->after('default_tax');
            $table->boolean('backup_keep_local')->default(true)->after('backup_cloud_enabled');

            // one of: google_drive, dropbox, s3, null
            $table->string('backup_cloud_provider', 50)->nullable()->after('backup_cloud_enabled');
            // Optional folder/prefix used by providers
            $table->string('backup_cloud_path', 255)->nullable()->after('backup_cloud_provider');

            // -------- S3-compatible settings --------
            $table->string('backup_s3_bucket', 191)->nullable()->after('backup_cloud_path');
            $table->string('backup_s3_region', 191)->nullable()->after('backup_s3_bucket');
            $table->string('backup_s3_access_key', 191)->nullable()->after('backup_s3_region');
            $table->text('backup_s3_secret_key')->nullable()->after('backup_s3_access_key');
            // e.g. https://minio.example.com (leave null for AWS)
            $table->string('backup_s3_endpoint', 255)->nullable()->after('backup_s3_secret_key');
            $table->boolean('backup_s3_path_style')->default(false)->after('backup_s3_endpoint');

            // -------- Google Drive settings --------
            $table->string('backup_gdrive_folder_id', 191)->nullable()->after('backup_s3_path_style');
            // Either provide an access token (short-lived) OR a refresh token + client id/secret
            $table->text('backup_gdrive_access_token')->nullable()->after('backup_gdrive_folder_id');
            $table->text('backup_gdrive_refresh_token')->nullable()->after('backup_gdrive_access_token');
            $table->string('backup_gdrive_client_id', 191)->nullable()->after('backup_gdrive_refresh_token');
            $table->text('backup_gdrive_client_secret')->nullable()->after('backup_gdrive_client_id');

            // -------- Dropbox settings --------
            // Destination path (folder) in Dropbox, e.g. /StockyBackups
            $table->string('backup_dropbox_path', 191)->nullable()->after('backup_gdrive_client_secret');
            $table->text('backup_dropbox_access_token')->nullable()->after('backup_dropbox_path');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'backup_cloud_enabled',
                'backup_cloud_provider',
                'backup_cloud_path',

                'backup_s3_bucket',
                'backup_s3_region',
                'backup_s3_access_key',
                'backup_s3_secret_key',
                'backup_s3_endpoint',
                'backup_s3_path_style',

                'backup_gdrive_folder_id',
                'backup_gdrive_access_token',
                'backup_gdrive_refresh_token',
                'backup_gdrive_client_id',
                'backup_gdrive_client_secret',

                'backup_dropbox_path',
                'backup_dropbox_access_token',
            ]);
        });
    }
};


