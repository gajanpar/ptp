<?php

namespace App\Services;

use App\Models\EvaluationDetail;

class EvaluationScoringService
{
    public const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public const AKHLAK_PILLARS = [
        'Amanah', 'Kompeten', 'Harmonis', 'Loyal', 'Adaptif', 'Kolaboratif',
    ];

    public const RATING_SCORES = [
        1 => 0.98,
        2 => 1.96,
        3 => 2.94,
        4 => 3.92,
        5 => 4.90,
        6 => 5.88,
    ];

    public const RATING_LABELS = [
        1 => 'Kurang Sekali',
        2 => 'Kurang',
        3 => 'Cukup',
        4 => 'Cukup Baik',
        5 => 'Baik',
        6 => 'Sangat Baik',
    ];

    public const RATING_COLORS = [
        1 => '#b91c1c', // Red-700 (6.47:1 WCAG AA on #FFFFFF & #FFFFFF on fill)
        2 => '#c2410c', // Orange-700 (5.18:1 WCAG AA)
        3 => '#a16207', // Amber-700 (5.38:1 WCAG AA)
        4 => '#0369a1', // Sky-700 (5.93:1 WCAG AA)
        5 => '#15803d', // Green-700 (5.02:1 WCAG AA)
        6 => '#166534', // Green-800 (7.13:1 WCAG AA)
    ];

    public static function getRatingScore(int $rating): float
    {
        return self::RATING_SCORES[$rating] ?? 0.0;
    }

    public static function getRatingLabel(int $rating): string
    {
        return self::RATING_LABELS[$rating] ?? '-';
    }

    public static function getRatingOptions(): array
    {
        $options = [];
        for ($i = 1; $i <= 6; $i++) {
            $options[$i] = [
                'rating' => $i,
                'label' => self::RATING_LABELS[$i],
                'score' => self::RATING_SCORES[$i],
                'color' => self::RATING_COLORS[$i],
            ];
        }

        return $options;
    }

    public static function getPredicate(float $totalScore): string
    {
        if ($totalScore >= 85.0) {
            return 'Sangat Baik';
        }
        if ($totalScore >= 70.0) {
            return 'Baik';
        }
        if ($totalScore >= 55.0) {
            return 'Cukup Baik';
        }

        return 'Perlu Perhatian';
    }

    public static function getBadgeClass(float $totalScore): string
    {
        if ($totalScore >= 85.0) {
            return 'badge-success';
        }
        if ($totalScore >= 70.0) {
            return 'badge-info';
        }
        if ($totalScore >= 55.0) {
            return 'badge-warning';
        }

        return 'badge-danger';
    }

    public static function calculate(array $ratings): array
    {
        $totalScore = 0.0;
        $count = count($ratings);
        $details = [];

        foreach ($ratings as $subcategoryId => $rating) {
            $ratingInt = (int) $rating;
            $score = self::getRatingScore($ratingInt);
            $totalScore += $score;

            $details[$subcategoryId] = [
                'rating' => $ratingInt,
                'score' => $score,
            ];
        }

        $totalScore = round($totalScore, 2);
        $averageScore = $count > 0 ? round($totalScore / $count, 2) : 0.0;
        $predicate = self::getPredicate($totalScore);

        return [
            'total_score' => $totalScore,
            'average_score' => $averageScore,
            'predicate' => $predicate,
            'details' => $details,
        ];
    }

    public static function getMonthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? '-';
    }

    /**
     * Group evaluation detail rows by their parent AKHLAK category safely.
     */
    public static function groupDetailsByCategory(iterable $details): array
    {
        $grouped = [];

        foreach ($details as $detail) {
            $category = $detail->subcategory?->category;
            if (! $category) {
                continue;
            }

            $categoryId = $category->id;
            if (! isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [
                    'category' => $category,
                    'items' => [],
                ];
            }

            $grouped[$categoryId]['items'][] = $detail;
        }

        return $grouped;
    }

    /**
     * Compute averaged scores across the 6 AKHLAK core pillars using a single grouped query.
     *
     * @param  iterable<int, int>  $evalIds
     * @return array<string, array{name: string, raw_avg: float, score_100: float}>
     */
    public static function computeAkhlakStats(iterable $evalIds): array
    {
        $ids = collect($evalIds)->filter()->values();
        $catAverages = collect();

        if ($ids->isNotEmpty()) {
            $catAverages = EvaluationDetail::whereIn('evaluation_details.evaluation_id', $ids)
                ->join('evaluation_subcategories', 'evaluation_details.subcategory_id', '=', 'evaluation_subcategories.id')
                ->join('evaluation_categories', 'evaluation_subcategories.category_id', '=', 'evaluation_categories.id')
                ->selectRaw('evaluation_categories.name, AVG(evaluation_details.score) as avg_score')
                ->groupBy('evaluation_categories.id', 'evaluation_categories.name', 'evaluation_categories.order_index')
                ->orderBy('evaluation_categories.order_index')
                ->pluck('avg_score', 'name');
        }

        $stats = [];
        foreach (self::AKHLAK_PILLARS as $pillarName) {
            $avgScore = $catAverages->get($pillarName);
            $stats[$pillarName] = [
                'name' => $pillarName,
                'raw_avg' => $avgScore ? round((float) $avgScore, 2) : 0.0,
                'score_100' => $avgScore ? round(((float) $avgScore / 5.88) * 100, 1) : 0.0,
            ];
        }

        return $stats;
    }
}
