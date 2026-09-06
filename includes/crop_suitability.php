<?php

if (!function_exists("cropsense_crop_config")) {
    function cropsense_crop_config()
    {
        $configPath = __DIR__ . "/../config/crop_thresholds.php";
        $config = is_file($configPath) ? require $configPath : [];

        return is_array($config) ? $config : ["crops" => []];
    }
}

if (!function_exists("cropsense_parameter_definitions")) {
    function cropsense_parameter_definitions()
    {
        return [
            "moisture" => [
                "label" => "Soil Moisture",
                "unit" => "%",
                "reading_fields" => ["moisture", "soil_moisture"],
            ],
            "soil_temperature" => [
                "label" => "Soil Temperature",
                "unit" => "°C",
                "reading_fields" => ["temperature", "soil_temperature"],
            ],
            "ec" => [
                "label" => "Electrical Conductivity (EC)",
                "unit" => "µS/cm",
                "reading_fields" => ["ec", "soil_ec"],
            ],
            "ph" => [
                "label" => "Soil pH",
                "unit" => "",
                "reading_fields" => ["ph", "soil_ph"],
            ],
            "nitrogen" => [
                "label" => "Nitrogen",
                "unit" => "mg/kg",
                "reading_fields" => ["nitrogen"],
            ],
            "phosphorus" => [
                "label" => "Phosphorus",
                "unit" => "mg/kg",
                "reading_fields" => ["phosphorus"],
            ],
            "potassium" => [
                "label" => "Potassium",
                "unit" => "mg/kg",
                "reading_fields" => ["potassium"],
            ],
        ];
    }
}

if (!function_exists("cropsense_numeric_value")) {
    function cropsense_numeric_value($value)
    {
        if (
            $value === null ||
            (is_string($value) && trim($value) === "") ||
            !is_numeric($value)
        ) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }
}

if (!function_exists("cropsense_number_value")) {
    function cropsense_number_value($reading, $key)
    {
        if (!$reading || !array_key_exists($key, $reading)) {
            return null;
        }

        return cropsense_numeric_value($reading[$key]);
    }
}

if (!function_exists("cropsense_reading_parameter_value")) {
    function cropsense_reading_parameter_value($reading, $definition)
    {
        if (!is_array($reading)) {
            return null;
        }

        foreach (($definition["reading_fields"] ?? []) as $field) {
            if (array_key_exists($field, $reading)) {
                return cropsense_numeric_value($reading[$field]);
            }
        }

        return null;
    }
}

if (!function_exists("cropsense_crop_key")) {
    function cropsense_crop_key($cropName)
    {
        $key = strtolower(trim((string) $cropName));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);

        return trim((string) $key, '_');
    }
}

if (!function_exists("cropsense_empty_ranges")) {
    function cropsense_empty_ranges()
    {
        return [
            "optimum" => null,
            "acceptable" => null,
            "marginal" => null,
        ];
    }
}

if (!function_exists("cropsense_valid_range")) {
    function cropsense_valid_range($minimum, $maximum)
    {
        $min = cropsense_numeric_value($minimum);
        $max = cropsense_numeric_value($maximum);

        if ($min === null || $max === null || $min > $max) {
            return null;
        }

        return ["min" => $min, "max" => $max];
    }
}

