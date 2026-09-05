<?php

require_once __DIR__ . '/../config/app_url.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . cropsense_url('login.php'));
    exit();
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/../config/database.php';

cropsense_start_secure_session();

/*
|--------------------------------------------------------------------------
| Require pending OTP login
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['pending_2fa_user_id'])) {
    $_SESSION['error'] = 'Your verification session has expired. Please sign in again.';
    header('Location: ' . cropsense_url('login.php'));
    exit();
}

$userId = (int) $_SESSION['pending_2fa_user_id'];
$submittedOtp = trim($_POST['otp'] ?? '');

/*
|--------------------------------------------------------------------------
| Validate OTP format
|--------------------------------------------------------------------------
*/

if (!preg_match('/^\d{6}$/', $submittedOtp)) {
    $_SESSION['otp_error'] = 'Please enter a valid 6-digit verification code.';
    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

/*
|--------------------------------------------------------------------------
| Get latest unused OTP
|--------------------------------------------------------------------------
*/

$otpStmt = $conn->prepare(
    "SELECT id, otp_hash, expires_at, attempts
     FROM login_otps
     WHERE user_id = ?
       AND used = 0
     ORDER BY id DESC
     LIMIT 1"
);

if (!$otpStmt) {
    $_SESSION['otp_error'] = 'Unable to verify your code right now.';
    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

$otpStmt->bind_param('i', $userId);
$otpStmt->execute();

$result = $otpStmt->get_result();
$otpRecord = $result->fetch_assoc();

$otpStmt->close();

/*
|--------------------------------------------------------------------------
| No active OTP
|--------------------------------------------------------------------------
*/

if (!$otpRecord) {
    $_SESSION['otp_error'] =
        'No active verification code was found. Please sign in again.';

    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

/*
|--------------------------------------------------------------------------
| Maximum attempts
|--------------------------------------------------------------------------
*/

if ((int) $otpRecord['attempts'] >= 5) {

    $invalidateStmt = $conn->prepare(
        "UPDATE login_otps
         SET used = 1
         WHERE id = ?"
    );

    if ($invalidateStmt) {
        $invalidateStmt->bind_param('i', $otpRecord['id']);
        $invalidateStmt->execute();
        $invalidateStmt->close();
    }

    $_SESSION['otp_error'] =
        'Too many incorrect attempts. Please sign in again to receive a new code.';

    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

/*
|--------------------------------------------------------------------------
| Check expiration
|--------------------------------------------------------------------------
*/

if (strtotime($otpRecord['expires_at']) < time()) {

    $expireStmt = $conn->prepare(
        "UPDATE login_otps
         SET used = 1
         WHERE id = ?"
    );

    if ($expireStmt) {
        $expireStmt->bind_param('i', $otpRecord['id']);
        $expireStmt->execute();
        $expireStmt->close();
    }

    $_SESSION['otp_error'] =
        'Your verification code has expired. Please sign in again.';

    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

/*
|--------------------------------------------------------------------------
| Verify submitted OTP
|--------------------------------------------------------------------------
*/

if (!password_verify($submittedOtp, $otpRecord['otp_hash'])) {

    $failedStmt = $conn->prepare(
        "UPDATE login_otps
         SET attempts = attempts + 1
         WHERE id = ?"
    );

    if ($failedStmt) {
        $failedStmt->bind_param('i', $otpRecord['id']);
        $failedStmt->execute();
        $failedStmt->close();
    }

    $remainingAttempts = max(
        0,
        4 - (int) $otpRecord['attempts']
    );

    if ($remainingAttempts > 0) {
        $_SESSION['otp_error'] =
            "Incorrect verification code. {$remainingAttempts} attempt(s) remaining.";
    } else {
        $_SESSION['otp_error'] =
            'Incorrect verification code. Maximum attempts reached.';
    }

    cropsense_audit_log(
        $conn,
        $userId,
        'Failed email OTP verification'
    );

    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

/*
|--------------------------------------------------------------------------
| OTP is correct - reload user securely
|--------------------------------------------------------------------------
*/

$userStmt = $conn->prepare(
    "SELECT id, fullname, username, email, role, status
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$userStmt) {
    $_SESSION['otp_error'] = 'Unable to complete authentication.';
    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

$userStmt->bind_param('i', $userId);
$userStmt->execute();

$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

$userStmt->close();

if (!$user || ($user['status'] ?? '') !== 'Active') {

    cropsense_audit_log(
        $conn,
        $userId,
        'OTP login blocked because account is inactive or unavailable'
    );

    session_unset();
    session_destroy();

    header('Location: ' . cropsense_url('login.php'));
    exit();
}

/*
|--------------------------------------------------------------------------
| Mark OTP as used
|--------------------------------------------------------------------------
*/

$usedStmt = $conn->prepare(
    "UPDATE login_otps
     SET used = 1
     WHERE id = ?"
);

if (!$usedStmt) {
    $_SESSION['otp_error'] = 'Unable to complete verification.';
    header('Location: ' . cropsense_url('otp.php'));
    exit();
}

$usedStmt->bind_param('i', $otpRecord['id']);
$usedStmt->execute();
$usedStmt->close();

/*
|--------------------------------------------------------------------------
| Create REAL authenticated session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['fullname'] = $user['fullname'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

/*
|--------------------------------------------------------------------------
| Remove temporary OTP session
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['pending_2fa_user_id'],
    $_SESSION['pending_2fa_email'],
    $_SESSION['otp_sent_at'],
    $_SESSION['otp_error']
);

/*
|--------------------------------------------------------------------------
| Audit successful authentication
|--------------------------------------------------------------------------
*/

cropsense_audit_log(
    $conn,
    (int) $user['id'],
    'User signed in with email OTP verification'
);

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

header('Location: ' . cropsense_url('dashboard.php'));
exit();
