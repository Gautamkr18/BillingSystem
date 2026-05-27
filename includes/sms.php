<?php
// includes/sms.php
// SMS Helper Utility - Premium Local Simulation Mode
// (Twilio integration removed for zero-configuration, 100% free SMS notifications)

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function send_sms_otp($phone, $message) {
    // Normalize phone number (ensure country code is present, e.g. +91 or +1)
    $phone = trim($phone);
    if (!str_starts_with($phone, '+')) {
        if (strlen($phone) === 10) {
            $phone = '+91' . $phone;
        } else {
            $phone = '+' . $phone;
        }
    }
    
    // Local Premium Simulation Mode: Write to local logs
    $db_dir = dirname(__DIR__) . '/db-data';
    if (!file_exists($db_dir)) {
        mkdir($db_dir, 0777, true);
    }
    $log_file = $db_dir . '/sms_logs.txt';
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] TO: $phone | MESSAGE: $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Save the message in the session so register.php can render the beautiful alert toast!
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['sms_debug_toast'] = [
        'phone' => $phone,
        'message' => $message
    ];
    
    return true;
}
?>
