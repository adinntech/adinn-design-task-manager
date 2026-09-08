<?php

return [
    // Number of concurrent active (non-completed) tasks a designer can comfortably
    // handle before the BD "Designer Availability" meter reads 0% available.
    'designer_capacity' => (int) env('DESIGNER_CAPACITY', 10),
];
