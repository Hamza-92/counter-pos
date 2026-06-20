<?php

namespace App\Http\Controllers;

use App\Models\ServiceJob;
use App\Models\ServiceJobPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ServiceJobPhotoController extends BaseController
{
    // -------------- List photos for a service job ---------------\\
    public function index(Request $request, $serviceJobId)
    {
        $this->authorizeForUser($request->user('api'), 'view', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($serviceJobId);

        $photos = ServiceJobPhoto::where('service_job_id', $job->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceJobPhoto $row) {
                return [
                    'id' => $row->id,
                    'stage' => $row->stage,
                    'path' => $row->path,
                    'url' => asset($row->path),
                    'original_name' => $row->original_name,
                    'mime_type' => $row->mime_type,
                    'size' => $row->size,
                    'caption' => $row->caption,
                    'created_at' => $row->created_at,
                ];
            });

        return response()->json(['photos' => $photos]);
    }

    // -------------- Upload one or more photos ---------------\\
    public function store(Request $request, $serviceJobId)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $validated = $request->validate([
            'stage' => 'nullable|string|in:intake,before,after,delivery',
            'caption' => 'nullable|string|max:1000',
            'photos' => 'required|array|min:1',
            'photos.*' => 'file|image|max:10240', // 10MB per file
        ]);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($serviceJobId);
        $stage = $validated['stage'] ?? 'intake';

        $uploadPath = public_path('images/service_photos/'.$job->id);
        if (! file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $created = [];
        foreach ($request->file('photos') as $file) {
            $originalName = $file->getClientOriginalName();
            $size = $file->getSize();
            $mimeType = $file->getMimeType();

            $filename = time().'_'.Str::random(10).'_'.$originalName;
            $file->move($uploadPath, $filename);

            $relativePath = 'images/service_photos/'.$job->id.'/'.$filename;

            $photo = ServiceJobPhoto::create([
                'service_job_id' => $job->id,
                'user_id' => Auth::id(),
                'stage' => $stage,
                'path' => $relativePath,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $size,
                'caption' => $validated['caption'] ?? null,
            ]);

            $created[] = [
                'id' => $photo->id,
                'stage' => $photo->stage,
                'path' => $photo->path,
                'url' => asset($photo->path),
                'original_name' => $photo->original_name,
                'mime_type' => $photo->mime_type,
                'size' => $photo->size,
                'caption' => $photo->caption,
            ];
        }

        return response()->json(['success' => true, 'photos' => $created], 201);
    }

    // -------------- Delete a photo ---------------\\
    public function destroy(Request $request, $serviceJobId, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', ServiceJob::class);

        $job = ServiceJob::whereNull('deleted_at')->findOrFail($serviceJobId);

        $photo = ServiceJobPhoto::whereNull('deleted_at')
            ->where('service_job_id', $job->id)
            ->findOrFail($id);

        if ($photo->path) {
            $absolute = public_path($photo->path);
            if (file_exists($absolute)) {
                @unlink($absolute);
            }
        }

        $photo->update(['deleted_at' => now()]);

        return response()->json(['success' => true]);
    }
}
