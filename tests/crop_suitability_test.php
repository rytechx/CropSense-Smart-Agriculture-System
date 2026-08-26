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

function fixture_threshold($optimum = null, $acceptable = null, $marginal = null)
{
    return [
        "unit" => "fixture-unit",
        "source" => "test_fixture",
        "ranges" => [
            "optimum" => $optimum,
            "acceptable" => $acceptable,
            "marginal" => $marginal,
        ],
    ];
}

$completeThreshold = fixture_threshold(
    ["min" => 40.0, "max" => 60.0],
    ["min" => 30.0, "max" => 70.0],
    ["min" => 20.0, "max" => 80.0]
);

assert_same("OPTIMUM produces score 3", cropsense_classify_parameter(50, $completeThreshold)["score"], 3);
assert_same("ACCEPTABLE produces score 2", cropsense_classify_parameter(65, $completeThreshold)["score"], 2);
assert_same("MARGINAL produces score 1", cropsense_classify_parameter(75, $completeThreshold)["score"], 1);
assert_same("UNSUITABLE produces score 0", cropsense_classify_parameter(90, $completeThreshold)["score"], 0);

$missingResult = cropsense_classify_parameter(null, $completeThreshold);
assert_same("Missing sensor value is NO DATA", $missingResult["assessment"], "NO_DATA");
assert_same("Missing sensor value is excluded", $missingResult["score"], null);
assert_same("Empty sensor value is NO DATA", cropsense_classify_parameter("", $completeThreshold)["assessment"], "NO_DATA");
assert_same("NaN sensor value is NO DATA", cropsense_classify_parameter(NAN, $completeThreshold)["assessment"], "NO_DATA");

$unconfiguredResult = cropsense_classify_parameter(50, fixture_threshold());
assert_same("Missing threshold is NOT ASSESSED", $unconfiguredResult["assessment"], "NOT_ASSESSED");
assert_same("Missing threshold is excluded", $unconfiguredResult["score"], null);

$partialThreshold = fixture_threshold(null, ["min" => 30.0, "max" => 70.0], null);
assert_same(
    "Incomplete tiers do not invent UNSUITABLE",
    cropsense_classify_parameter(90, $partialThreshold)["assessment"],
    "NOT_ASSESSED"
);

$zeroThreshold = fixture_threshold(["min" => -1.0, "max" => 1.0], ["min" => -2.0, "max" => 2.0], ["min" => -3.0, "max" => 3.0]);
$zeroResult = cropsense_classify_parameter("0.00", $zeroThreshold);
assert_same("Real API zero is preserved", cropsense_numeric_value("0.00"), 0.0);
assert_same("Real API zero is evaluated normally", $zeroResult["score"], 3);

$definitions = cropsense_parameter_definitions();
$parameters = [];
foreach ($definitions as $parameterCode => $definition) {
    $parameters[$parameterCode] = fixture_threshold();
    $parameters[$parameterCode]["unit"] = $definition["unit"];
}
$parameters["moisture"] = $completeThreshold;
$parameters["soil_temperature"] = $completeThreshold;
$parameters["ec"] = $completeThreshold;

$cropFixture = [
    "crop_key" => "fixture_crop",
    "crop" => "Fixture Crop",
    "scientific_name" => "Testus fixture",
    "parameters" => $parameters,
];
$readingFixture = [
    "soil_moisture" => 50,
    "temperature" => 65,
    "soil_ec" => 75,
    "soil_ph" => 6.2,
    "nitrogen" => 0,
    "phosphorus" => 24,
    "potassium" => 120,
];
$cropResult = cropsense_calculate_crop_score($readingFixture, $cropFixture);

