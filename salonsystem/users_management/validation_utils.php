<?php
/**
 * Shared Validation Utilities
 * Used across registration, profile updates, and user management
 */

/**
 * Validate password strength
 * @param string $password
 * @return true|string Returns true if valid, error message if invalid
 */
function validatePasswordStrength($password) {
    // At least 8 characters
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    
    // Must contain at least one letter
    if (!preg_match('/[a-zA-Z]/', $password)) {
        return "Password must contain at least one letter.";
    }
    
    // Must contain at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    
    return true;
}

/**
 * Validate Malaysian phone number format
 * @param string $phone
 * @return true|string Returns true if valid, error message if invalid
 */
function validateMalaysianPhone($phone) {
    // Remove all spaces
    $phone = str_replace(' ', '', $phone);
    
    // Format: 01X-XXXXXXXX or 01X-XXXXXXXXX (mobile)
    //         03-XXXXXXXX or 03-XXXXXXXXX (landline)
    if (preg_match('/^01[0-9]-[0-9]{8,9}$/', $phone)) {
        return true;
    }
    
    if (preg_match('/^03-[0-9]{8,9}$/', $phone)) {
        return true;
    }
    
    return "Invalid phone format. Use: 01X-XXXXXXXX or 03-XXXXXXXX (8-9 digits after dash)";
}

/**
 * Validate email format and length
 * @param string $email
 * @return true|string Returns true if valid, error message if invalid
 */
function validateEmail($email) {
    if (empty($email)) {
        return "Email is required";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }
    
    if (strlen($email) > 100) {
        return "Email cannot exceed 100 characters";
    }
    
    return true;
}

/**
 * Validate name
 * @param string $name
 * @return true|string Returns true if valid, error message if invalid
 */
function validateName($name) {
    if (empty($name)) {
        return "Name is required";
    }
    
    if (strlen($name) > 50) {
        return "Name cannot exceed 50 characters";
    }
    
    return true;
}

/**
 * Validate address (optional field)
 * @param string $address
 * @return true|string Returns true if valid, error message if invalid
 */
function validateAddress($address) {
    if (!empty($address) && strlen($address) > 255) {
        return "Address cannot exceed 255 characters";
    }
    
    return true;
}

/**
 * Check if email exists in database (excluding specific user)
 * @param PDO $pdo
 * @param string $email
 * @param int|null $excludeUserId
 * @return bool Returns true if email exists
 */
function emailExists($pdo, $email, $excludeUserId = null) {
    if ($excludeUserId) {
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $excludeUserId]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ?");
        $stmt->execute([$email]);
    }
    
    return $stmt->fetch() !== false;
}

/**
 * Get password requirements text (for UI display)
 * @return array
 */
function getPasswordRequirements() {
    return [
        'At least <strong>8 characters</strong> long',
        'Must contain at least <strong>one letter</strong> (A-Z or a-z)',
        'Must contain at least <strong>one number</strong> (0-9)',
        'Special characters are optional'
    ];
}

/**
 * Get phone format hint text (for UI display)
 * @return string
 */
function getPhoneFormatHint() {
    return 'Format: 01X-XXXXXXXX or 03-XXXXXXXX (8-9 digits after dash)';
}

/**
 * Get phone format examples (for UI display)
 * @return array
 */
function getPhoneExamples() {
    return [
        '012-34567890',
        '013-12345678',
        '03-87654321'
    ];
}

