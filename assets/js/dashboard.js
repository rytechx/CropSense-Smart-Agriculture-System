(function (global) {
    function numberValue(value) {
        if (
            value === null ||
            value === undefined ||
            (typeof value === "string" && value.trim() === "")
        ) {
            return null;
        }

        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function getMoistureLevel(value) {
        if (value < 25) return "LOW";
        if (value <= 40) return "MEDIUM";
        return "HIGH";
    }

    function getPhLevel(value) {
        if (value < 5.5) return "LOW";
        if (value <= 7.0) return "MEDIUM";
        return "HIGH";
    }

    function getNitrogenLevel(value) {
        if (value < 20) return "LOW";
        if (value <= 40) return "MEDIUM";
        return "HIGH";
    }

    function getPhosphorusLevel(value) {
        if (value < 15) return "LOW";
        if (value <= 30) return "MEDIUM";
        return "HIGH";
    }

    function getPotassiumLevel(value) {
        if (value < 80) return "LOW";
        if (value <= 150) return "MEDIUM";
        return "HIGH";
    }

    function getStatusClass(status) {
        if (status === "LOW") return "status-low";
        if (status === "MEDIUM") return "status-medium";
        if (status === "HIGH") return "status-high";
        return "status-unknown";
    }

    function getTelemetryStatus(value, getLevel) {
        const parsed = numberValue(value);
        return parsed === null ? "NO DATA" : getLevel(parsed);
    }

    global.CropSenseTelemetryStatus = Object.freeze({
        numberValue,
        getMoistureLevel,
        getPhLevel,
        getNitrogenLevel,
        getPhosphorusLevel,
        getPotassiumLevel,
        getStatusClass,
        getTelemetryStatus
    });

    const dashboard = global.document
        ? global.document.querySelector("[data-live-dashboard]")
        : null;

    if (!dashboard) {
        return;
    }

    const apiUrl = "api/get_latest_reading.php";
    const pollMs = 5000;

    const setText = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const updateDashboardClock = () => {
        const now = new Date();
        const hour = now.getHours();
        const greeting = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
        const overview = hour < 12
            ? "Morning field overview"
            : hour < 18 ? "Afternoon field overview" : "Evening field overview";

        setText("[data-dashboard-greeting]", greeting);
        setText("[data-greeting-period]", overview);
        setText("[data-dashboard-date]", now.toLocaleDateString("en-US", {
            month: "long",
            day: "numeric",
            year: "numeric"
        }));
        setText("[data-dashboard-time]", now.toLocaleTimeString("en-US", {
            hour: "numeric",
            minute: "2-digit",
            second: "2-digit"
        }));
    };

    const setSystemStatus = (status, label) => {
        const pill = document.querySelector("[data-system-pill]");
        const labels = document.querySelectorAll("[data-system-label]");
        const heroStatus = document.querySelector("[data-hero-status]");
        const heroStatusLabels = document.querySelectorAll("[data-hero-status-label]");
        const commandStatus = document.querySelector("[data-command-status]");
        const commandStatusLabel = document.querySelector("[data-command-status-label]");
        const fieldMood = document.querySelector("[data-field-mood]");
        const fieldPulseDots = document.querySelectorAll(".field-pulse-live-dot");

        labels.forEach((text) => {
            text.textContent = label;
        });

        if (pill) {
            pill.classList.toggle("is-offline", status === "offline");
            pill.classList.toggle("is-stale", status === "stale");
        }

        if (heroStatus) {
            heroStatus.classList.toggle("is-live", status === "live");
            heroStatus.classList.toggle("is-offline", status !== "live");
            heroStatus.classList.toggle("is-stale", status === "stale");
        }

        heroStatusLabels.forEach((text) => {
            text.textContent = status === "live" ? "Sensor Live" : label;
        });

        if (commandStatus) {
            commandStatus.classList.toggle("is-online", status === "live");
            commandStatus.classList.toggle("is-offline", status !== "live");
        }

        if (commandStatusLabel) {
            commandStatusLabel.textContent = status === "live" ? "Online" : (status === "stale" ? "Stale" : "Offline");
        }

        if (fieldMood) {
            fieldMood.textContent = status === "live"
                ? "Ready for today's scan"
                : (status === "stale" ? "Waiting for a fresh reading" : "Waiting for sensor connection");
        }

        fieldPulseDots.forEach((dot) => {
            dot.classList.toggle("is-live", status === "live");
            dot.classList.toggle("is-stale", status === "stale");
        });

        setText("[data-device-online-count]", status === "live" ? "1" : "0");
    };

    const fixed = (value, digits = 1) => {
        const parsed = numberValue(value);
        return parsed === null ? "--" : parsed.toFixed(digits);
    };

    const measurementText = (value) => {
        const parsed = numberValue(value);

        return parsed === null
            ? "--"
            : parsed.toLocaleString("en-US", {
                useGrouping: false,
                maximumFractionDigits: 2
            });
    };

    const celsiusText = (value) => `${fixed(value, 1)} °C`;
    const nutrientText = (value) => measurementText(value);

    const applyStatusBadge = (name, status) => {
        const statusClass = getStatusClass(status);

        document.querySelectorAll(`[data-status-badge="${name}"]`).forEach((badge) => {
            badge.classList.remove("status-low", "status-medium", "status-high", "status-unknown");
            badge.classList.add(statusClass);
            badge.textContent = status;
            badge.setAttribute("aria-label", `${badge.dataset.statusLabel || name} status: ${status}`);
        });
    };

    const updateTelemetryStatuses = (reading = null) => {
        const source = reading || {};
        const telemetry = [
            ["soil_moisture", source.moisture ?? source.soil_moisture, getMoistureLevel],
            ["soil_ph", source.ph ?? source.soil_ph, getPhLevel],
            ["nitrogen", source.nitrogen, getNitrogenLevel],
            ["phosphorus", source.phosphorus, getPhosphorusLevel],
            ["potassium", source.potassium, getPotassiumLevel]
        ];

        telemetry.forEach(([name, value, getLevel]) => {
            applyStatusBadge(name, getTelemetryStatus(value, getLevel));
        });
    };

    const suitabilityStateClasses = ["is-s1", "is-s2", "is-s3", "is-n", "is-na"];
    const parameterStateClasses = [
        "is-optimum",
        "is-acceptable",
        "is-marginal",
        "is-unsuitable",
        "is-no-data",
        "is-not-assessed"
    ];

    const suitabilityStateClass = (code) => {
        const normalized = String(code || "NA").toLowerCase();
        return ["s1", "s2", "s3", "n"].includes(normalized) ? `is-${normalized}` : "is-na";
    };

    const parameterStateClass = (assessment) => {
        const normalized = String(assessment || "NOT_ASSESSED").toLowerCase().replace(/_/g, "-");
        return `is-${normalized}`;
    };

    const setElementText = (root, selector, value) => {
        const element = root ? root.querySelector(selector) : null;

        if (element) {
            element.textContent = value;
        }
    };

    const applySuitabilityState = (element, code, label) => {
        if (!element) {
            return;
        }

        element.classList.remove(...suitabilityStateClasses);
        element.classList.add(suitabilityStateClass(code));
        element.textContent = label || "Not Assessed";
    };

    const measuredParameterText = (parameter) => {
        const value = numberValue(parameter ? parameter.value : null);

        if (value === null) {
            return "--";
        }

        const unit = String(parameter.unit || "").trim();
        return `${measurementText(value)}${unit ? ` ${unit}` : ""}`;
    };

    const renderLimitingFactors = (card, crop) => {
        const list = card.querySelector("[data-limiting-factors]");
        const factors = Array.isArray(crop.limiting_factors) ? crop.limiting_factors : [];
        setElementText(card, "[data-limiting-summary]", crop.limiting_factor_reason || "No assessed limiting factors yet.");

        if (!list) {
            return;
        }

        list.replaceChildren();
        factors.forEach((factor) => {
            const item = document.createElement("div");
            const heading = document.createElement("strong");
            const reading = document.createElement("span");
            const assessment = document.createElement("span");
            const guidance = document.createElement("small");

            heading.textContent = factor.label || factor.parameter || "Parameter";
            reading.textContent = measuredParameterText(factor);
            assessment.textContent = `${factor.assessment_label || factor.assessment || "Assessed"} · ${factor.score}/3`;
            assessment.className = `parameter-assessment-badge ${parameterStateClass(factor.assessment)}`;
            guidance.textContent = factor.guidance || "Review measured soil conditions.";
            item.append(heading, reading, assessment, guidance);
            list.appendChild(item);
        });
    };

    const resetCropAssessmentCard = (card) => {
        card.classList.remove(...suitabilityStateClasses);
        card.classList.add("is-na");
        setElementText(card, "[data-crop-rank]", "--");
        setElementText(card, "[data-crop-percentage]", "--");
        setElementText(card, "[data-crop-assessed]", "0 of 7 parameters");
        setElementText(card, "[data-crop-score]", "--");
        setElementText(card, "[data-crop-raw-class]", "Not Assessed");
        setElementText(card, "[data-crop-final-class]", "Not Assessed");
        applySuitabilityState(card.querySelector("[data-crop-final-badge]"), "NA", "Not Assessed");
        setElementText(card, "[data-limiting-summary]", "No assessed limiting factors yet.");

        const limitingList = card.querySelector("[data-limiting-factors]");
        if (limitingList) {
            limitingList.replaceChildren();
        }

        card.querySelectorAll("[data-crop-parameter]").forEach((row) => {
            setElementText(row, "[data-parameter-measured]", "--");
            setElementText(row, "[data-parameter-score]", "--");
            const badge = row.querySelector("[data-parameter-assessment]");

            if (badge) {
                badge.classList.remove(...parameterStateClasses);
                badge.classList.add("is-no-data");
                badge.textContent = "NO DATA";
            }
        });
    };

    const renderCropSuitability = (assessment = null) => {
        const recommendations = Array.isArray(assessment && assessment.recommendations)
            ? assessment.recommendations
            : [];
        const list = document.querySelector("[data-crop-assessment-list]");
        const cards = Array.from(document.querySelectorAll("[data-crop-assessment]"));
        const cardsByCrop = new Map(cards.map((card) => [card.dataset.cropAssessment, card]));
        const top = assessment && assessment.top_recommendation ? assessment.top_recommendation : null;

        cards.forEach(resetCropAssessmentCard);
        applySuitabilityState(
            document.querySelector("[data-recommendation-class]"),
            top ? top.final_class : "NA",
            top ? top.final_full_label : "Not Assessed"
        );
        setText("[data-recommendation-score]", top && top.percentage !== null ? `${measurementText(top.percentage)}%` : "--");
        setText(
            "[data-recommendation-title]",
            top ? `${top.crop} — ${top.final_full_label}` : "Waiting for an assessable reading"
        );
        setText(
            "[data-recommendation-description]",
            assessment && assessment.error
                ? assessment.error
                : top
                    ? "Top CropSense recommendation based on measured soil parameters. Open each crop to review its scored and unassessed parameters."
                    : "Crop-specific results appear when real sensor data and validated thresholds are both available."
        );

        recommendations.forEach((crop, index) => {
            const card = cardsByCrop.get(crop.crop_key);

            if (!card) {
                return;
            }

            card.classList.remove(...suitabilityStateClasses);
            card.classList.add(suitabilityStateClass(crop.final_class));
            card.open = index === 0;
            setElementText(card, "[data-crop-rank]", `#${crop.rank || index + 1}`);
            setElementText(card, "[data-crop-percentage]", crop.percentage === null ? "--" : `${measurementText(crop.percentage)}%`);
            setElementText(card, "[data-crop-assessed]", `${crop.assessed_parameters} of ${crop.total_parameters} parameters`);
            setElementText(card, "[data-crop-score]", crop.maximum_score > 0 ? `${crop.score} / ${crop.maximum_score}` : "--");
            setElementText(card, "[data-crop-raw-class]", crop.raw_full_label || "Not Assessed");
            setElementText(card, "[data-crop-final-class]", crop.final_full_label || "Not Assessed");
            applySuitabilityState(
                card.querySelector("[data-crop-final-badge]"),
                crop.final_class,
                crop.final_full_label
            );

            (Array.isArray(crop.parameters) ? crop.parameters : []).forEach((parameter) => {
                const row = card.querySelector(`[data-crop-parameter="${parameter.parameter}"]`);

                if (!row) {
                    return;
                }

                setElementText(row, "[data-parameter-measured]", measuredParameterText(parameter));
                setElementText(row, "[data-parameter-score]", parameter.score === null ? "--" : `${parameter.score}/3`);
                const badge = row.querySelector("[data-parameter-assessment]");

                if (badge) {
                    badge.classList.remove(...parameterStateClasses);
                    badge.classList.add(parameterStateClass(parameter.assessment));
                    badge.textContent = parameter.assessment_label || "NOT ASSESSED";
                }
            });

            renderLimitingFactors(card, crop);

            if (list) {
                list.appendChild(card);
            }
        });
    };

    const relativeTime = (ageSeconds) => {
        const age = numberValue(ageSeconds);

        if (age === null) {
            return "just now";
        }

        if (age < 60) {
            return "just now";
        }

        if (age < 3600) {
            return `${Math.floor(age / 60)} min ago`;
        }

        return `${Math.floor(age / 3600)} hr ago`;
    };

    const setSensorState = (name, label, tone = "good") => {
        document.querySelectorAll(`[data-sensor-state="${name}"]`).forEach((element) => {
            element.textContent = label;
            element.classList.toggle("good", tone === "good");
            element.classList.toggle("warn", tone === "warn");
            element.classList.toggle("bad", tone === "bad");
        });
    };

    const updateSensorStates = (reading) => {
        const temperature = numberValue(reading.temperature);
        const moisture = numberValue(reading.soil_moisture);
        const ph = numberValue(reading.soil_ph);
        const ec = numberValue(reading.soil_ec);
        const nitrogen = numberValue(reading.nitrogen);
        const phosphorus = numberValue(reading.phosphorus);
        const potassium = numberValue(reading.potassium);

        setSensorState("temperature", temperature !== null && temperature >= 22 && temperature <= 38 ? "Normal" : "Check", temperature !== null && temperature >= 22 && temperature <= 38 ? "good" : "warn");
        setSensorState("moisture", moisture !== null ? "Validation Required" : "No Reading", "warn");
        setSensorState("ph", ph > 0 && ph >= 5.5 && ph <= 7 ? "Suitable" : "Check pH", ph > 0 && ph >= 5.5 && ph <= 7 ? "good" : "warn");
        setSensorState("ec", ec !== null && ec > 0 ? "Range Needed" : "No Reading", "warn");
        setSensorState("nitrogen", nitrogen !== null ? "Validation Required" : "No Reading", "warn");
        setSensorState("phosphorus", phosphorus !== null ? "Validation Required" : "No Reading", "warn");
        setSensorState("potassium", potassium !== null ? "Validation Required" : "No Reading", "warn");
    };

    const showEmptyState = () => {
        setText('[data-reading="soil_moisture"]', "--");
        setText('[data-reading="soil_ph"]', "--");
        setText('[data-reading="soil_ec"]', "--");
        setText('[data-reading="npk"]', "-- / -- / --");
        setText('[data-reading="nitrogen"]', "--");
        setText('[data-reading="phosphorus"]', "--");
        setText('[data-reading="potassium"]', "--");
        setText('[data-reading="temperature"]', "-- °C");

        setText('[data-reading-status="soil_moisture"]', "Awaiting sensor reading");
        setText('[data-reading-status="soil_ph"]', "pH sensor not connected");
        setText('[data-reading-status="soil_ec"]', "Electrical conductivity");
        setText('[data-reading-status="npk"]', "Awaiting NPK sensor reading");
        setText('[data-reading-status="temperature"]', "Soil probe reading");

        renderCropSuitability();
        setText("[data-activity-title]", "Sensor readings pending");
        setText("[data-activity-detail]", "Waiting for device input");
        setSystemStatus("offline", "Sensor Offline");

        updateTelemetryStatuses();

        ["temperature", "moisture", "ph", "ec", "nitrogen", "phosphorus", "potassium"].forEach((name) => {
            setSensorState(name, "No Reading", "warn");
        });
    };

    const updateDashboard = (reading) => {
        if (!reading) {
            showEmptyState();
            return;
        }

        const nitrogen = numberValue(reading.nitrogen);
        const phosphorus = numberValue(reading.phosphorus);
        const potassium = numberValue(reading.potassium);
        const moistureReading = reading.moisture ?? reading.soil_moisture;
        const phReading = reading.ph ?? reading.soil_ph;
        const ecReading = reading.ec ?? reading.soil_ec;
        const soilPh = numberValue(phReading);
        const soilEc = numberValue(ecReading);
        const npk = `${nutrientText(nitrogen)} / ${nutrientText(phosphorus)} / ${nutrientText(potassium)}`;
        const updatedAt = relativeTime(reading.age_seconds);
        const connectionStatus = reading.connection_status || "Offline";
        const isLive = reading.is_live === true || reading.is_live === "1" || connectionStatus === "Live" || connectionStatus === "Online";
        const isStale = connectionStatus === "Stale";
        const freshnessLabel = isLive ? `Updated ${updatedAt}` : `${isStale ? "Stale reading" : "Last reading"} ${updatedAt}`;

        setText('[data-reading="soil_moisture"]', measurementText(moistureReading));
        setText('[data-reading="soil_ph"]', measurementText(soilPh));
        setText('[data-reading="soil_ec"]', soilEc === null ? "--" : fixed(soilEc, 0));
        setText('[data-reading="npk"]', npk);
        setText('[data-reading="nitrogen"]', nutrientText(nitrogen));
        setText('[data-reading="phosphorus"]', nutrientText(phosphorus));
        setText('[data-reading="potassium"]', nutrientText(potassium));
        setText('[data-reading="temperature"]', celsiusText(reading.temperature));

        setText('[data-reading-status="soil_moisture"]', freshnessLabel);
        setText('[data-reading-status="soil_ph"]', soilPh === null ? "No pH reading" : freshnessLabel);
        setText('[data-reading-status="soil_ec"]', soilEc === null ? "No EC reading" : `uS/cm ${freshnessLabel.toLowerCase()}`);
        setText('[data-reading-status="npk"]', `N, P, K mg/kg ${isLive ? "updated" : "last updated"} ${updatedAt}`);
        setText('[data-reading-status="temperature"]', freshnessLabel);
        updateTelemetryStatuses(reading);
        updateSensorStates({
            ...reading,
            soil_moisture: moistureReading,
            soil_ph: phReading,
            soil_ec: ecReading
        });

        renderCropSuitability(reading.crop_suitability || null);
        setText("[data-activity-title]", isLive ? "Live sensor reading received" : (isStale ? "Stale sensor reading" : "Sensor offline"));
        setText("[data-activity-detail]", `${reading.device_name || "CropSense device"} ${isLive ? "updated" : "last updated"} ${updatedAt}`);
        setSystemStatus(isLive ? "live" : (isStale ? "stale" : "offline"), isLive ? "Sensor Live" : (isStale ? "Sensor Stale" : "Sensor Offline"));
    };

    const loadLatestReading = async () => {
        try {
            const response = await fetch(apiUrl, { cache: "no-store" });

            if (!response.ok) {
                throw new Error("Reading request failed");
            }

            const payload = await response.json();

            if (!payload.ok) {
                throw new Error(payload.error || "Invalid sensor response");
            }

            updateDashboard(payload.data || null);
        } catch (error) {
            setText("[data-activity-title]", "Unable to refresh readings");
            setText("[data-activity-detail]", "Check the local server and database connection");
            setSystemStatus("offline", "Sensor Status Unavailable");
        }
    };

    const initialSuitabilityElement = document.querySelector("[data-initial-crop-suitability]");
    let initialSuitability = null;

    if (initialSuitabilityElement) {
        try {
            initialSuitability = JSON.parse(initialSuitabilityElement.textContent);
        } catch (error) {
            initialSuitability = null;
        }
    }

    renderCropSuitability(initialSuitability);
    updateTelemetryStatuses({
        moisture: dashboard.dataset.initialMoisture,
        ph: dashboard.dataset.initialPh,
        nitrogen: dashboard.dataset.initialNitrogen,
        phosphorus: dashboard.dataset.initialPhosphorus,
        potassium: dashboard.dataset.initialPotassium
    });
    setText("[data-activity-title]", "Loading latest sensor reading");
    setText("[data-activity-detail]", "Connecting to the CropSense sensor API");
    loadLatestReading();
    window.setInterval(loadLatestReading, pollMs);
    updateDashboardClock();
    window.setInterval(updateDashboardClock, 1000);
})(typeof window !== "undefined" ? window : globalThis);
