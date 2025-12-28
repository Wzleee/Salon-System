<?php
require_once '../users_management/auth_check.php';
requireLogin(['Admin']);
$pageTitle = 'Hours Management - Cosmos Salon';
$pageCSS = '../css/hours.css';
$currentPage = 'hours';
include '../head.php';
include '../nav.php';
?>
    <!-- Main Content -->
    <div class="container">
        <!-- Page Title -->
        <div class="page-title">
            <i class="far fa-clock"></i>
            <h2>Business Hours</h2>
        </div>

        <!-- Info Note -->
        <div class="info-note">
            <i class="fas fa-info-circle"></i>
            <p>Set your salon's operating hours for each day of the week. Changes will affect customer booking availability immediately.</p>
        </div>

        <!-- Hours Container -->
        <div class="hours-container">
            <!-- Loading State -->
            <div id="hours-loading" style="text-align: center; padding: 2rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #7c3aed;"></i>
                <p style="margin-top: 1rem; color: #6b7280;">Loading business hours...</p>
            </div>
            <!-- Hours Content -->
            <div id="hours-content" style="display: none;">
                    
            <div class="day-row" data-day="Monday">
                    <div class="day-name">Monday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="09:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="18:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="monday-closed" class="closed-toggle">
                        <label for="monday-closed">Closed</label>
                    </div>
                </div>

                <!-- Tuesday -->
                <div class="day-row" data-day="Tuesday">
                    <div class="day-name">Tuesday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="09:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="18:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="tuesday-closed" class="closed-toggle">
                        <label for="tuesday-closed">Closed</label>
                    </div>
                </div>

                <!-- Wednesday -->
                <div class="day-row" data-day="Wednesday">
                    <div class="day-name">Wednesday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="09:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="18:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="wednesday-closed" class="closed-toggle">
                        <label for="wednesday-closed">Closed</label>
                    </div>
                </div>

                <!-- Thursday -->
                <div class="day-row" data-day="Thursday">
                    <div class="day-name">Thursday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="09:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="18:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="thursday-closed" class="closed-toggle">
                        <label for="thursday-closed">Closed</label>
                    </div>
                </div>

                <!-- Friday -->
                <div class="day-row" data-day="Friday">
                    <div class="day-name">Friday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="09:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="18:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="friday-closed" class="closed-toggle">
                        <label for="friday-closed">Closed</label>
                    </div>
                </div>

                <!-- Saturday -->
                <div class="day-row" data-day="Saturday">
                    <div class="day-name">Saturday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="10:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="17:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="saturday-closed" class="closed-toggle">
                        <label for="saturday-closed">Closed</label>
                    </div>
                </div>

                <!-- Sunday -->
                <div class="day-row" data-day="Sunday">
                    <div class="day-name">Sunday</div>
                    <div class="time-inputs">
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="opening-time" value="10:00">
                        </div>
                        <span class="time-separator">to</span>
                        <div class="time-input-group">
                            <i class="far fa-clock"></i>
                            <input type="time" class="closing-time" value="17:00">
                        </div>
                    </div>
                    <div class="closed-checkbox">
                        <input type="checkbox" id="sunday-closed" class="closed-toggle">
                        <label for="sunday-closed">Closed</label>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="save-section">
                    <button class="save-btn" id="save-hours-btn">
                        <i class="far fa-save"></i>
                        Save Business Hours
                    </button>
                </div>
            </div>

            <!-- Holidays Section -->
            <div class="holidays-section">
                <div class="section-title">
                    <div class="title-icon">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h3>Holidays & Closures</h3>
                        <p>Add one-off or recurring dates when the salon is closed.</p>
                    </div>
                </div>

                <div class="add-holiday-card">
                    <div class="add-holiday-header">
                        <h4>Add New Holiday</h4>
                    </div>
                    <form id="holiday-form" class="holiday-form">
                        <div class="form-field">
                            <label for="holiday-date">Date</label>
                            <input type="date" id="holiday-date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-field">
                            <label for="holiday-name">Holiday Name</label>
                            <input type="text" id="holiday-name" placeholder="e.g., Christmas Day" required>
                        </div>
                        <div class="form-actions">
                            <label class="checkbox">
                                <input type="checkbox" id="holiday-recurring">
                                <span>Repeats every year</span>
                            </label>
                            <button type="submit" class="add-holiday-btn">Add Holiday</button>
                        </div>
                    </form>
                </div>

                <div id="holiday-list" class="holiday-list">
                    <div class="holiday-empty">Loading holidays...</div>
                </div>
            </div>
        </div>
    </div>
