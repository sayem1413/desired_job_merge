<?php

namespace App\Http\Controllers;

use App\Models\DesiredJob;
use Barryvdh\DomPDF\Facade\Pdf;

class AnalysisReportV2Controller extends Controller
{
    protected int $strongMatch = 90;
    protected int $partialMatch = 70;

    public function downloadPdfReportV2()
    {
        ini_set('memory_limit', '512M');

        $report = $this->analyze();

        $pdf = Pdf::loadView('reports.job-analysis-v2', [
                'report' => $report,
                'generatedAt' => now('Asia/Dhaka'),
            ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

        return $pdf->download(
            'analysis-report-v2-' . now('Asia/Dhaka')->format('Ymd_His') . '.pdf'
        );
    }

    /**
     * Core data analysis (read-only)
     */
    private function analyze(): array
    {
        $csvPath = storage_path('app/files/jobs.csv');

        if (!file_exists($csvPath)) {
            return [];
        }

        $rows = $this->readCsv($csvPath);

        $dbIndex = DesiredJob::pluck('title')
            ->map(fn ($t) => [
                'title' => $t,
                'norm'  => $this->normalize($t),
                'words' => $this->tokens($t),
            ])
            ->toArray();

        $grouped = collect($rows)->groupBy('Category');
        $report = [];

        foreach ($grouped as $category => $items) {

            [$parentMatch, $parentScore] = $this->bestMatch($category, $dbIndex);

            $children = [];

            foreach ($items as $row) {
                $child = trim($row['Title']);

                [$childMatch, $childScore] = $this->bestMatch($child, $dbIndex);

                $children[] = [
                    'csv'    => $child,
                    'match'  => $childMatch,
                    'score'  => $childScore,
                    'status' => $this->status($childScore),
                ];
            }

            $report[] = [
                'category' => $category,
                'parent' => [
                    'match'  => $parentMatch,
                    'score'  => $parentScore,
                    'status' => $this->status($parentScore),
                ],
                'children' => $children,
            ];
        }

        return $report;
    }

    /**
     * Hybrid fuzzy matcher (BEST PRACTICE)
     */
    private function bestMatch(string $needle, array $dbIndex): array
    {
        $needleNorm  = $this->normalize($needle);
        $needleWords = $this->tokens($needle);

        $bestTitle = null;
        $bestScore = 0;

        foreach ($dbIndex as $item) {

            $jaccard = $this->jaccard($needleWords, $item['words']);

            similar_text($needleNorm, $item['norm'], $charPercent);

            $score = max(round($jaccard), round($charPercent));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTitle = $item['title'];
            }
        }

        return [$bestTitle, $bestScore];
    }

    private function jaccard(array $a, array $b): int
    {
        if (!$a || !$b) return 0;

        $intersection = array_intersect($a, $b);
        $union = array_unique(array_merge($a, $b));

        return round((count($intersection) / count($union)) * 100);
    }

    private function normalize(string $v): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', trim($v)));
    }

    private function tokens(string $v): array
    {
        $v = strtolower(preg_replace('/[^a-z0-9\s]/i', '', $v));
        return array_values(array_filter(explode(' ', $v)));
    }

    private function status(int $p): string
    {
        return $p >= $this->strongMatch
            ? 'Strong Match'
            : ($p >= $this->partialMatch ? 'Partial Match' : 'No Match');
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $header = null;

        if (($h = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($h, 1000, ',')) !== false) {
                if (!$header) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                    $header = $data;
                    continue;
                }
                $rows[] = array_combine($header, $data);
            }
            fclose($h);
        }

        return $rows;
    }
}
