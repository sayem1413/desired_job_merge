<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DesiredSkill;

class DesiredSkillMerge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'desired-skill:merge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge desired skills table merge from csv data';

    protected int $strongMatch = 95;
    protected int $partialMatch = 80;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $report = $this->analyze();

        return $this->updateTable($report);
    }

    private function updateTable($report)
    {
        $parentMatchCount = 0;
        $parentNotMatchCount = 0;
        $childMatchCount = 0;
        $childNotMatchCount = 0;

        foreach ($report as $item) {
            $parentCategoryId = null;
            $parentData = $item['parent'];
            $childrens = $item['children'];
            if ($this->isSafe($item['category'], $parentData['db_title'], $parentData['score']) && $parentData['status'] == 'Strong Match' && $parentData['db_id']) {
                $parentCategoryId = $parentData['db_id'];
                $parentMatchCount++;
            } else {
                $parentCategory = DesiredSkill::create([
                    'title' => $item['category'],
                    'title_bn' => $item['category'],
                    'active_status' => 'Active'
                ]);
                $parentCategoryId  = $parentCategory->id;
                $parentNotMatchCount++;
            }

            if ($parentCategoryId || true) {
                foreach ($childrens as $children) {
                    if ($this->isSafe($children['csv'], $children['db_title'], $children['score']) && $children['status'] == 'Strong Match' && $children['db_id']) {
                        $skill = DesiredSkill::where('id', $children['db_id'])->first();
                        
                        if($skill) {
                            $skill->parent_id = $parentCategoryId;
                            $skill->active_status = 'Active';

                            if(!$skill?->bmet_reference_code) {
                                $skill->bmet_reference_code = $children['bmet_reference_code'];
                            }

                            $skill->save();
                            $childMatchCount++;
                        }
                    } else {
                        DesiredSkill::create([
                            'title' => $children['csv'],
                            'title_bn' => $children['csv'],
                            'parent_id' => $parentCategoryId,
                            'active_status' => 'Active',
                            'bmet_reference_code' => $children['bmet_reference_code'],
                        ]);
                        $childNotMatchCount++;
                    }
                }
            }
        }

        return [
            'Parent Found' => $parentMatchCount,
            'Parent Created' => $parentNotMatchCount,
            'Child Updated' => $parentNotMatchCount,
            'Child Created' => $childNotMatchCount,
        ];
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

        $dbIndex = DesiredSkill::all()->map(fn($job) => [
            'id' => $job->id,
            'title' => $job->title,
            'norm'  => $this->normalize($job->title),
            'words' => $this->tokens($job->title),
        ])
            ->toArray();

        $grouped = collect($rows)->groupBy('Category');

        $report = [];

        foreach ($grouped as $csv_category => $items) {

            [$parentMatchId, $parentMatch, $parentScore] = $this->bestMatch($csv_category, $dbIndex);

            $children = [];

            foreach ($items as $row) {
                $child = trim($row['Title']);

                [$childMatchId, $childMatch, $childScore] = $this->bestMatch(trim($child), $dbIndex);

                $children[] = [
                    'csv'    => $child,
                    'db_id'  => $childMatchId,
                    'db_title'  => $childMatch,
                    'match'  => $childMatch,
                    'score'  => $childScore,
                    'status' => $this->status($childScore),
                    'bmet_reference_code' => $row['BMET_reference_code'] ? (int) $row['BMET_reference_code'] : null
                ];
            }

            $report[] = [
                'category' => $csv_category,
                'parent' => [
                    'db_id'  => $parentMatchId,
                    'db_title'  => $parentMatch,
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
                $bestId = $item['id'];
                $bestTitle = $item['title'];
            }
        }

        return [$bestId, $bestTitle, $bestScore];
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

    private function isSafe(string $csv, ?string $db, int $score): bool
    {
        if (!$db) return false;

        return $score >= $this->strongMatch &&
            $this->normalize($csv) === $this->normalize($db);
    }
}
