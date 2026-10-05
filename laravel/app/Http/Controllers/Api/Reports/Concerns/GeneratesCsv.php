<?php

namespace App\Http\Controllers\Api\Reports\Concerns;

trait GeneratesCsv
{
    private function generateCSV($data, $filename, $headers)
    {
        $filename = $filename.'_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($data as $row) {
                fputcsv($file, array_values((array) $row));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
