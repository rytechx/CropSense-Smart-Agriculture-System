<?php

require_once __DIR__ . "/../includes/crop_suitability.php";

$assertions = 0;
$failures = 0;

function assert_same($label, $actual, $expected)
{
    global $assertions, $failures;
    $assertions += 1;

    if ($actual !== $expected) {
        $failures += 1;
        echo "FAIL {$label}: expected " . var_export($expected, true) . ", received " . var_export($actual, true) . PHP_EOL;
        return;
    }

    echo "PASS {$label}" . PHP_EOL;
}

$thresholds = cropsense_get_crop_thresholds();
$sample = ["nitrogen" => 24, "phosphorus" => 22, "potassium" => 135, "moisture" => 72,
    "ph" => 6.3, "soil_temperature" => 28, "ec" => 950];
$result = cropsense_assess_crop_suitability(null, $sample);
foreach ([["Corn", 14, 100.0, "Highly Suitable"], ["Tobacco", 12, 85.7, "Highly Suitable"], ["Rice", 11, 78.6, "Suitable"]] as $i => $expected) {
    $crop = $result["recommendations"][$i];
    assert_same("Sample rank " . ($i + 1), [$crop["crop"], $crop["score"], $crop["percentage"], $crop["final_label"]], $expected);
    assert_same("Maximum is fourteen", $crop["maximum_score"], 14);
    echo "#" . $crop["rank"] . " " . $crop["crop"] . " " . $crop["percentage"] . "% " . $crop["final_label"] . " | " . $crop["match_explanation"] . " | " . $crop["limiting_factor_reason"] . PHP_EOL;
}
assert_same("Strongest tobacco limitation", $result["recommendations"][1]["limiting_factors"][0]["parameter"], "potassium");
assert_same("Rice moisture limitation", $result["recommendations"][2]["limiting_factors"][0]["parameter"], "moisture");
foreach ($thresholds as $crop) {
    foreach ($crop["parameters"] as $code => $threshold) {
        $range = $threshold["ranges"]["acceptable"];
        $near = $threshold["ranges"]["marginal"];
        foreach ([[$range["min"], 2], [$range["max"], $code === "ec" ? 1 : 2],
            [$near["max"], 1], [$near["max"] + 0.001, 0],
            [$near["min"], $code === "ec" ? 2 : 1], [$near["min"] - 0.001, 0]] as [$value, $score]) {
            assert_same($crop["crop"] . " " . $code . " boundary " . $value, cropsense_classify_parameter($value, $threshold)["score"], $score);
        }
        foreach ([null, "", "  ", NAN, INF, "invalid"] as $value) {
            assert_same("Invalid values excluded", cropsense_classify_parameter($value, $threshold)["score"], null);
        }
    }
}
foreach ([0, "0.00"] as $zero) {
    $reading = array_fill_keys(array_keys($sample), $zero);
    $crop = cropsense_calculate_crop_score($reading, $thresholds["corn"]);
    assert_same("All zeros remain assessed", $crop["assessed_parameters"], 7);
    assert_same("Zero EC matches upper-only range", $crop["score"], 2);
    foreach ($crop["parameters"] as $p) assert_same("Real zero preserved", $p["value"], 0.0);
}
$partial = $sample;
unset($partial["potassium"]);
$crop = cropsense_calculate_crop_score($partial, $thresholds["corn"]);
assert_same("Missing value excluded from denominator", $crop["maximum_score"], 12);
assert_same("Partial score", $crop["percentage"], 100.0);
assert_same("Missing label", $crop["missing_parameters"], ["Potassium"]);
assert_same("Missing status", $crop["parameters"][6]["assessment"], "NO_DATA");
$empty = cropsense_assess_crop_suitability(null, null);
assert_same("No data has no recommendation", $empty["top_recommendation"], null);
assert_same("No data percentage", $empty["recommendations"][0]["percentage"], null);
assert_same("No data denominator", $empty["recommendations"][0]["maximum_score"], 0);
$humid = $sample;
$humid["humidity"] = 999;
assert_same("Humidity ignored", cropsense_assess_crop_suitability(null, $humid), $result);
$aliases = ["soil_moisture" => 72, "temperature" => 28, "soil_ec" => 950, "soil_ph" => 6.3, "nitrogen" => 24, "phosphorus" => 22, "potassium" => 135];
assert_same("Existing database and API aliases preserved", cropsense_assess_crop_suitability(null, $aliases), $result);
foreach ([[85, "S1"], [84.99, "S2"], [70, "S2"], [69.99, "S3"], [50, "S3"], [49.99, "N"]] as [$value, $code]) {
    assert_same("Class boundary " . $value, cropsense_score_class($value)["code"], $code);
}
$limited = $sample;
$limited["potassium"] = 0;
$crop = cropsense_calculate_crop_score($limited, $thresholds["corn"]);
assert_same("Limitation does not cap percentage class", $crop["final_class"], "S1");
$ranked = cropsense_rank_crop_recommendations([
    ["crop" => "Low", "percentage" => 70, "final_class" => "S1"],
    ["crop" => "High", "percentage" => 90, "final_class" => "N"]]);
assert_same("Percentage alone determines order", $ranked[0]["crop"], "High");
$config = cropsense_crop_config();
assert_same("Generic NPK config unchanged", $config["npk_classification"], [
    "nitrogen" => ["low_max" => 19.99, "medium_max" => 40.00],
    "phosphorus" => ["low_max" => 14.99, "medium_max" => 30.00],
    "potassium" => ["low_max" => 79.99, "medium_max" => 150.00],
]);
echo "{$assertions} assertions; {$failures} failures." . PHP_EOL;
exit($failures > 0 ? 1 : 0);