<script>
        
        document.addEventListener('DOMContentLoaded', function() {
            const todayStr = new Date().toISOString().slice(0, 10);
            const holidayDateInput = document.getElementById('holiday-date');
            if (holidayDateInput) {
                holidayDateInput.setAttribute('min', todayStr);
            }
            loadBusinessHours();
            setupEventListeners();
            loadHolidays();
            setupHolidayEvents();
        });

        
        function loadBusinessHours() {
            fetch('get_hours.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateHours(data.hours);
                        document.getElementById('hours-loading').style.display = 'none';
                        document.getElementById('hours-content').style.display = 'block';
                    } else {
                        showNotification('Error loading hours: ' + data.message, 'error');
                        document.getElementById('hours-loading').innerHTML = '<p style="color: #ef4444;">Failed to load hours. Please refresh the page.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to load business hours', 'error');
                    document.getElementById('hours-loading').innerHTML = '<p style="color: #ef4444;">Failed to load hours. Please refresh the page.</p>';
                });
        }

        
        function populateHours(hours) {
            hours.forEach(hour => {
                const dayRow = document.querySelector(`[data-day="${hour.day_of_week}"]`);
                if (dayRow) {
                    const openingTime = dayRow.querySelector('.opening-time');
                    const closingTime = dayRow.querySelector('.closing-time');
                    const closedToggle = dayRow.querySelector('.closed-toggle');
                    
                    openingTime.value = hour.opening_time;
                    closingTime.value = hour.closing_time;
                    closedToggle.checked = hour.is_closed;
                    
                    if (hour.is_closed) {
                        dayRow.classList.add('is-closed');
                        openingTime.disabled = true;
                        closingTime.disabled = true;
                    }
                }
            });
        }

        
        function setupEventListeners() {
            
            document.querySelectorAll('.closed-toggle').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const dayRow = this.closest('.day-row');
                    const timeInputs = dayRow.querySelectorAll('input[type="time"]');
                    
                    if (this.checked) {
                        dayRow.classList.add('is-closed');
                        timeInputs.forEach(input => input.disabled = true);
                    } else {
                        dayRow.classList.remove('is-closed');
                        timeInputs.forEach(input => input.disabled = false);
                    }
                });
            });

            
            document.getElementById('save-hours-btn').addEventListener('click', () => saveBusinessHours(false));
        }

        
        // 替换原来的 saveBusinessHours 函数

function saveBusinessHours(force = false) {
    // Only boolean true should enable the "force" flow (confirm + send emails).
    force = (force === true);
    const saveBtn = document.getElementById('save-hours-btn');
    const originalHTML = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const hoursData = [];
    let isValid = true;

    document.querySelectorAll('.day-row').forEach(row => {
        const day = row.dataset.day;
        const openingTime = row.querySelector('.opening-time').value;
        const closingTime = row.querySelector('.closing-time').value;
        const isClosed = row.querySelector('.closed-toggle').checked;

        if (!isClosed && openingTime >= closingTime) {
            showNotification(`Invalid hours for ${day}: Opening time must be before closing time`, 'error');
            isValid = false;
            return;
        }

        hoursData.push({
            day: day,
            opening_time: openingTime + ':00',
            closing_time: closingTime + ':00',
            is_closed: isClosed
        });
    });

    if (!isValid) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHTML;
        return;
    }

    fetch('save_hours.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ hours: hoursData, force })
    })
    .then(response => response.json())
    .then(data => {
        if (data.needs_confirm) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHTML;
            
            // 显示受影响的 bookings
            showAffectedBookingsModal(data.affected_bookings, () => {
                saveBusinessHours(true); // 用户确认后再次保存
            });
            return;
        }
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadBusinessHours(); // 重新加载数据
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Failed to save business hours', 'error');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalHTML;
    });
}