assert_same("EC is read from soil_ec", $cropResult["parameters"][2]["value"], 75.0);
assert_same("Only configured parameters are assessed", $cropResult["assessed_parameters"], 3);
assert_same("Dynamic total score", $cropResult["score"], 6);
assert_same("Dynamic maximum score", $cropResult["maximum_score"], 9);
assert_same("Dynamic denominator percentage", $cropResult["percentage"], 66.7);
assert_same("Unconfigured nitrogen zero remains zero", $cropResult["parameters"][4]["value"], 0.0);
assert_same("Unconfigured nitrogen zero is excluded", $cropResult["parameters"][4]["score"], null);
assert_same("Lowest-scoring factors are identified", count($cropResult["limiting_factors"]), 1);
assert_same("Marginal EC is the limiting factor", $cropResult["limiting_factors"][0]["parameter"], "ec");

$multipleFactors = cropsense_identify_limiting_factors([
    ["parameter" => "moisture", "label" => "Soil Moisture", "value" => 20.0, "unit" => "%", "assessment" => "MARGINAL", "assessment_label" => "Marginal", "score" => 1],
    ["parameter" => "nitrogen", "label" => "Nitrogen", "value" => 10.0, "unit" => "mg/kg", "assessment" => "MARGINAL", "assessment_label" => "Marginal", "score" => 1],
    ["parameter" => "ph", "label" => "Soil pH", "value" => 6.0, "unit" => "", "assessment" => "ACCEPTABLE", "assessment_label" => "Acceptable", "score" => 2],
]);
assert_same("All tied limiting factors are returned", count($multipleFactors["factors"]), 2);

assert_same(
    "90 percent with lowest score 2 is capped at S2",
    cropsense_apply_limiting_factor_rule(cropsense_score_class(90), 2)["code"],
    "S2"
);
assert_same(
    "90 percent with lowest score 1 is capped at S3",
    cropsense_apply_limiting_factor_rule(cropsense_score_class(90), 1)["code"],
    "S3"
);
assert_same(
    "90 percent with lowest score 0 becomes N",
    cropsense_apply_limiting_factor_rule(cropsense_score_class(90), 0)["code"],
    "N"
);

$ranked = cropsense_rank_crop_recommendations([
    ["crop" => "S2 Lower", "final_class" => "S2", "percentage" => 72.0],
    ["crop" => "N Crop", "final_class" => "N", "percentage" => 99.0],
    ["crop" => "S3 Crop", "final_class" => "S3", "percentage" => 99.0],
    ["crop" => "S1 Crop", "final_class" => "S1", "percentage" => 85.0],
    ["crop" => "S2 Higher", "final_class" => "S2", "percentage" => 80.0],
]);

assert_same("S1 outranks S2", $ranked[0]["crop"], "S1 Crop");
assert_same("Higher percentage wins within S2", $ranked[1]["crop"], "S2 Higher");
assert_same("Lower percentage follows within S2", $ranked[2]["crop"], "S2 Lower");
assert_same("S3 outranks N", $ranked[3]["crop"], "S3 Crop");
assert_same("N ranks last", $ranked[4]["crop"], "N Crop");

assert_same("85 percent maps to S1", cropsense_score_class(85)["code"], "S1");
assert_same("84.99 percent maps to S2", cropsense_score_class(84.99)["code"], "S2");
assert_same("70 percent maps to S2", cropsense_score_class(70)["code"], "S2");
assert_same("69.99 percent maps to S3", cropsense_score_class(69.99)["code"], "S3");
assert_same("50 percent maps to S3", cropsense_score_class(50)["code"], "S3");
assert_same("Below 50 percent maps to N", cropsense_score_class(49.99)["code"], "N");

$missingFieldReading = $readingFixture;
unset($missingFieldReading["soil_ec"]);
$missingFieldResult = cropsense_calculate_crop_score($missingFieldReading, $cropFixture);
assert_same("Missing API field becomes NO DATA", $missingFieldResult["parameters"][2]["assessment"], "NO_DATA");
assert_same("Missing API field is excluded", $missingFieldResult["assessed_parameters"], 2);

if ($failures > 0) {
    echo "{$failures} of {$assertions} assertions failed." . PHP_EOL;
    exit(1);
}

echo "{$assertions} assertions passed." . PHP_EOL;
