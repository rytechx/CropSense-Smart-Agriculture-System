-- Non-destructive normalized crop suitability threshold structure.
-- Existing crop_thresholds rows are preserved and copied as ACCEPTABLE ranges.
-- No optimum, marginal, or EC values are inferred.

CREATE TABLE IF NOT EXISTS `crop_parameter_thresholds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crop_name` varchar(100) NOT NULL,
  `parameter_code` varchar(32) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT '',
  `optimum_min` decimal(10,3) DEFAULT NULL,
  `optimum_max` decimal(10,3) DEFAULT NULL,
  `acceptable_min` decimal(10,3) DEFAULT NULL,
  `acceptable_max` decimal(10,3) DEFAULT NULL,
  `marginal_min` decimal(10,3) DEFAULT NULL,
  `marginal_max` decimal(10,3) DEFAULT NULL,
  `source_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crop_parameter` (`crop_name`, `parameter_code`),
  KEY `idx_parameter_code` (`parameter_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Preserve any already-configured legacy numeric ranges without overwriting
-- normalized rows that may have been entered by an agronomist.
INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
SELECT `crop_name`, 'ph', '', `ph_min`, `ph_max`, 'Copied from existing crop_thresholds pH range'
FROM `crop_thresholds`
WHERE `ph_min` IS NOT NULL AND `ph_max` IS NOT NULL;

INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
SELECT `crop_name`, 'moisture', '%', `moisture_min`, `moisture_max`, 'Copied from existing crop_thresholds moisture range'
FROM `crop_thresholds`
WHERE `moisture_min` IS NOT NULL AND `moisture_max` IS NOT NULL;

INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
SELECT `crop_name`, 'soil_temperature', '°C', `temperature_min`, `temperature_max`, 'Copied from existing crop_thresholds temperature range'
FROM `crop_thresholds`
WHERE `temperature_min` IS NOT NULL AND `temperature_max` IS NOT NULL;

INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
SELECT `crop_name`, 'nitrogen', 'mg/kg', `nitrogen_min`, `nitrogen_max`, 'Copied from existing crop_thresholds nitrogen range'
FROM `crop_thresholds`
WHERE `nitrogen_min` IS NOT NULL AND `nitrogen_max` IS NOT NULL;

INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
SELECT `crop_name`, 'phosphorus', 'mg/kg', `phosphorus_min`, `phosphorus_max`, 'Copied from existing crop_thresholds phosphorus range'
FROM `crop_thresholds`
WHERE `phosphorus_min` IS NOT NULL AND `phosphorus_max` IS NOT NULL;

INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
SELECT `crop_name`, 'potassium', 'mg/kg', `potassium_min`, `potassium_max`, 'Copied from existing crop_thresholds potassium range'
FROM `crop_thresholds`
WHERE `potassium_min` IS NOT NULL AND `potassium_max` IS NOT NULL;

-- Preserve the numeric pH and probe-temperature ranges that already existed
-- in config/crop_thresholds.php. They are intentionally stored only as the
-- ACCEPTABLE tier because CropSense had not defined optimum or marginal tiers.
INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `acceptable_min`, `acceptable_max`, `source_note`)
VALUES
  ('Rice', 'ph', '', 5.500, 6.500, 'Existing CropSense config range'),
  ('Rice', 'soil_temperature', '°C', 26.000, 38.000, 'Existing CropSense config range'),
  ('Corn', 'ph', '', 5.800, 7.000, 'Existing CropSense config range'),
  ('Corn', 'soil_temperature', '°C', 24.000, 35.000, 'Existing CropSense config range'),
  ('Tobacco', 'ph', '', 6.000, 7.000, 'Existing CropSense config range'),
  ('Tobacco', 'soil_temperature', '°C', 25.000, 32.000, 'Existing CropSense config range');

-- Create explicit placeholders for every measured parameter. NULL ranges mean
-- THRESHOLD NOT CONFIGURED and are excluded from scoring.
INSERT IGNORE INTO `crop_parameter_thresholds`
  (`crop_name`, `parameter_code`, `unit`, `source_note`)
VALUES
  ('Rice', 'moisture', '%', 'Awaiting validated numeric ranges'),
  ('Rice', 'soil_temperature', '°C', 'Awaiting validated tier expansion'),
  ('Rice', 'ec', 'µS/cm', 'Awaiting validated numeric ranges'),
  ('Rice', 'ph', '', 'Awaiting validated tier expansion'),
  ('Rice', 'nitrogen', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Rice', 'phosphorus', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Rice', 'potassium', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Corn', 'moisture', '%', 'Awaiting validated numeric ranges'),
  ('Corn', 'soil_temperature', '°C', 'Awaiting validated tier expansion'),
  ('Corn', 'ec', 'µS/cm', 'Awaiting validated numeric ranges'),
  ('Corn', 'ph', '', 'Awaiting validated tier expansion'),
  ('Corn', 'nitrogen', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Corn', 'phosphorus', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Corn', 'potassium', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Tobacco', 'moisture', '%', 'Awaiting validated numeric ranges'),
  ('Tobacco', 'soil_temperature', '°C', 'Awaiting validated tier expansion'),
  ('Tobacco', 'ec', 'µS/cm', 'Awaiting validated numeric ranges'),
  ('Tobacco', 'ph', '', 'Awaiting validated tier expansion'),
  ('Tobacco', 'nitrogen', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Tobacco', 'phosphorus', 'mg/kg', 'Awaiting validated sensor-compatible ranges'),
  ('Tobacco', 'potassium', 'mg/kg', 'Awaiting validated sensor-compatible ranges');

