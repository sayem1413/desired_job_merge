<?php

namespace App\Http\Controllers;

use App\Models\DesiredJob;
use Illuminate\Support\Facades\DB;

class DesiredJobTableController extends Controller
{
    public function index() // From manual code and will take suggeation from chatgpt with this logic
    {
        $path = storage_path('app/file/jobs.csv');

        $csv_rows = [];
        $header = null;

        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {

                if (!$header) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                    $header = $data;
                    continue;
                }

                $csv_rows[] = array_combine($header, $data);
            }
            fclose($handle);
        }

        /* dd(
            count($csv_rows),
            $csv_rows[0],
        ); */

        foreach ($csv_rows as $csv_row) {
            $parent_category_title = $csv_row['Category'];
            $child_category_title = $csv_row['Title'];

            $parent_desired_job = DesiredJob::where('title', 'like', '%' . $parent_category_title . '%')
                ->orWhere('title_bn', 'like', '%' . $parent_category_title . '%')
                ->first();

            if (empty($parent_desired_job)) {
                /* $parent_desired_job = DesiredJob::created([
                    'title' => $parent_category_title,
                    'title_bn' => $parent_category_title
                ]); */
                dump('parent created');
            } else {
                dump('parent found');
            }

            $child_desired_job = DesiredJob::where('title', 'like', '%' . $child_category_title . '%')
                ->orWhere('title_bn', 'like', '%' . $child_category_title . '%')
                ->first();

            if (empty($child_desired_job)) {
                /* $child_desired_job = DesiredJob::created([
                    'title' => $child_category_title,
                    'title_bn' => $child_category_title,
                    'parent_id' => !empty($parent_desired_job) ? $parent_desired_job?->id : null,
                ]); */
                dump('child created');
            } else {
                /* $child_desired_job->update([
                    'title' => $child_category_title,
                    'title_bn' => $child_category_title,
                    'parent_id' => !empty($parent_desired_job) ? $parent_desired_job?->id : null,
                ]); */
                dump('child updated');
            }
        }

        dd('finished');

        DB::transaction(function () use ($path) {

            $handle = fopen($path, 'r');

            $header = null;

            $parentCache = [];

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {

                if (!$header) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                    $header = $row;
                    continue;
                }

                $data = array_combine($header, $row);

                $parentTitle = trim($data['Category']);
                $childTitle  = trim($data['Title']);

                if (!isset($parentCache[$parentTitle])) {
                    $parentCache[$parentTitle] = DesiredJob::firstOrCreate(
                        ['title' => $parentTitle],
                        ['title_bn' => $parentTitle]
                    );
                }

                $parent = $parentCache[$parentTitle];

                DesiredJob::updateOrCreate(
                    [
                        'title'     => $childTitle,
                        'parent_id' => $parent->id,
                    ],
                    [
                        'title_bn'  => $childTitle,
                    ]
                );
            }

            fclose($handle);
        });
    }

    public function index2() // From chat gpt
    {
        $path = storage_path('app/file/jobs.csv');

        if (!file_exists($path)) {
            abort(404, 'CSV not found');
        }

        $rows = $this->readCsv($path);

        DB::transaction(fn() => $this->importJobs($rows));

        return response()->json([
            'status' => 'success',
            'count'  => count($rows),
        ]);
    }

    /**
     * Read CSV into associative array
     */
    private function readCsv(string $path): array
    {
        $rows   = [];
        $header = null;

        $handle = fopen($path, 'r');

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {

            if (!$header) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                $header = $data;
                continue;
            }

            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Import jobs with O(n) complexity
     */
    private function importJobs(array $rows): void
    {
        $parents = collect($rows)
            ->pluck('Category')
            ->map(fn($v) => trim($v))
            ->unique()
            ->values();

        $existingParents = DesiredJob::whereNull('parent_id')
            ->whereIn('title', $parents)
            ->get()
            ->keyBy('title');

        $newParents = $parents
            ->diff($existingParents->keys())
            ->map(fn($title) => [
                'title'      => $title,
                'title_bn'   => $title,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DesiredJob::insert($newParents->toArray());

        $parentMap = DesiredJob::whereNull('parent_id')
            ->whereIn('title', $parents)
            ->get()
            ->keyBy('title');

        $children = collect($rows)->map(function ($row) use ($parentMap) {
            return [
                'title'      => trim($row['Title']),
                'title_bn'   => trim($row['Title']),
                'parent_id'  => $parentMap[trim($row['Category'])]->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->unique(fn($row) => $row['title'] . '-' . $row['parent_id']);

        DesiredJob::upsert(
            $children->toArray(),
            ['title', 'parent_id'],
            ['title_bn', 'updated_at']
        );
    }
}
