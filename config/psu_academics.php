<?php

/**
 * Shared helpers for participant academic / employment details
 * (campus, program, classification-specific fields).
 */

const PSU_INSTITUTION = 'Pangasinan State University';

if (!function_exists('psu_campuses')) {

    /** campus => [full program name => short label] */
    function psu_campuses(): array {
        static $campuses = null;
        if ($campuses === null) {
            $campuses = require __DIR__ . '/psu_campuses.php';
        }
        return $campuses;
    }

    /** Flat list of every program across campuses: full name => short label. */
    function psu_all_programs(): array {
        $all = [];
        foreach (psu_campuses() as $programs) {
            foreach ($programs as $name => $label) {
                if (!isset($all[$name])) {
                    $all[$name] = $label;
                }
            }
        }
        ksort($all, SORT_NATURAL | SORT_FLAG_CASE);
        return $all;
    }

    function psu_is_valid_campus(string $campus): bool {
        return $campus !== '' && array_key_exists($campus, psu_campuses());
    }

    function psu_campus_offers(string $campus, string $program): bool {
        return psu_is_valid_campus($campus) && array_key_exists($program, psu_campuses()[$campus]);
    }

    /** Returns the campus when exactly one campus offers the program (used for older accounts with no campus saved). */
    function psu_infer_campus(string $program): ?string {
        $found = [];
        foreach (psu_campuses() as $campus => $programs) {
            if (array_key_exists($program, $programs)) {
                $found[] = $campus;
            }
        }
        return count($found) === 1 ? $found[0] : null;
    }

    function psu_year_levels(): array {
        return ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Graduate Student'];
    }

    function psu_faculty_ranks(): array {
        return [
            'Instructor',
            'Assistant Professor',
            'Associate Professor',
            'Professor',
            'Lecturer / Part-time Faculty',
            'Administrative Staff',
        ];
    }
}
