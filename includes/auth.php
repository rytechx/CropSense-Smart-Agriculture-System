<?php
if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . "/security.php";
require_once __DIR__ . "/roles.php";

cropsense_start_secure_session();

require_once __DIR__ . "/../config/database.php";

    $login = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($login) || empty($password)) {
        $_SESSION['error'] = "Please enter your username/email and password.";
        header("Location: ../login.php");
        exit();
    }

    $sql = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = "Login is not ready. Please check the database setup.";
        header("Location: ../login.php");
        exit();
    }

    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();

    $result = $stmt->get_result();

    if (!$result) {
        $_SESSION['error'] = "Unable to check account. Please check the database setup.";
        header("Location: ../login.php");
        exit();
    }

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (($user['status'] ?? 'Active') !== 'Active') {
            $_SESSION['error'] = "Your account is inactive.";

            cropsense_audit_log($conn, (int) $user['id'], "Blocked login attempt for inactive account");

            header("Location: ../login.php");
            exit();
        }

        if (password_verify($password, $user['password'])) {

    require_once __DIR__ . '/mailer.php';

    /* -----------------------------------------
       Invalidate previous unused OTPs
       ----------------------------------------- */
    $invalidateOtp = $conn->prepare(
        "UPDATE login_otps
         SET used = 1
         WHERE user_id = ?
         AND used = 0"
    );

    if ($invalidateOtp) {
        $invalidateOtp->bind_param("i", $user['id']);
        $invalidateOtp->execute();
        $invalidateOtp->close();
    }

    /* -----------------------------------------
       Generate secure 6-digit OTP
       ----------------------------------------- */
    $otp = (string) random_int(100000, 999999);

    /* Never save OTP in plaintext */
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    /* 5-minute expiration */
    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + 300
    );

    /* -----------------------------------------
       Save OTP
       ----------------------------------------- */
    $otpStmt = $conn->prepare(
        "INSERT INTO login_otps
        (user_id, otp_hash, expires_at, attempts, used)
        VALUES (?, ?, ?, 0, 0)"
    );

    if (!$otpStmt) {

        $_SESSION['error'] =
            "Unable to prepare verification code.";

        header("Location: ../login.php");
        exit();
    }

    $otpStmt->bind_param(
        "iss",
        $user['id'],
        $otpHash,
        $expiresAt
    );

    if (!$otpStmt->execute()) {

        $_SESSION['error'] =
            "Unable to generate verification code.";

        header("Location: ../login.php");
        exit();
    }

    $otpStmt->close();

    /* -----------------------------------------
       Send OTP through email
       ----------------------------------------- */
    $emailSent = cropsenseSendOtp(
        $user['email'],
        $user['fullname'],
        $otp
    );

    if (!$emailSent) {

        $_SESSION['error'] =
            "Unable to send verification code. Please try again.";

        header("Location: ../login.php");
        exit();
    }

    /* -----------------------------------------
       Temporary 2FA session
       NOT a logged-in session yet
       ----------------------------------------- */
    session_regenerate_id(true);

    $_SESSION['pending_2fa_user_id'] =
        (int) $user['id'];

    $_SESSION['pending_2fa_email'] =
        $user['email'];

    $_SESSION['otp_sent_at'] =
        time();

    cropsense_audit_log(
        $conn,
        (int) $user['id'],
        "Email OTP sent"
    );

    header("Location: ../otp.php");
    exit();
} else {

            $_SESSION['error'] = "Invalid password.";

            cropsense_audit_log($conn, (int) $user['id'], "Failed login attempt");

            header("Location: ../login.php");
            exit();

        }

    } else {

        $_SESSION['error'] = "Account not found.";

        header("Location: ../login.php");
        exit();

    }

?>
