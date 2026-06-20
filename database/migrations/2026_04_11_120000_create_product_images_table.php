<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->string('image_path', 255);
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'image_path']);
            $table->index(['product_id', 'sort_order']);
        });

        $this->migrateLegacyProductImages();
    }

    /**
     * Copy existing gallery paths from products.image (single filename or comma-separated) into product_images.
     * First path is marked main to match legacy “primary image” behaviour. Skips empty values and no-image.png.
     */
    protected function migrateLegacyProductImages(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_images')) {
            return;
        }

        $now = now();

        DB::table('products')
            ->select('id', 'image')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now) {
                foreach ($rows as $product) {
                    $paths = collect(explode(',', (string) $product->image))
                        ->map(fn ($s) => trim((string) $s))
                        ->filter(fn ($p) => $p !== '' && strcasecmp($p, 'no-image.png') !== 0)
                        ->unique()
                        ->values();

                    foreach ($paths as $index => $path) {
                        if (strlen($path) > 255) {
                            continue;
                        }

                        DB::table('product_images')->insertOrIgnore([
                            'product_id' => (int) $product->id,
                            'image_path' => $path,
                            'is_main' => $index === 0,
                            'sort_order' => $index,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
