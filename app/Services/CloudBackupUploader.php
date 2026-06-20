<?php

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class CloudBackupUploader
{
    /**
     * Upload a local backup file to the configured cloud provider.
     *
     * Returns an array with:
     * - success: bool
     * - provider: string|null
     * - remote: string|null
     * - error: string|null
     */
    public function uploadIfConfigured(string $localPath, string $fileName, ?Setting $setting = null): array
    {
        $setting = $setting ?: Setting::whereNull('deleted_at')->first();

        if (! $setting) {
            return [
                'success' => false,
                'provider' => null,
                'remote' => null,
                'error' => 'Settings not found',
            ];
        }

        if (! $setting->backup_cloud_enabled) {
            return [
                'success' => true,
                'provider' => null,
                'remote' => null,
                'error' => null,
                'skipped' => true,
            ];
        }

        $provider = (string) ($setting->backup_cloud_provider ?: '');
        if ($provider === '') {
            return [
                'success' => false,
                'provider' => null,
                'remote' => null,
                'error' => 'Cloud backup is enabled but no provider is selected',
            ];
        }

        try {
            if ($provider === 'dropbox') {
                $remote = $this->uploadToDropbox($setting, $localPath, $fileName);
                return ['success' => true, 'provider' => 'dropbox', 'remote' => $remote, 'error' => null];
            }

            if ($provider === 'google_drive') {
                $remote = $this->uploadToGoogleDrive($setting, $localPath, $fileName);
                return ['success' => true, 'provider' => 'google_drive', 'remote' => $remote, 'error' => null];
            }

            if ($provider === 's3') {
                $remote = $this->uploadToS3($setting, $localPath, $fileName);
                return ['success' => true, 'provider' => 's3', 'remote' => $remote, 'error' => null];
            }

            return [
                'success' => false,
                'provider' => $provider,
                'remote' => null,
                'error' => 'Unsupported cloud provider',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'remote' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function prefixPath(?string $path, string $fileName): string
    {
        $p = trim((string) $path);
        if ($p === '') {
            return $fileName;
        }

        // Normalize separators to "/" and ensure single slash between prefix and file
        $p = str_replace('\\', '/', $p);
        $p = trim($p, '/');
        return $p === '' ? $fileName : ($p.'/'.$fileName);
    }

    private function uploadToDropbox(Setting $setting, string $localPath, string $fileName): string
    {
        $token = (string) ($setting->backup_dropbox_access_token ?: '');
        if ($token === '') {
            throw new \RuntimeException('Dropbox access token is missing');
        }

        $base = (string) ($setting->backup_dropbox_path ?: '');
        $base = trim(str_replace('\\', '/', $base));
        if ($base === '' || $base === '/') {
            $base = '';
        }

        $cloudPrefix = (string) ($setting->backup_cloud_path ?: '');
        $relative = $this->prefixPath($cloudPrefix, $fileName);
        $relative = str_replace('\\', '/', $relative);

        $path = '/'.trim($base.'/'.$relative, '/');

        $client = new Client([
            'timeout' => 120,
        ]);

        $resp = $client->post('https://content.dropboxapi.com/2/files/upload', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Dropbox-API-Arg' => json_encode([
                    'path' => $path,
                    'mode' => 'overwrite',
                    'autorename' => false,
                    'mute' => true,
                    'strict_conflict' => false,
                ]),
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => fopen($localPath, 'rb'),
        ]);

        if ($resp->getStatusCode() < 200 || $resp->getStatusCode() >= 300) {
            throw new \RuntimeException('Dropbox upload failed');
        }

        return $path;
    }

    private function getGoogleAccessToken(Setting $setting): string
    {
        $accessToken = trim((string) ($setting->backup_gdrive_access_token ?: ''));
        if ($accessToken !== '') {
            return $accessToken;
        }

        $refresh = trim((string) ($setting->backup_gdrive_refresh_token ?: ''));
        $clientId = trim((string) ($setting->backup_gdrive_client_id ?: ''));
        $clientSecret = trim((string) ($setting->backup_gdrive_client_secret ?: ''));

        if ($refresh === '' || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Google Drive credentials are missing (provide access token OR refresh token + client id/secret)');
        }

        $client = new Client(['timeout' => 60]);
        $resp = $client->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = json_decode((string) $resp->getBody(), true);
        $token = is_array($data) ? ($data['access_token'] ?? '') : '';
        $token = trim((string) $token);
        if ($token === '') {
            throw new \RuntimeException('Failed to refresh Google Drive access token');
        }
        return $token;
    }

    private function uploadToGoogleDrive(Setting $setting, string $localPath, string $fileName): string
    {
        $token = $this->getGoogleAccessToken($setting);
        $folderId = trim((string) ($setting->backup_gdrive_folder_id ?: ''));

        $client = new Client([
            'timeout' => 120,
        ]);

        // Google Drive doesn't support "path" without folder IDs; we use cloud_path as a name prefix.
        $name = $this->prefixPath($setting->backup_cloud_path, $fileName);
        $name = str_replace('/', '_', $name);
        $name = Str::limit($name, 250, '');

        $metadata = ['name' => $name];
        if ($folderId !== '') {
            $metadata['parents'] = [$folderId];
        }

        $resp = $client->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
            'multipart' => [
                [
                    'name' => 'metadata',
                    'contents' => json_encode($metadata),
                    'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($localPath, 'rb'),
                    'filename' => $name,
                    'headers' => ['Content-Type' => 'application/octet-stream'],
                ],
            ],
        ]);

        $data = json_decode((string) $resp->getBody(), true);
        $id = is_array($data) ? ($data['id'] ?? null) : null;
        if (! $id) {
            throw new \RuntimeException('Google Drive upload failed');
        }
        return (string) $id;
    }

    private function uploadToS3(Setting $setting, string $localPath, string $fileName): string
    {
        if (! class_exists(\Aws\S3\S3Client::class)) {
            throw new \RuntimeException('aws/aws-sdk-php is required for S3 uploads. Please run: composer require aws/aws-sdk-php');
        }

        $bucket = trim((string) ($setting->backup_s3_bucket ?: ''));
        $region = trim((string) ($setting->backup_s3_region ?: ''));
        $keyId = trim((string) ($setting->backup_s3_access_key ?: ''));
        $secret = trim((string) ($setting->backup_s3_secret_key ?: ''));

        if ($bucket === '' || $region === '' || $keyId === '' || $secret === '') {
            throw new \RuntimeException('S3 settings are incomplete (bucket/region/access/secret)');
        }

        $endpoint = trim((string) ($setting->backup_s3_endpoint ?: ''));
        $usePathStyle = (bool) ($setting->backup_s3_path_style ?? false);

        $clientConfig = [
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $keyId,
                'secret' => $secret,
            ],
        ];

        if ($endpoint !== '') {
            $clientConfig['endpoint'] = $endpoint;
            $clientConfig['use_path_style_endpoint'] = $usePathStyle;
        }

        $s3 = new \Aws\S3\S3Client($clientConfig);

        $key = $this->prefixPath($setting->backup_cloud_path, $fileName);
        $key = str_replace('\\', '/', $key);
        $key = ltrim($key, '/');

        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => fopen($localPath, 'rb'),
            'ACL' => 'private',
        ]);

        return $bucket.'/'.$key;
    }
}




























































