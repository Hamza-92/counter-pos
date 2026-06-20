<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MigrateProductLegacyImagesCommand extends Command
{
    protected $signature = 'products:migrate-legacy-images {--chunk=500 : Rows per chunk}';

    protected $description = 'Copy non-empty products.image paths into product_images (idempotent, chunked).';

    public function handle(): int
    {
        if (! Schema::hasTable('product_images')) {
            $this->error('Table product_images does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $inserted = 0;
        $skipped = 0;

        Product::query()
            ->whereNull('deleted_at')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use (&$inserted, &$skipped) {
                foreach ($products as $product) {
                    $parts = collect(explode(',', (string) $product->image))
                        ->map(fn ($s) => trim((string) $s))
                        ->filter(fn ($s) => $s !== '' && $s !== 'no-image.png')
                        ->values();

                    if ($parts->isEmpty()) {
                        $skipped++;

                        continue;
                    }

                    foreach ($parts as $index => $path) {
                        $row = ProductImage::firstOrCreate(
                            [
                                'product_id' => $product->id,
                                'image_path' => $path,
                            ],
                            [
                                'is_main' => $index === 0,
                                'sort_order' => $index,
                            ]
                        );
                        if ($row->wasRecentlyCreated) {
                            $inserted++;
                        }
                    }
                }
            });

        ProductImage::query()
            ->select('product_id')
            ->distinct()
            ->orderBy('product_id')
            ->pluck('product_id')
            ->chunk((int) $chunk)
            ->each(function ($productIds) {
                foreach ($productIds as $productId) {
                    if (ProductImage::where('product_id', $productId)->where('is_main', true)->exists()) {
                        continue;
                    }
                    $first = ProductImage::where('product_id', $productId)->orderBy('sort_order')->orderBy('id')->first();
                    if ($first) {
                        $first->update(['is_main' => true]);
                    }
                }
            });

        $this->info("Done. New rows created: {$inserted}. Empty/no-image products skipped in chunk loop: {$skipped}.");

        return self::SUCCESS;
    }
}
