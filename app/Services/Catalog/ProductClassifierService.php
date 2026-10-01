<?php

namespace App\Services\Catalog;

/**
 * Classifica produtos em categorias e extrai características (armazenamento,
 * cor) a partir do nome, com regras derivadas do catálogo real sincronizado
 * do ERP (ver docs/Product-Classification.md). Não usa IA — é um classificador
 * baseado em palavras-chave, auditável e determinístico.
 */
class ProductClassifierService
{
    /**
     * Ordem importa: primeira regra cujo termo aparece no nome (case-insensitive)
     * vence. Categorias mais específicas ficam antes das mais genéricas.
     *
     * @var array<string, array{name: string, terms: array<int, string>}>
     */
    private const CATEGORY_RULES = [
        'tablet' => ['name' => 'Tablet', 'terms' => ['IPAD']],
        'notebook' => ['name' => 'Notebook', 'terms' => ['MACBOOK', 'MACMINI', 'MAC MINI']],
        'smartwatch' => ['name' => 'Smartwatch', 'terms' => ['WATCH']],
        'celular' => ['name' => 'Celular', 'terms' => ['IPHONE', 'XIAOMI', 'REDMI']],
        'perfume' => ['name' => 'Perfume', 'terms' => ['PERF ']],
        'estetica' => ['name' => 'Estética', 'terms' => ['BOTOX', 'PREENCHEDOR', 'MASCARA CAPILAR']],
        'performance-longevidade' => [
            'name' => 'Performance & Longevidade',
            'terms' => [
                'MOUNJARO', 'TIRZEPATIDE', 'TIRZEPATIDA', 'RETATRUDITE', 'RETATRUTIDE',
                'TB500', 'CJC-IPA', 'GHK-CU', 'GLOW', 'NAD+', 'LANDERGOLD',
            ],
        ],
        'games' => ['name' => 'Games', 'terms' => ['PLAYSTATION']],
        'eletronicos' => ['name' => 'Eletrônicos', 'terms' => ['FIRE STICK', 'LIQUIDIFICADOR', 'OCULOS']],
        'acessorios' => ['name' => 'Acessórios', 'terms' => ['AIRTAG', 'CABO', 'USB-C']],
    ];

    private const FALLBACK_CATEGORY = ['slug' => 'outros', 'name' => 'Outros'];

    /**
     * Dicionário de cores ordenado das frases mais longas/específicas pras mais
     * curtas/genéricas, pra "SPACE GRAY"/"ROSE GOLD"/"CLOUD WHITE" baterem antes
     * do fallback genérico "GRAY"/"GOLD"/"WHITE". Chave = termo a procurar no
     * nome (maiúsculo), valor = nome de exibição da cor.
     *
     * @var array<string, string>
     */
    private const COLOR_DICTIONARY = [
        'SPACE GRAY' => 'Space Gray',
        'SPACE BLACK' => 'Space Black',
        'ROSE GOLD' => 'Rose Gold',
        'CLOUD WHITE' => 'Cloud White',
        'LIGHT GOLD' => 'Light Gold',
        'SKYBLUE' => 'Sky Blue',
        'SKY BLUE' => 'Sky Blue',
        'ULTRAMARINE' => 'Ultramarine',
        'MIDNIGHT' => 'Midnight',
        'STARLIGHT' => 'Starlight',
        'LAVANDER' => 'Lavender',
        'LAVENDER' => 'Lavender',
        'GRAFITE' => 'Grafite',
        'GRAPHITE' => 'Graphite',
        'DESERT' => 'Desert',
        'NATURAL' => 'Natural',
        'BURGUNDY' => 'Burgundy',
        'GLACIER' => 'Glacier',
        'SAGE' => 'Sage',
        'BLACK' => 'Black',
        'WHITE' => 'White',
        'SILVER' => 'Silver',
        'BLUE' => 'Blue',
        'GOLD' => 'Gold',
        'PURPLE' => 'Purple',
        'PINK' => 'Pink',
        'GREEN' => 'Green',
        'TEAL' => 'Teal',
        'ORANGE' => 'Orange',
        'GRAY' => 'Gray',
        'GREY' => 'Gray',
        'YELLOW' => 'Yellow',
    ];

