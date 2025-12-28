<?php
/**
 * Authentication Check Helper
 * Include this file at the top of pages that require login
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @param string|array $allowedRoles - Optional: Check if user has specific role(s)
 * @return bool
 */
function isLoggedIn($allowedRoles = null) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // If specific roles are required, check if user has one of them
    if ($allowedRoles !== null) {
        $userRole = $_SESSION['user_role'] ?? '';
        
        // Convert single role to array
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        return in_array($userRole, $allowedRoles);
    }
    
    return true;
}

/**
 * Require login - redirect to login page if not logged in
 * @param string|array $allowedRoles - Optional: Require specific role(s)
 */
function requireLogin($allowedRoles = null) {
    if (!isLoggedIn($allowedRoles)) {
        header('Location: ../users_management/login.php?error=unauthorized');
        exit();
    }

    enforceRoleAccess();
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin('Admin');
}

/**
 * Require staff role (Staff or Admin)
 */
function requireStaff() {
    requireLogin(['Staff', 'Admin']);
}

/**
 * Get current user information
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? null,
    ];
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    return isLoggedIn('Admin');
}

/**
 * Check if current user is staff (Staff or Admin)
 * @return bool
 */
function isStaff() {
    return isLoggedIn(['Staff', 'Admin']);
}

/**
 * Check if current user is customer
 * @return bool
 */
function isCustomer() {
    return isLoggedIn('Customer');
}

/**
 * Define per-role allowed PHP entry points.
 * Admin has wildcard access.
 *
 * @return array
 */
function getRolePermissions() {
    return [
        'Admin' => ['*'],
        'Staff' => [
            'schedule_service/schedule.php',
            'schedule_service/bookings.php',
            'schedule_service/get_hours.php',
            'schedule_service/get_holidays.php',
            'users_management/profile.php',
        ],
        'Customer' => [
            'appointment/appointment.php',
            'appointment/my_bookings.php',
            'appointment/reschedule.php',
            'appointment/booking_details.php',
            'appointment/booking_confirmation.php',
            'appointment/get_available_slots.php',
            'appointment/get_business_hours.php',
            'users_management/profile.php',
        ],
    ];
}

/**
 * Normalize paths to forward-slash form without leading slash.
 *
 * @param string $path
 * @return string
 */
function normalizePath($path) {
    $normalized = str_replace('\\', '/', $path);
    return ltrim($normalized, '/');
}

/**
 * Check if current path ends with the allowed path (handles subfolder prefixes).
 *
 * @param string $currentPath
 * @param string $allowedPath
 * @return bool
 */
function pathMatches($currentPath, $allowedPath) {
    $current = normalizePath($currentPath);
    $allowed = normalizePath($allowedPath);

    if ($allowed === '') {
        return false;
    }

    return substr($current, -strlen($allowed)) === $allowed;
}

/**
 * Enforce route-level permissions for the logged-in user.
 * Admin can access everything. Others are limited to their allowlist.
 */
function enforceRoleAccess() {
    if (!isLoggedIn()) {
        header('Location: ../users_management/login.php?error=login_required');
        exit();
    }

    $role = $_SESSION['user_role'] ?? '';
    $currentPath = $_SERVER['PHP_SELF'] ?? '';

    $permissions = getRolePermissions();
    $allowedPaths = $permissions[$role] ?? [];

    $hasWildcard = in_array('*', $allowedPaths, true);
    if ($hasWildcard) {
        return;
    }

    foreach ($allowedPaths as $allowedPath) {
        if (pathMatches($currentPath, $allowedPath)) {
            return;
        }
    }

    http_response_code(403);
    echo 'Access denied for your role.';
    exit();
}
?>
