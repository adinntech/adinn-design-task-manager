<?php

return [
    // Number of concurrent active (non-completed) tasks a designer can comfortably
    // handle before the BD "Designer Availability" meter reads 0% available.
    'designer_capacity' => (int) env('DESIGNER_CAPACITY', 10),

    // Minimum availability % required for each band (checked top-down); anything
    // below 'busy' falls through to "critical". Only remaps the existing
    // availability_percent value into a 4-way status — does not change how
    // availability_percent itself is calculated.
    'availability_thresholds' => [
        'available' => (int) env('DESIGNER_AVAILABILITY_AVAILABLE_MIN', 70),
        'moderate' => (int) env('DESIGNER_AVAILABILITY_MODERATE_MIN', 40),
        'busy' => (int) env('DESIGNER_AVAILABILITY_BUSY_MIN', 15),
    ],
];
