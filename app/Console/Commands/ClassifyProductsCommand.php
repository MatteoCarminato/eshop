<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductClassifierService;
use Illuminate\Console\Command;

class ClassifyProductsCommand extends Command
{
    protected $signature = 'products:classify
        {--dry-run : Não grava nada, apenas mostra o que aconteceria}
        {--force : Reclassifica produtos que já têm categoria/armazenamento/cor/modelo definidos}';

    protected $description = 'Gera categorias e extrai características (armazenamento, cor, modelo) dos produtos a partir do nome';

    public function handle(ProductClassifierService $classifier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Product::query();
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('category_id')->orWhereNull('storage')->orWhereNull('color_name')->orWhereNull('model');
            });
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->info('Nenhum produto pendente de classificação (use --force para reclassificar tudo).');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Classificando {$products->count()} produto(s)...");

        $categoryCache = [];
        $counts = [];

        foreach ($products as $product) {
            $category = $classifier->classifyCategory($product->name);
            $storage = $classifier->extractStorage($product->name);
            $color = $classifier->extractColor($product->name);
            $model = $classifier->extractModel($product->name);

            $categoryId = null;
            if (!$dryRun) {
                if (!isset($categoryCache[$category['slug']])) {
                    $categoryCache[$category['slug']] = Category::firstOrCreate(
                        ['slug' => $category['slug']],
                        ['name' => $category['name']]
                    );
                }
                $categoryId = $categoryCache[$category['slug']]->id;

                $product->update([
                    'category_id' => $categoryId,
                    'storage' => $storage,
                    'color_name' => $color,
                    'model' => $model,
                ]);
            }

            $counts[$category['name']] = ($counts[$category['name']] ?? 0) + 1;
        }

        arsort($counts);
        $rows = collect($counts)->map(fn ($count, $name) => [$name, $count])->values()->all();
        $this->table(['Categoria', 'Produtos'], $rows);

        if (!$dryRun) {
            $this->info('Concluído.');
        }

        return self::SUCCESS;
    }
}
