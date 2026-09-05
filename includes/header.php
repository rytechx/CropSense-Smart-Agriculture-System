<?php
require_once __DIR__ . "/../config/app_url.php";
require_once __DIR__ . "/security.php";
cropsense_apply_security_headers("web");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CropSense | <?php echo htmlspecialchars($pageTitle ?? "Live Monitoring"); ?></title>

    <meta name="theme-color" content="#123d2f">
    <link rel="icon" href="<?php echo htmlspecialchars(cropsense_asset('img/cropsense-logo.svg'), ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(cropsense_asset('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260722-classic-hero-v4">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(cropsense_asset('css/design-system.css'), ENT_QUOTES, 'UTF-8'); ?>?v=20260827-crop-summary-v2">
    <script nonce="<?php echo htmlspecialchars(cropsense_csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
        window.CROPSENSE_BASE_URL = <?php echo json_encode(CROPSENSE_BASE_URL, JSON_UNESCAPED_SLASHES); ?>;
    </script>
</head>

<body class="dashboard-body sidebar-initializing<?php echo isset($bodyExtraClass) ? ' ' . htmlspecialchars($bodyExtraClass) : ''; ?>">