if (!function_exists("cropsense_table_exists")) {
    function cropsense_table_exists($connection, $tableName)
    {
        if (!$connection instanceof mysqli || $connection->connect_error) {
            return false;
        }

        $stmt = $connection->prepare(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("s", $tableName);
        $exists = $stmt->execute() && (bool) $stmt->get_result()->fetch_row();
        $stmt->close();

        return $exists;
    }
}

if (!function_exists("cropsense_get_crop_thresholds")) {
    function cropsense_get_crop_thresholds($connection = null)
    {
        // Validated configuration is authoritative; legacy database tiers must not override it.
        $config = cropsense_crop_config();
        $thresholds = [];
        foreach ($config["crops"] as $key => $crop) {
            $parameters = [];
            foreach (cropsense_parameter_definitions() as $code => $definition) {
                $range = $crop["recommended"][$code];
                $tolerance = $code === "ec"
                    ? $range["max"] * $config["near_tolerance"]["ec_fraction"]
                    : $config["near_tolerance"][$code];
                $parameters[$code] = [
                    "parameter_code" => $code,
                    "unit" => $definition["unit"],
                    "tolerance" => $tolerance,
                    "ranges" => [
                        "optimum" => null,
                        "acceptable" => $range,
                        "marginal" => ["min" => $code === "ec" ? 0 : $range["min"] - $tolerance,
                                       "max" => $range["max"] + $tolerance],
                    ],
                    "source" => "validated_config_20260906",
                ];
            }
            $thresholds[$key] = ["crop_key" => $key, "crop" => $crop["name"],
                "scientific_name" => $crop["scientific_name"], "parameters" => $parameters];
        }
        return $thresholds;
    }
}

if (!function_exists("cropsense_range_contains")) {
    function cropsense_range_contains($value, $range)
    {
        return is_array($range) &&
            isset($range["min"], $range["max"]) &&
            $value >= $range["min"] &&
            (!empty($range["max_exclusive"]) ? $value < $range["max"] : $value <= $range["max"]);
    }
}

if (!function_exists("cropsense_calculate_parameter_score")) {
    function cropsense_calculate_parameter_score($assessmentCode)
    {
        $scores = [
            "OPTIMUM" => 2,
            "ACCEPTABLE" => 2,
            "MARGINAL" => 1,
            "UNSUITABLE" => 0,
        ];

        return $scores[$assessmentCode] ?? null;
    }
}

if (!function_exists("cropsense_classify_parameter")) {
    function cropsense_classify_parameter($value, $threshold)
    {
        $numericValue = cropsense_numeric_value($value);
        $ranges = is_array($threshold["ranges"] ?? null)
            ? $threshold["ranges"]
            : cropsense_empty_ranges();

        if ($numericValue === null) {
            return [
                "assessment" => "NO_DATA",
                "assessment_label" => "NO DATA",
                "score" => null,
                "reason" => "No valid sensor value is available.",
            ];
        }

        $configuredRanges = array_filter($ranges, "is_array");
        if (!$configuredRanges) {
            return [
                "assessment" => "NOT_ASSESSED",
                "assessment_label" => "THRESHOLD NOT CONFIGURED",
                "score" => null,
                "reason" => "No validated crop threshold is configured for this parameter.",
            ];
        }

        foreach (["OPTIMUM" => "optimum", "ACCEPTABLE" => "acceptable", "MARGINAL" => "marginal"] as $code => $rangeKey) {
            if (cropsense_range_contains($numericValue, $ranges[$rangeKey] ?? null)) {
                return [
                    "assessment" => $code,
                    "assessment_label" => $code === "MARGINAL" ? "Near recommended range" : "Recommended range",
                    "score" => cropsense_calculate_parameter_score($code),
                    "reason" => "The measured value is inside the configured " . strtolower($code) . " range.",
                ];
            }
        }

        // A configured marginal range defines the outer assessed boundary.
        // Without it, CropSense cannot distinguish marginal from unsuitable.
        if (is_array($ranges["marginal"] ?? null)) {
            return [
                "assessment" => "UNSUITABLE",
                "assessment_label" => "Unsuitable",
                "score" => 0,
                "reason" => "The measured value is outside the configured marginal range.",
            ];
        }

        return [
            "assessment" => "NOT_ASSESSED",
            "assessment_label" => "THRESHOLD NOT CONFIGURED",
            "score" => null,
            "reason" => "A marginal boundary is required to classify this measured value safely.",
        ];
    }
}

if (!function_exists("cropsense_score_class")) {
    function cropsense_score_class($percentage)
    {
        if ($percentage === null || !is_numeric($percentage)) {
            return [
                "code" => "NA",
                "label" => "Not Assessed",
                "full_label" => "Not Assessed",
                "rank" => 0,
            ];
        }

        $value = (float) $percentage;
        if ($value >= 85) {
            return ["code" => "S1", "label" => "Highly Suitable", "full_label" => "S1 – Highly Suitable", "rank" => 4];
        }
        if ($value >= 70) {
            return ["code" => "S2", "label" => "Suitable", "full_label" => "S2 – Suitable", "rank" => 3];
        }
        if ($value >= 50) {
            return ["code" => "S3", "label" => "Marginally Suitable", "full_label" => "S3 – Marginally Suitable", "rank" => 2];
        }

        return ["code" => "N", "label" => "Not Suitable", "full_label" => "N – Not Suitable", "rank" => 1];
    }
}

if (!function_exists("cropsense_apply_limiting_factor_rule")) {
    function cropsense_apply_limiting_factor_rule($rawClass, $lowestScore)
    {
        // Compatibility helper: classification is now determined only by percentage.
        return $rawClass;
    }
}

if (!function_exists("cropsense_identify_limiting_factors")) {
    function cropsense_identify_limiting_factors($parameters)
    {
        $scored = array_values(array_filter($parameters, static function ($parameter) {
            return array_key_exists("score", $parameter) && $parameter["score"] !== null;
        }));

        if (!$scored) {
            return ["lowest_score" => null, "factors" => []];
        }

        $lowestScore = min(array_column($scored, "score"));
        $factors = [];

        if ($lowestScore < 2) {
            foreach ($scored as $parameter) {
                if ($parameter["score"] === $lowestScore) {
                    $factors[] = [
                        "parameter" => $parameter["parameter"],
                        "label" => $parameter["label"],
                        "value" => $parameter["value"],
                        "unit" => $parameter["unit"],
                        "assessment" => $parameter["assessment"],
                        "assessment_label" => $parameter["assessment_label"],
                        "score" => $parameter["score"],
                        "guidance" => $parameter["reason"],
                        "deviation" => $parameter["deviation"] ?? 0,
                    ];
                }
            }
        }

        usort($factors, static function ($a, $b) { return $b["deviation"] <=> $a["deviation"]; });
        return ["lowest_score" => $lowestScore, "factors" => $factors];
    }
}

if (!function_exists("cropsense_join_labels")) {
    function cropsense_join_labels($labels)
    {
        $labels = array_values(array_filter($labels));
        $count = count($labels);

        if ($count === 0) {
            return "";
        }
        if ($count === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(", ", $labels) . " and " . $last;
    }
}

if (!function_exists("cropsense_calculate_crop_score")) {
    function cropsense_calculate_crop_score($reading, $cropThreshold)
    {
        $definitions = cropsense_parameter_definitions();
        $parameters = [];
        $totalScore = 0;
        $assessedCount = 0;

        foreach ($definitions as $parameterCode => $definition) {
            $threshold = $cropThreshold["parameters"][$parameterCode] ?? [
                "unit" => $definition["unit"],
                "ranges" => cropsense_empty_ranges(),
                "source" => "not_configured",
            ];
            $value = cropsense_reading_parameter_value($reading, $definition);
            $classification = cropsense_classify_parameter($value, $threshold);
            $score = $classification["score"];

            if ($score !== null) {
                $assessedCount += 1;
                $totalScore += $score;
            }

            $recommended = $threshold["ranges"]["acceptable"] ?? null;
            $deviation = 0;
            if ($score !== null && $score < 2 && $recommended !== null) {
                $below = $value < $recommended["min"];
                $distance = $below ? $recommended["min"] - $value : $value - $recommended["max"];
                $deviation = $distance / max(0.000001, $threshold["tolerance"] ?? 1);
                $classification["reason"] = $definition["label"] . ($below ? " is below" : " is above")
                    . " the recommended range.";
                if (!empty($recommended["max_exclusive"]) && $value == $recommended["max"]) {
                    $classification["reason"] = $definition["label"] . " is at the excluded recommended maximum.";
                }
            }
            $parameters[] = [
                "deviation" => $deviation,
                "parameter" => $parameterCode,
                "label" => $definition["label"],
                "value" => $value,
                "unit" => $threshold["unit"] ?? $definition["unit"],
                "assessment" => $classification["assessment"],
                "assessment_label" => $classification["assessment_label"],
                "score" => $score,
                "maximum_score" => $score === null ? null : 2,
                "reason" => $classification["reason"],
                "threshold_source" => $threshold["source"] ?? "not_configured",
                "configured_ranges" => $threshold["ranges"] ?? cropsense_empty_ranges(),
            ];
        }

        $maximumScore = $assessedCount * 2;
        $percentage = $maximumScore > 0 ? ($totalScore / $maximumScore) * 100 : null;
        $rawClass = cropsense_score_class($percentage);
        $limiting = cropsense_identify_limiting_factors($parameters);
        $finalClass = cropsense_apply_limiting_factor_rule($rawClass, $limiting["lowest_score"]);
        $limitingReason = $limiting["factors"]
            ? "Main limitation: " . $limiting["factors"][0]["guidance"]
            : ($assessedCount > 0 ? "No limitation among the assessed parameters." : "NO DATA");
        $matchingCount = count(array_filter($parameters, static function ($p) { return $p["score"] === 2; }));
        $missingLabels = array_column(array_filter($parameters, static function ($p) { return $p["assessment"] === "NO_DATA"; }), "label");

        return [
            "crop_key" => $cropThreshold["crop_key"],
            "crop" => $cropThreshold["crop"],
            "scientific_name" => $cropThreshold["scientific_name"],
            "matching_parameters" => $matchingCount,
            "match_explanation" => $assessedCount > 0 ? $matchingCount . " of " . $assessedCount . " parameters match the recommended range." : "NO DATA: No sensor parameters are available.",
            "missing_parameters" => $missingLabels,
            "assessed_parameters" => $assessedCount,
            "total_parameters" => count($definitions),
            "score" => $totalScore,
            "maximum_score" => $maximumScore,
            "percentage" => $percentage === null ? null : round($percentage, 1),
            "raw_class" => $rawClass["code"],
            "raw_label" => $rawClass["label"],
            "raw_full_label" => $rawClass["full_label"],
            "final_class" => $finalClass["code"],
            "final_label" => $finalClass["label"],
            "final_full_label" => $finalClass["full_label"],
            "class_was_capped" => $finalClass["code"] !== $rawClass["code"],
            "lowest_parameter_score" => $limiting["lowest_score"],
            "limiting_factors" => $limiting["factors"],
            "limiting_factor_reason" => $limitingReason,
            "parameters" => $parameters,
        ];
    }
}

if (!function_exists("cropsense_final_class_rank")) {
    function cropsense_final_class_rank($code)
    {
        return ["S1" => 4, "S2" => 3, "S3" => 2, "N" => 1, "NA" => 0][$code] ?? 0;
    }
}

if (!function_exists("cropsense_rank_crop_recommendations")) {
    function cropsense_rank_crop_recommendations($recommendations)
    {
        usort($recommendations, static function ($left, $right) {
            $percentageComparison = ($right["percentage"] ?? -1) <=> ($left["percentage"] ?? -1);

            if ($percentageComparison !== 0) {
                return $percentageComparison;
            }

            return strcmp((string) ($left["crop"] ?? ""), (string) ($right["crop"] ?? ""));
        });

        foreach ($recommendations as $index => &$recommendation) {
            $recommendation["rank"] = $index + 1;
        }
        unset($recommendation);

        return $recommendations;
    }
}

if (!function_exists("cropsense_select_assessment_reading")) {
    function cropsense_select_assessment_reading($readingOrReadings)
    {
        if (!is_array($readingOrReadings) || !$readingOrReadings) {
            return ["reading" => null, "available_readings" => 0];
        }

        $looksLikeReading = false;
        foreach (cropsense_parameter_definitions() as $definition) {
            foreach ($definition["reading_fields"] as $field) {
                if (array_key_exists($field, $readingOrReadings)) {
                    $looksLikeReading = true;
                    break 2;
                }
            }
        }

        $readings = $looksLikeReading ? [$readingOrReadings] : array_values(array_filter($readingOrReadings, "is_array"));

        return [
            "reading" => $readings[0] ?? null,
            "available_readings" => count($readings),
        ];
    }
}

if (!function_exists("cropsense_assess_crop_suitability")) {
    function cropsense_assess_crop_suitability($connection, $readingOrReadings)
    {
        $selection = cropsense_select_assessment_reading($readingOrReadings);
        $thresholds = cropsense_get_crop_thresholds($connection);
        $recommendations = [];

        foreach ($thresholds as $cropThreshold) {
            $recommendations[] = cropsense_calculate_crop_score($selection["reading"], $cropThreshold);
        }

        $recommendations = cropsense_rank_crop_recommendations($recommendations);
        $topRecommendation = null;

        foreach ($recommendations as $recommendation) {
            if ($recommendation["assessed_parameters"] > 0) {
                $topRecommendation = [
                    "crop_key" => $recommendation["crop_key"],
                    "crop" => $recommendation["crop"],
                    "percentage" => $recommendation["percentage"],
                    "final_class" => $recommendation["final_class"],
                    "final_label" => $recommendation["final_label"],
                    "final_full_label" => $recommendation["final_full_label"],
                ];
                break;
            }
        }

        return [
            "title" => "Sensor-Based Crop Suitability Assessment",
            "class_method" => "Recommended range match: 2 points; near range: 1 point; outside: 0 points",
            "assessment_basis" => [
                "method" => "latest",
                "description" => "Latest available sensor reading",
                "sample_count" => $selection["reading"] ? 1 : 0,
                "available_readings" => $selection["available_readings"],
                "supports_future_recent_window" => true,
            ],
            "top_recommendation" => $topRecommendation,
            "recommendations" => $recommendations,
        ];
    }
}

// Compatibility helpers retained for existing templates and extensions.
if (!function_exists("cropsense_format_number")) {
    function cropsense_format_number($value, $digits = 1)
    {
        $numericValue = cropsense_numeric_value($value);

        return $numericValue === null ? "No recent reading" : number_format($numericValue, $digits);
    }
}

if (!function_exists("cropsense_range_text")) {
    function cropsense_range_text($range, $digits = 1, $unit = "")
    {
        $validRange = cropsense_valid_range($range["min"] ?? null, $range["max"] ?? null);

        if ($validRange === null) {
            return "No approved range configured";
        }

        return trim(number_format($validRange["min"], $digits) . "-" . number_format($validRange["max"], $digits) . " " . $unit);
    }
}

if (!function_exists("cropsense_status_badge")) {
    function cropsense_status_badge($result)
    {
        $status = htmlspecialchars($result["status"] ?? "validation", ENT_QUOTES, "UTF-8");
        $label = htmlspecialchars($result["label"] ?? "Validation required", ENT_QUOTES, "UTF-8");

        return '<span class="fit-badge ' . $status . '">' . $label . '</span>';
    }
}

if (!function_exists("cropsense_requirement_with_badge")) {
    function cropsense_requirement_with_badge($requirement, $result = null)
    {
        $html = htmlspecialchars($requirement, ENT_QUOTES, "UTF-8");

        return $result ? $html . " " . cropsense_status_badge($result) : $html;
    }
}

