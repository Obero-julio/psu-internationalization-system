<?php

/**
 * Flat list of all PSU degree programs: full program name => short label.
 *
 * Built from config/psu_campuses.php (the campus-grouped source of truth).
 * The old version of this file used program names as array keys under several
 * campuses, so PHP kept only the last duplicate and campus info was lost.
 */
require_once __DIR__ . '/psu_academics.php';

return psu_all_programs();
