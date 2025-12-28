
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hasFlashMessage = false;
$flashType = 'success'; // success, error, info, warning
$flashMessage = '';
$validTypes = ['success', 'error', 'info', 'warning'];

$sources = [
    ['messageKey' => 'flash_message', 'typeKey' => 'flash_type', 'defaultType' => 'info'],
    ['messageKey' => 'success_message', 'defaultType' => 'success'],
    ['messageKey' => 'error_message', 'defaultType' => 'error'],
    ['messageKey' => 'register_success', 'defaultType' => 'success'],
    ['messageKey' => 'login_success', 'defaultType' => 'success'],
];

foreach ($sources as $source) {
    if (!isset($_SESSION[$source['messageKey']])) {
        continue;
    }

    $flashMessage = $_SESSION[$source['messageKey']];
    $type = $source['defaultType'] ?? 'info';

    if (!empty($source['typeKey']) && isset($_SESSION[$source['typeKey']]) && in_array($_SESSION[$source['typeKey']], $validTypes, true)) {
        $type = $_SESSION[$source['typeKey']];
    }

    $flashType = in_array($type, $validTypes, true) ? $type : 'info';
    $hasFlashMessage = true;

    unset($_SESSION[$source['messageKey']]);
    if (!empty($source['typeKey'])) {
        unset($_SESSION[$source['typeKey']]);
    }
    break;
}

$icons = [
    'success' => 'fas fa-check-circle',
    'error' => 'fas fa-exclamation-circle',
    'info' => 'fas fa-info-circle',
    'warning' => 'fas fa-exclamation-triangle',
];
$flashIcon = $icons[$flashType] ?? 'fas fa-info-circle';
?>

<?php if ($hasFlashMessage): ?>
<div class="flash-message flash-<?php echo $flashType; ?>" id="flashMessage">
    <div class="flash-content">
        <i class="<?php echo $flashIcon; ?>"></i>
        <span><?php echo htmlspecialchars($flashMessage); ?></span>
        <span class="flash-countdown" aria-hidden="true"></span>
    </div>
</div>

<style>
.flash-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    max-width: 400px;
    animation: slideInRight 0.3s ease-out;
}

.flash-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.flash-content i {
    font-size: 20px;
}

.flash-success {
    background-color: #d1fae5;
    border: 1px solid #6ee7b7;
    color: #065f46;
}

.flash-error {
    background-color: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

.flash-info {
    background-color: #dbeafe;
    border: 1px solid #93c5fd;
    color: #1e40af;
}

.flash-warning {
    background-color: #fef3c7;
    border: 1px solid #fcd34d;
    color: #92400e;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* ‡Ż†S"‡®_‚?,‚.? */
@media (max-width: 768px) {
    .flash-message {
        left: 20px;
        right: 20px;
        max-width: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        // Auto-hide after a configurable delay
        const durationMs = (typeof window.FLASH_MESSAGE_DURATION_MS === 'number' && window.FLASH_MESSAGE_DURATION_MS > 0)
            ? window.FLASH_MESSAGE_DURATION_MS
            : 10000;

        const countdownEl = flashMessage.querySelector('.flash-countdown');
        const endTime = Date.now() + durationMs;
        let dismissed = false;
        let countdownIntervalId = null;
        let autoHideTimeoutId = null;

        function updateCountdown() {
            if (!countdownEl) return;
            const remainingMs = endTime - Date.now();
            const remainingSec = Math.max(0, Math.ceil(remainingMs / 1000));
            countdownEl.textContent = `${remainingSec}s`;
        }

        function dismiss() {
            if (dismissed) return;
            dismissed = true;

            if (autoHideTimeoutId) clearTimeout(autoHideTimeoutId);
            if (countdownIntervalId) clearInterval(countdownIntervalId);

            flashMessage.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(function() {
                flashMessage.remove();
            }, 300);
        }

        updateCountdown();
        if (countdownEl) {
            countdownIntervalId = setInterval(updateCountdown, 1000);
        }
        autoHideTimeoutId = setTimeout(dismiss, durationMs);
    }
});
</script>
<?php endif; ?>
