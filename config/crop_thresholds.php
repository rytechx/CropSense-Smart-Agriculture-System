<?php

// The legacy "temperature" key maps to the RS485 probe's soil temperature.
// It is retained to preserve the existing sensor/API contract.
return [
    "near_tolerance" => [
        "ph" => 0.20,
        "soil_temperature" => 2.00,
        "moisture" => 10,
        "nitrogen" => 5,
        "phosphorus" => 5,
        "potassium" => 20,
        "ec_fraction" => 0.10,
    ],
    // Temporary sensor-level interpretation thresholds for raw NPK readings.
    // These are intentionally separate from crop-specific suitability ranges.
    "npk_classification" => [
        "nitrogen" => ["low_max" => 19.99, "medium_max" => 40.00],
        "phosphorus" => ["low_max" => 14.99, "medium_max" => 30.00],
        "potassium" => ["low_max" => 79.99, "medium_max" => 150.00],
    ],
    // Authoritative validated crop ranges, 2026-09-06. EC uses microSiemens/cm.
    "crops" => [
        "corn" => [
            "name" => "Corn",
            "scientific_name" => "Zea mays",
            "recommended" => [
                "moisture" => ["min" => 60, "max" => 80],
                "soil_temperature" => ["min" => 20, "max" => 30],
                "ec" => ["min" => 0, "max" => 1700, "max_exclusive" => true],
                "ph" => ["min" => 6, "max" => 6.8],
                "nitrogen" => ["min" => 20, "max" => 30],
                "phosphorus" => ["min" => 15, "max" => 25],
                "potassium" => ["min" => 120, "max" => 180],
            ],
        ],
        "tobacco" => [
            "name" => "Tobacco",
            "scientific_name" => "Nicotiana tabacum",
            "recommended" => [
                "moisture" => ["min" => 60, "max" => 80],
                "soil_temperature" => ["min" => 20, "max" => 30],
                "ec" => ["min" => 0, "max" => 2000, "max_exclusive" => true],
                "ph" => ["min" => 5.8, "max" => 6.2],
                "nitrogen" => ["min" => 15, "max" => 25],
                "phosphorus" => ["min" => 20, "max" => 30],
                "potassium" => ["min" => 150, "max" => 250],
            ],
        ],
        "rice" => [
            "name" => "Rice",
            "scientific_name" => "Oryza sativa",
            "recommended" => [
                "moisture" => ["min" => 90, "max" => 100],
                "soil_temperature" => ["min" => 25, "max" => 30],
                "ec" => ["min" => 0, "max" => 3000, "max_exclusive" => true],
                "ph" => ["min" => 5.5, "max" => 6.5],
                "nitrogen" => ["min" => 15, "max" => 25],
                "phosphorus" => ["min" => 10, "max" => 20],
                "potassium" => ["min" => 80, "max" => 150],
            ],
        ],
    ],
];
