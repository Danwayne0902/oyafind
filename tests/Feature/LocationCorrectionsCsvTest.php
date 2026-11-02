<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The shipped correction mapping is replayed on production, so its rows must
 * always point at real catalogue slugs and carry the evidence that justified
 * each move. This guards against typos in the CSV reaching a deploy.
 */
class LocationCorrectionsCsvTest extends TestCase
{
    public function test_every_correction_points_at_a_catalogued_location_with_evidence(): void
    {
        $catalogue = $this->catalogueSlugs();
        $this->assertNotEmpty($catalogue);

        $rows = $this->correctionRows();
        $this->assertNotEmpty($rows);

        foreach ($rows as $line => $row) {
            $this->assertArrayHasKey('slug', $row, "Line {$line} has no listing slug.");
            $this->assertArrayHasKey('location_slug', $row, "Line {$line} has no location slug.");
            $this->assertNotSame('', $row['slug'], "Line {$line} has an empty listing slug.");
            $this->assertNotSame('', $row['evidence'], "Line {$line} has no evidence.");
            $this->assertContains(
                $row['location_slug'],
                $catalogue,
                "Line {$line} points at an unknown location slug: {$row['location_slug']}",
            );
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function correctionRows(): array
    {
        $path = database_path('seeders/data/location_corrections.csv');
        $this->assertFileExists($path);

        $handle = fopen($path, 'r');
        $this->assertNotFalse($handle);

        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null] || trim(implode('', $values)) === '') {
                continue;
            }

            $record = [];
            foreach ($headers as $index => $header) {
                $record[$header] = trim((string) ($values[$index] ?? ''));
            }
            $rows[] = $record;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function catalogueSlugs(): array
    {
        $slugs = [];

        foreach (['nigeria/states.csv', 'nigeria/lgas.csv', 'global/locations.csv'] as $relative) {
            $handle = fopen(database_path('seeders/data/'.$relative), 'r');
            if ($handle === false) {
                continue;
            }

            $headers = array_map('trim', fgetcsv($handle) ?: []);
            $slugIndex = array_search('slug', $headers, true);

            if ($slugIndex !== false) {
                while (($values = fgetcsv($handle)) !== false) {
                    $slug = trim((string) ($values[$slugIndex] ?? ''));
                    if ($slug !== '') {
                        $slugs[] = $slug;
                    }
                }
            }

            fclose($handle);
        }

        return $slugs;
    }
}
