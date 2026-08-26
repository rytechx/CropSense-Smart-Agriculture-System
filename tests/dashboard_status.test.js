"use strict";

require("../assets/js/dashboard.js");

const {
    numberValue,
    getMoistureLevel,
    getPhLevel,
    getNitrogenLevel,
    getPhosphorusLevel,
    getPotassiumLevel,
    getStatusClass,
    getTelemetryStatus
} = globalThis.CropSenseTelemetryStatus;

const boundaryCases = [
    ["Moisture 24.9", getMoistureLevel, 24.9, "LOW"],
    ["Moisture 25", getMoistureLevel, 25, "MEDIUM"],
    ["Moisture 40", getMoistureLevel, 40, "MEDIUM"],
    ["Moisture 40.1", getMoistureLevel, 40.1, "HIGH"],
    ["pH 5.4", getPhLevel, 5.4, "LOW"],
    ["pH 5.5", getPhLevel, 5.5, "MEDIUM"],
    ["pH 7.0", getPhLevel, 7.0, "MEDIUM"],
    ["pH 7.1", getPhLevel, 7.1, "HIGH"],
    ["Nitrogen 19", getNitrogenLevel, 19, "LOW"],
    ["Nitrogen numeric zero", getNitrogenLevel, 0, "LOW"],
    ["Nitrogen API string zero", getNitrogenLevel, "0.00", "LOW"],
    ["Nitrogen 20", getNitrogenLevel, 20, "MEDIUM"],
    ["Nitrogen 40", getNitrogenLevel, 40, "MEDIUM"],
    ["Nitrogen 41", getNitrogenLevel, 41, "HIGH"],
    ["Phosphorus 14", getPhosphorusLevel, 14, "LOW"],
    ["Phosphorus 15", getPhosphorusLevel, 15, "MEDIUM"],
    ["Phosphorus 30", getPhosphorusLevel, 30, "MEDIUM"],
    ["Phosphorus 31", getPhosphorusLevel, 31, "HIGH"],
    ["Potassium 79", getPotassiumLevel, 79, "LOW"],
    ["Potassium 80", getPotassiumLevel, 80, "MEDIUM"],
    ["Potassium 150", getPotassiumLevel, 150, "MEDIUM"],
    ["Potassium 151", getPotassiumLevel, 151, "HIGH"]
];

const classifiers = [
    ["Moisture", getMoistureLevel],
    ["pH", getPhLevel],
    ["Nitrogen", getNitrogenLevel],
    ["Phosphorus", getPhosphorusLevel],
    ["Potassium", getPotassiumLevel]
];

const missingCases = [
    ["null", null],
    ["undefined", undefined],
    ["empty string", ""],
    ["whitespace string", "   "],
    ["NaN", Number.NaN]
];

const classCases = [
    ["LOW class", getStatusClass("LOW"), "status-low"],
    ["MEDIUM class", getStatusClass("MEDIUM"), "status-medium"],
    ["HIGH class", getStatusClass("HIGH"), "status-high"],
    ["NO DATA class", getStatusClass("NO DATA"), "status-unknown"]
];

let failures = 0;
let assertions = 0;

function assertEqual(label, actual, expected) {
    assertions += 1;

    if (actual !== expected) {
        failures += 1;
        console.error(`FAIL ${label}: expected ${expected}, received ${actual}`);
        return;
    }

    console.log(`PASS ${label} = ${expected}`);
}

boundaryCases.forEach(([label, classifier, value, expected]) => {
    assertEqual(label, getTelemetryStatus(value, classifier), expected);
});

classifiers.forEach(([parameter, classifier]) => {
    missingCases.forEach(([label, value]) => {
        assertEqual(`${parameter} ${label}`, getTelemetryStatus(value, classifier), "NO DATA");
    });
});

classCases.forEach(([label, actual, expected]) => {
    assertEqual(label, actual, expected);
});

assertEqual("Numeric zero is preserved", numberValue(0), 0);
assertEqual("API string zero is preserved", numberValue("0.00"), 0);

if (failures > 0) {
    console.error(`${failures} of ${assertions} assertions failed.`);
    process.exit(1);
}

console.log(`${assertions} assertions passed.`);
