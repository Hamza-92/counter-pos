<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class BannersApiController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreBanner::class);

        $per = (int) $request->query('per_page', 10);
        $sort = $request->query('sort', 'updated_at');
        $dir = $request->query('dir', 'desc');

        $q = StoreBanner::query()->orderBy($sort, $dir)->paginate($per);

        $rows = collect($q->items())->map(function (StoreBanner $b) {
            return [
                'id' => $b->id,
                'title' => $b->title,
                'position' => $b->position,
                'active' => $b->active,
                'updated_at' => $b->updated_at->toDateTimeString(),
                'image' => $b->image,
                'image_url' => $b->image ? asset($b->image) : asset('images/brands/no-image.png'),
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $q->total()],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreBanner::class);

        $request->merge(['active' => $request->boolean('active')]);

        $data = $request->validate([
            'title' => 'nullable|string|max:190',
            'position' => 'nullable|string|max:60',
            'active' => 'boolean',
            // Laravel 12: image rule excludes SVG by default; use file + mimes
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Generate unique name
            $filename = rand(11111111, 99999999).$image->getClientOriginalName();

            // Create folder if it doesn’t exist
            $path = public_path('images/banners');
            if (! file_exists($path)) {
                mkdir($path, 0755, true);
            }

            // Resize / process with Intervention
            $image_resize = Image::make($image->getRealPath());
            $image_resize->save($path.'/'.$filename);

        } else {
            $filename = 'no-image.png'; // <-- put this file manually into public/images/brands
        }

        $data['image'] = 'images/banners/'.$filename;

        $banner = StoreBanner::create($data);

        return response()->json(array_merge($banner->toArray(), [
            'image_url' => asset($data['image']),
        ]), 201);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreBanner::class);
        $b = StoreBanner::findOrFail($id);
        $arr = $b->toArray();
        $arr['image_url'] = $b->image_url;

        return response()->json($arr);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreBanner::class);
        $b = StoreBanner::findOrFail($id);

        // Normalize boolean to satisfy validator
        $request->merge(['active' => $request->boolean('active')]);

        $data = $request->validate([
            'title' => 'nullable|string|max:190',
            'position' => 'nullable|string|max:60',
            'link' => 'nullable|url',
            'active' => 'boolean',
            // Laravel 12: image rule excludes SVG by default; use file + mimes
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:4096',
        ]);

        // If a new image is uploaded, delete the old one (simple unlink pattern) and save the new one.
        if ($request->hasFile('image')) {
            // delete old
            if (! empty($b->image) && $b->image !== 'no-image.png') {
                // if DB ever stored a path, basename() keeps it safe
                $oldName = basename($b->image);
                $pathIMG = public_path().'/images/banners/'.$oldName;
                if (file_exists($pathIMG)) {
                    @unlink($pathIMG);
                }
            }

            // save new
            $image = $request->file('image');
            $filename = rand(11111111, 99999999).$image->getClientOriginalName();

            $img = Image::make($image->getRealPath());
            $img->save(public_path('/images/banners/'.$filename));

            // store only the filename in DB
            $data['image'] = 'images/banners/'.$filename;
        }

        $b->fill($data)->save();

        // respond with a ready-to-use URL
        $arr = $b->toArray();
        $arr['image_url'] = $b->image
            ? asset('images/banners/'.basename($b->image))
            : asset('images/banners/no-image.png');

        return response()->json($arr);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', StoreBanner::class);
        $b = StoreBanner::findOrFail($id);

        if (! empty($b->image)) {
            $filename = basename($b->image); // in case a path was stored
            if ($filename !== 'no-image.png') {
                $pathIMG = public_path('/images/banenrs/'.$filename);
                if (file_exists($pathIMG)) {
                    @unlink($pathIMG);
                }
            }
        }

        $b->delete();

        return response()->json(['ok' => true]);
    }
}