/**
 * 显示受影响的 bookings 模态框
 */
function showAffectedBookingsModal(bookings, onConfirm) {
    // 创建模态框
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Affected Bookings</h3>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                    <div class="warning-box">
                        <p><strong>${bookings.length} booking(s) will be affected</strong> by this change.</p>
                        <p>All customers will be automatically notified by email and asked to reschedule or cancel their appointments.</p>
                    </div>
                
                <div class="bookings-table-wrapper">
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${bookings.map(b => `
                                <tr>
                                    <td>${escapeHtml(b.customer_name)}</td>
                                    <td>${formatDate(b.date)}</td>
                                    <td>${b.time}</td>
                                    <td>${escapeHtml(b.service || 'N/A')}</td>
                                    <td><small>${escapeHtml(b.reason)}</small></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="btn-secondary" onclick="this.closest('.modal-overlay').remove()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-primary" id="confirm-save-btn">
                    <i class="fas fa-check"></i> Confirm & Send Emails
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // 确认按钮事件
    document.getElementById('confirm-save-btn').addEventListener('click', () => {
        modal.remove();
        onConfirm();
    });
    
    // 点击背景关闭
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

/**
 * 格式化日期
 */
function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

/**
 * 添加模态框样式 (添加到 hours.css 或在 head 中)
 */
function addModalStyles() {
    if (document.getElementById('modal-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'modal-styles';
    style.textContent = `
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: #f3f4f6;
            color: #111827;
        }
        
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .warning-box p {
            margin: 0 0 8px 0;
            color: #92400e;
        }
        
        .warning-box p:last-child {
            margin-bottom: 0;
        }
        
        .bookings-table-wrapper {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .bookings-table thead {
            background: #f9fafb;
        }
        
        .bookings-table th,
        .bookings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .bookings-table th {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .bookings-table td {
            color: #6b7280;
            font-size: 14px;
        }
        
        .bookings-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .bookings-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn-primary,
        .btn-secondary {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #7c3aed;
            color: white;
        }
        
        .btn-primary:hover {
            background: #6d28d9;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
    `;
    document.head.appendChild(style);
}

// 在 DOMContentLoaded 时添加样式
document.addEventListener('DOMContentLoaded', function() {
    addModalStyles();
    // ... 其他初始化代码
});

        
        // Unified flash-style notification (matches flash_message.php)
        function showNotification(message, type = 'success') {
            ensureFlashStyles();

            const existingNotification = document.querySelector('.flash-message');
            if (existingNotification) {
                existingNotification.remove();
            }

            const durationMs = window.FLASH_MESSAGE_DURATION_MS || 10000;
            const container = document.createElement('div');
            container.className = `flash-message flash-${type}`;
            container.id = 'flashMessage';

            const iconMap = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle',
                warning: 'fas fa-exclamation-triangle'
            };
            const icon = iconMap[type] || iconMap.info;

            container.innerHTML = `
                <div class="flash-content">
                    <i class="${icon}"></i>
                    <span>${message}</span>
                    <span class="flash-countdown" aria-hidden="true"></span>
                </div>
            `;

            document.body.appendChild(container);

            const countdownEl = container.querySelector('.flash-countdown');
            const endTime = Date.now() + durationMs;
            const updateCountdown = () => {
                if (!countdownEl) return;
                const remainingMs = endTime - Date.now();
                const remainingSec = Math.max(0, Math.ceil(remainingMs / 1000));
                countdownEl.textContent = `${remainingSec}s`;
            };
            updateCountdown();
            const intervalId = setInterval(updateCountdown, 1000);

            setTimeout(() => {
                clearInterval(intervalId);
                container.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => container.remove(), 300);
            }, durationMs);

            container.addEventListener('click', () => {
                clearInterval(intervalId);
                container.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => container.remove(), 300);
            });
        }

        function ensureFlashStyles() {
            if (document.getElementById('flash-style-inline')) return;
            const style = document.createElement('style');
            style.id = 'flash-style-inline';
            style.textContent = `
                .flash-message {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 16px 24px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    z-index: 10000;
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
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        // Holidays
        function setupHolidayEvents() {
            const form = document.getElementById('holiday-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    addHoliday();
                });
            }

            const list = document.getElementById('holiday-list');
            if (list) {
                list.addEventListener('click', function(e) {
                    if (e.target.classList.contains('holiday-remove')) {
                        const id = e.target.getAttribute('data-id');
                        deleteHoliday(id);
                    }
                });
            }
        }

        function loadHolidays() {
            fetch('get_holidays.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        showNotification('Failed to load holidays: ' + (data.message || ''), 'error');
                        return;
                    }
                    renderHolidays(data.holidays || []);
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Failed to load holidays', 'error');
                });
        }

        function renderHolidays(holidays) {
            const list = document.getElementById('holiday-list');
            if (!list) return;

            if (!holidays.length) {
                list.innerHTML = '<div class="holiday-empty">No holidays added yet.</div>';
                return;
            }

            list.innerHTML = '';
            holidays.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'holiday-item';
                const dateDisplay = formatHolidayDate(item.holiday_date, item.is_recurring);
                itemEl.innerHTML = `
                    <div class="holiday-info">
                        <div class="holiday-name">${escapeHtml(item.holiday_name)}</div>
                        <div class="holiday-date">${dateDisplay}</div>
                    </div>
                    <div class="holiday-actions">
                        ${item.is_recurring ? '<span class="badge badge-recurring">Recurring</span>' : ''}
                        <button class="holiday-remove" data-id="${item.holiday_id}">Remove</button>
                    </div>
                `;
                list.appendChild(itemEl);
            });
        }

        function addHoliday() {
            const nameInput = document.getElementById('holiday-name');
            const dateInput = document.getElementById('holiday-date');
            const recurringInput = document.getElementById('holiday-recurring');

            const payload = {
                holiday_name: nameInput.value.trim(),
                holiday_date: dateInput.value,
                is_recurring: recurringInput.checked
            };

            if (!payload.holiday_name || !payload.holiday_date) {
                showNotification('Please enter holiday name and date', 'error');
                return;
            }

            const todayStr = new Date().toISOString().slice(0, 10);
            if (payload.holiday_date < todayStr) {
                showNotification('Holiday date cannot be in the past', 'error');
                return;
            }

            fetch('add_holiday.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        showNotification(data.message || 'Failed to add holiday', 'error');
                        return;
                    }
                    nameInput.value = '';
                    dateInput.value = '';
                    recurringInput.checked = false;
                    loadHolidays();
                    showNotification('Holiday added', 'success');
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Failed to add holiday', 'error');
                });
        }

        function deleteHoliday(id) {
            if (!id) return;
            fetch('delete_holiday.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ holiday_id: id })
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        showNotification(data.message || 'Failed to delete holiday', 'error');
                        return;
                    }
                    loadHolidays();
                    showNotification('Holiday removed', 'success');
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Failed to delete holiday', 'error');
                });
        }

        function formatHolidayDate(dateStr, isRecurring) {
            const opts = { weekday: 'long', year: isRecurring ? undefined : 'numeric', month: 'long', day: 'numeric' };
            const date = new Date(dateStr + 'T00:00:00');
            if (Number.isNaN(date.getTime())) return dateStr;
            return date.toLocaleDateString('en-US', opts);
        }

        function escapeHtml(str) {
            return str.replace(/[&<>"']/g, function(m) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[m];
            });
        }
    </script>
</body>
</html>