    /**
     * @return array{slug: string, name: string}
     */
    public function classifyCategory(string $name): array
    {
        $haystack = strtoupper($name);

        foreach (self::CATEGORY_RULES as $slug => $rule) {
            foreach ($rule['terms'] as $term) {
                if (str_contains($haystack, $term)) {
                    return ['slug' => $slug, 'name' => $rule['name']];
                }
            }
        }

        return self::FALLBACK_CATEGORY;
    }

    /**
     * Pega o maior valor entre todas as ocorrências de "NNGB"/"NNTB" no nome,
     * não a primeira — porque a ordem RAM/Armazenamento varia entre linhas de
     * produto no ERP (ex.: "16GB/256GB SSD" vs "256GB/8GB"), mas armazenamento
     * é sempre maior que RAM nos produtos deste catálogo, então o maior valor
     * é sempre o armazenamento real.
     */
    public function extractStorage(string $name): ?string
    {
        if (!preg_match_all('/(\d+)\s*(GB|TB)\b/i', $name, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $best = null;
        $bestGb = -1;

        foreach ($matches as $m) {
            $value = (int) $m[1];
            $unit = strtoupper($m[2]);
            $gb = $unit === 'TB' ? $value * 1024 : $value;

            if ($gb > $bestGb) {
                $bestGb = $gb;
                $best = $value . $unit;
            }
        }

        return $best;
    }

    public function extractColor(string $name): ?string
    {
        $term = $this->matchedColorTerm($name);

        return $term ? self::COLOR_DICTIONARY[$term] : null;
    }

    private function matchedColorTerm(string $name): ?string
    {
        $haystack = strtoupper($name);

        foreach (self::COLOR_DICTIONARY as $term => $label) {
            if (str_contains($haystack, $term)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Palavras/tokens sem valor pra identificar o "modelo" de um produto —
     * códigos de região/mercado do ERP (US/UK/JP/LL/ITA/INDIANO), condição
     * cosmética de swap (GRADE A/AA/A+) e ruído de embalagem/chip. Removidos
     * como palavras inteiras (word boundary), não como substring, pra não
     * comer pedaços de palavras legítimas.
     *
     * @var array<int, string>
     */
    private const MODEL_NOISE_WORDS = [
        'C/CHIP', 'S/CAIXA', 'GRADE A+', 'GRADE AA', 'GRADE A',
        'INDIANO', 'ITA', 'JP', 'LL', 'UK', 'US',
    ];

    /**
     * "Modelo" = nome do produto sem armazenamento, cor e ruído de região/
     * condição — usado pra agrupar variantes do mesmo produto (cor/tamanho
     * diferente) como "irmãos" na API do Orbita (ver
     * OrbitaCatalogService::variantsFor()). Mantém prefixos de condição como
     * "SWAP"/"CPO"/"AS-IS" — são produtos/SKUs diferentes, não uma variante de
     * cor do mesmo item.
     *
     * Heurística por remoção, não por reconstrução: mais robusto a nomes
     * inconsistentes do ERP do que tentar "adivinhar" o modelo do zero.
     */
    public function extractModel(string $name): string
    {
        $model = strtoupper($name);

        // Remove todas as ocorrências de tamanho (não só a "vencedora" de
        // extractStorage), pra sumir tanto o armazenamento quanto a RAM
        // quando os dois aparecem juntos (ex.: "16GB/256GB").
        $model = preg_replace('/\d+\s*(GB|TB)\b/i', '', $model) ?? $model;

        if ($colorTerm = $this->matchedColorTerm($name)) {
            $model = str_ireplace($colorTerm, '', $model);
        }

        foreach (self::MODEL_NOISE_WORDS as $word) {
            $pattern = '#\b' . preg_quote($word, '#') . '\b#i';
            $model = preg_replace($pattern, '', $model) ?? $model;
        }

        // Sobras de separador deixadas pela remoção acima (ex.: "M4 16GB/256GB
        // SSD" -> "M4 /SSD" -> "M4 SSD").
        $model = preg_replace('/\s*\/\s*/', ' ', $model) ?? $model;
        $model = preg_replace('/\s+/', ' ', $model) ?? $model;
        $model = trim($model, " -/\t\n\r\0\x0B");

        return $model !== '' ? $model : strtoupper(trim($name));
    }
}
