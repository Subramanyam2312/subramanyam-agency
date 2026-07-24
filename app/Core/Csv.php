<?php

declare(strict_types=1);

namespace App\Core;

final class Csv
{
    /**
     * Builds a downloadable CSV response from a list of rows.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string>|null      $headings column => label; defaults to the first row's keys
     */
    public static function download(string $filename, array $rows, ?array $headings = null): Response
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return Response::make('Could not build the export.', 500);
        }

        // BOM so Excel opens UTF-8 correctly instead of mangling accented names.
        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows !== []) {
            $headings ??= array_combine(array_keys($rows[0]), array_keys($rows[0]));

            fputcsv($handle, array_values($headings));

            foreach ($rows as $row) {
                $line = [];

                foreach (array_keys($headings) as $column) {
                    $line[] = self::neutralise((string) ($row[$column] ?? ''));
                }

                fputcsv($handle, $line);
            }
        }

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);

        return Response::make($body)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Defuses CSV injection.
     *
     * A submitted message beginning =, +, - or @ is treated as a formula by Excel
     * and Google Sheets. Since this data comes straight from a public form, a
     * prefixed apostrophe is the difference between an export and an attack on
     * whoever opens it.
     */
    private static function neutralise(string $value): string
    {
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'" . $value;
        }

        return $value;
    }
}
