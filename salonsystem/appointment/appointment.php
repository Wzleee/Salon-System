<?php
require_once '../config.php';
require_once '../users_management/auth_check.php';
requireLogin(['Admin', 'Customer']);
require_once 'email_utility.php'; // Add this line

$pageCSS = '../css/appointment.css';
$pageTitle = 'Book Appointment - Cosmos Salon';
$currentPage = 'appointment';
$user_id = $_SESSION['user_id'];

// Get all services grouped by category
$services = [];
$categoryLabels = [];
try {
    $sql = "SELECT s.*, c.category_name 
            FROM service s 
            JOIN category c ON s.category_id = c.category_id 
            WHERE s.status = 'Active' 
            ORDER BY c.category_name, s.service_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryName = trim($row['category_name'] ?? '');
        if ($categoryName === '') {
            continue;
        }
        $categoryName = preg_replace('/\s+/', ' ', $categoryName);
        $categoryKey = strtolower($categoryName);
        if (!isset($categoryLabels[$categoryKey])) {
            $categoryLabels[$categoryKey] = $categoryName;
            $services[$categoryLabels[$categoryKey]] = [];
        }
        $services[$categoryLabels[$categoryKey]][] = $row;
    }
} catch (PDOException $e) {
    error_log("Error loading services: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading services. Please try again later.";
}

// Get all stylists
$stylists = [];
try {
    $sql = "SELECT s.stylist_id, u.name as stylist_name, s.specialization, s.experience_years, s.photo
            FROM stylist s 
            JOIN user u ON s.user_id = u.user_id
            ORDER BY u.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stylists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading stylists: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading stylists. Please try again later.";
}

// Handle appointment booking
if (isset($_POST['book_appointment'])) {
    $service_ids = $_POST['service_ids'] ?? []; // Array of service IDs
    if (!is_array($service_ids)) {
        $service_ids = [];
    }
    $service_ids = array_values(array_filter(array_map('intval', $service_ids), fn($id) => $id > 0));

    $stylist_id = (int)($_POST['stylist_id'] ?? 0);
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $appointment_time = trim($_POST['appointment_time'] ?? '');

    // Server-side validation (in case JS validation is bypassed)
    if (empty($service_ids)) {
        $_SESSION['error_message'] = "Please select at least one service.";
    } elseif ($stylist_id <= 0) {
        $_SESSION['error_message'] = "Please select a stylist.";
    } elseif ($appointment_date === '') {
        $_SESSION['error_message'] = "Please select an appointment date.";
    } elseif ($appointment_time === '') {
        $_SESSION['error_message'] = "Please select an appointment time.";
    } else {
        $isHolidayDate = false;
        try {
            $holidayCheck = $pdo->prepare("
                SELECT 1 FROM holiday 
                WHERE holiday_date = :date 
                   OR (is_recurring = 1 AND DATE_FORMAT(holiday_date, '%m-%d') = DATE_FORMAT(:date, '%m-%d'))
                LIMIT 1
            ");
            $holidayCheck->execute([':date' => $appointment_date]);
            $isHolidayDate = (bool)$holidayCheck->fetchColumn();
        } catch (PDOException $e) {
            // If the lookup fails, continue without blocking but log it.
            error_log('Holiday check failed: ' . $e->getMessage());
        }

        if ($isHolidayDate) {
            $_SESSION['error_message'] = "The selected date is unavailable due to a holiday. Please choose another date.";
        } else {
            try {
            // Calculate total price from all selected services
            $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
            $sql = "SELECT SUM(price) as total FROM service WHERE service_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($service_ids);
            $total_price = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Insert appointment
            $sql = "INSERT INTO appointment (user_id, stylist_id, total_price, appointment_date, appointment_time, status) 
                    VALUES (:user_id, :stylist_id, :total_price, :appointment_date, :appointment_time, 'Confirmed')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':stylist_id' => $stylist_id,
                ':total_price' => $total_price,
                ':appointment_date' => $appointment_date,
                ':appointment_time' => $appointment_time
            ]);
            
            $appointment_id = $pdo->lastInsertId();
            
            // Insert all appointment items
            $sql = "INSERT INTO appointmentitem (appointment_id, service_id, service_price, quantity) 
                    VALUES (:appointment_id, :service_id, :service_price, 1)";
            $stmt = $pdo->prepare($sql);
            
            foreach ($service_ids as $service_id) {
                // Get individual service price
                $priceSql = "SELECT price FROM service WHERE service_id = ?";
                $priceStmt = $pdo->prepare($priceSql);
                $priceStmt->execute([$service_id]);
                $service_price = $priceStmt->fetch(PDO::FETCH_ASSOC)['price'];
                
                $stmt->execute([
                    ':appointment_id' => $appointment_id,
                    ':service_id' => $service_id,
                    ':service_price' => $service_price
                ]);
            }

            // ============ SEND CONFIRMATION EMAIL ============
            try {
                // Get customer details
                $sql = "SELECT name, email FROM user WHERE user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':user_id' => $user_id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get stylist name
                $sql = "SELECT u.name as stylist_name 
                        FROM stylist s 
                        JOIN user u ON s.user_id = u.user_id 
                        WHERE s.stylist_id = :stylist_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':stylist_id' => $stylist_id]);
                $stylist = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get all services
                $sql = "SELECT s.service_name, s.duration_minutes, ai.service_price
                        FROM appointmentitem ai
                        JOIN service s ON ai.service_id = s.service_id
                        WHERE ai.appointment_id = :appointment_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':appointment_id' => $appointment_id]);
                $services_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Prepare booking details for email
                $bookingDetails = [
                    'appointment_id' => $appointment_id,
                    'appointment_date' => $appointment_date,
                    'appointment_time' => $appointment_time,
                    'stylist_name' => $stylist['stylist_name'],
                    'total_price' => $total_price,
                    'services' => $services_list
                ];
                
                // Send email
                sendBookingConfirmation(
                    $customer['email'],
                    $customer['name'],
                    $bookingDetails
                );
            } catch (Exception $e) {
                error_log("Email error: " . $e->getMessage());
                // Continue even if email fails
            }
            // ================================================
            
            header("Location: booking_confirmation.php?id=" . $appointment_id);
            exit;
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error booking appointment: " . $e->getMessage();
            }
        }
    }
}

include '../head.php';
include '../flash_message.php';

?>

<div class="appointment-container">
    <!-- Page Header with Cancel Button-->
    <div class="appointment-header">
        <a href="my_bookings.php" class="cancel-btn">
            <i class="fas fa-times"></i> Cancel
        </a>
        <div class="header-content">
            <h1>Book an Appointment</h1>
            <p>Schedule your visit at Cosmos Salon</p>
        </div>
    </div>

    <!-- Step Indicator -->
    <div class="steps-container">
        <div class="step active" id="step-1">
            <div class="step-number">1</div>
            <div class="step-label">Services</div>
        </div>
        <div class="step" id="step-2">
            <div class="step-number">2</div>
            <div class="step-label">Stylist</div>
        </div>
        <div class="step" id="step-3">
            <div class="step-number">3</div>
            <div class="step-label">Date & Time</div>
        </div>
    </div>

    <form method="POST" action="" id="appointmentForm">

    <!-- Step 1: Service Selection -->
<div class="form-step active" id="service-step">
    <div class="step-title">
        <h2>Select Services</h2>
        <p style="color: #6b7280; font-size: 0.95rem; margin-top: 0.5rem;">Choose one or more services</p>
    </div>

    <!-- Category Filter Chips -->
    <div class="category-filter-container">
        <div class="filter-chips">
            <button type="button" class="filter-chip active" data-filter="all" onclick="filterServicesByCategory(this)">
                All Services
            </button>
            <?php 
            $displayedCategories = [];
            foreach ($services as $category => $categoryServices): 
                if (!in_array($category, $displayedCategories)):
                    $displayedCategories[] = $category;
                    $categorySlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $category));
            ?>
                <button type="button" class="filter-chip" 
                        data-filter="<?php echo $categorySlug; ?>" 
                        onclick="filterServicesByCategory(this)">
                    <?php echo htmlspecialchars($category); ?>
                </button>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
    </div>

    <div class="services-grid">
        <?php foreach ($services as $category => $categoryServices): 
            $categorySlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $category));
        ?>
            <div class="service-category-section" data-category="<?php echo $categorySlug; ?>">
                <h3 class="category-header"><?php echo htmlspecialchars($category); ?></h3>
                <div class="services-list">
                    <?php foreach ($categoryServices as $service): ?>
                        <label class="service-option">
                            <input type="checkbox" 
                                   name="service_ids[]" 
                                   value="<?php echo $service['service_id']; ?>"
                                   data-price="<?php echo $service['price']; ?>"
                                   data-duration="<?php echo $service['duration_minutes']; ?>"
                                   data-name="<?php echo htmlspecialchars($service['service_name']); ?>"
                                   onchange="updateSummary()">
                            <div class="service-card">
                                <div class="service-info">
                                    <h4><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                    <p class="service-description"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
                                    <p class="service-duration">
                                        <i class="far fa-clock"></i>
                                        <?php echo $service['duration_minutes']; ?> min
                                    </p>
                                </div>
                                <div class="service-price">
                                    RM <?php echo number_format($service['price'], 2); ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State Message -->
    <div id="noServicesInCategory" class="empty-category-message" style="display: none;">
        <i class="far fa-folder-open"></i>
        <p>No services found in this category</p>
    </div>

    <!-- Summary Panel -->
    <div id="summary-panel" class="summary-panel" style="display: none;">
        <h3><i class="fas fa-shopping-cart"></i> Selected Services</h3>
        <div id="summary-items"></div>
        <div class="summary-totals">
            <div class="summary-row">
                <span>Total Duration:</span>
                <strong id="total-duration">0 min</strong>
            </div>
            <div class="summary-row total">
                <span>Total Price:</span>
                <strong id="total-price">RM 0.00</strong>
            </div>
        </div>
    </div>
</div>

        <!-- Step 2: Stylist Selection -->
        <div class="form-step" id="stylist-step">
            <div class="step-title">
                <h2>Select a Stylist</h2>
            </div>

            <div class="stylists-grid">
                <?php if (!empty($stylists)): ?>
                    <?php foreach ($stylists as $stylist): ?>
                        <label class="stylist-option">
                            <input type="radio" name="stylist_id" value="<?php echo $stylist['stylist_id']; ?>" required>
                            <div class="stylist-card">
                                <div class="stylist-avatar">
                                    <img src="<?php echo htmlspecialchars(!empty($stylist['photo']) ? $stylist['photo'] : '/salonsystem/images/default-pic.jpg'); ?>"
                                         alt="<?php echo htmlspecialchars($stylist['stylist_name']); ?>"
                                         onerror="this.onerror=null; this.src='/salonsystem/images/default-pic.jpg';">
                                </div>
                                <h4><?php echo htmlspecialchars($stylist['stylist_name']); ?></h4>
                                <p class="stylist-spec"><?php echo htmlspecialchars($stylist['specialization'] ?? ''); ?></p>
                                <p class="stylist-exp"><?php echo $stylist['experience_years']; ?> years experience</p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-state">No stylists available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 3: Date & Time Selection -->
        <div class="form-step" id="datetime-step">
            <div class="step-title">
                <h2>Select Date & Time</h2>
            </div>

            <div class="datetime-container">
                <!-- Calendar -->
                <div class="calendar-section">
                    <div class="calendar-header">
                        <button type="button" class="btn-nav" onclick="changeMonth(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h3 id="currentMonth"></h3>
                        <button type="button" class="btn-nav" onclick="changeMonth(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="calendar-grid" id="calendarGrid"></div>
                </div>

                <!-- Time Slots -->
                <div class="timeslots-section">
                    <h3 id="selectedDateDisplay">Select a date</h3>
                    <div id="timeSlotsContainer" class="timeslots-grid">
                        <p class="empty-state">Please select a date to view available time slots</p>
                    </div>
                </div>
            </div>

            <!-- Hidden inputs -->
            <input type="hidden" id="appointment_date" name="appointment_date" required>
            <input type="hidden" id="appointment_time" name="appointment_time" required>
        </div>

        <!-- Navigation Buttons -->
        <div class="form-navigation">
            <button type="button" class="btn btn-secondary" id="backBtn" onclick="previousStep()" style="display:none;">
                <i class="fas fa-chevron-left"></i>
                Back
            </button>
            <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()">
                Next
                <i class="fas fa-chevron-right"></i>
            </button>
            <button type="submit" class="btn btn-success" id="submitBtn" name="book_appointment" style="display:none;">
                <i class="fas fa-check"></i>
                Book Appointment
            </button>
        </div>
    </form>
</div>


<script>
let currentStep = 1;
const totalSteps = 3;
let currentDate = new Date();
let selectedDate = null;
let selectedStylistId = null;
let totalServiceDuration = 0;

let businessHours = {};
let holidays = []; //add holidays array


// Category Filter Function
function filterServicesByCategory(button) {
    // Update active state
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    button.classList.add('active');
    
    const filter = button.dataset.filter;
    const categorySections = document.querySelectorAll('.service-category-section');
    const emptyMessage = document.getElementById('noServicesInCategory');
    let hasVisibleSections = false;
    
    categorySections.forEach(section => {
        if (filter === 'all' || section.dataset.category === filter) {
            section.style.display = 'flex';
            hasVisibleSections = true;
        } else {
            section.style.display = 'none';
        }
    });
    
    // Show/hide empty message
    if (hasVisibleSections) {
        emptyMessage.style.display = 'none';
    } else {
        emptyMessage.style.display = 'flex';
    }
    
    // Smooth scroll to services grid
    document.querySelector('.services-grid').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'nearest' 
    });
}

// Reset date and time selection when stylist changes
function resetDateTimeSelection() {
    selectedDate = null;
    currentDate = new Date();
    businessHours = {};
    holidays = [];
    document.getElementById('appointment_date').value = '';
    document.getElementById('appointment_time').value = '';
    document.getElementById('selectedDateDisplay').textContent = 'Select a date';
    const container = document.getElementById('timeSlotsContainer');
    if (container) {
        container.innerHTML = '<p class="empty-state">Please select a date to view available time slots</p>';
    }
    renderCalendar();
}

// added function to get selected service IDs
function getSelectedServiceIds() {
    return Array.from(document.querySelectorAll('input[name="service_ids[]"]:checked')).map(cb => cb.value);
}

// Update summary panel when services are selected
function updateSummary() {
    const checkboxes = document.querySelectorAll('input[name="service_ids[]"]:checked');
    const summaryPanel = document.getElementById('summary-panel');
    const summaryItems = document.getElementById('summary-items');
    
    if (checkboxes.length === 0) {
        summaryPanel.style.display = 'none';
        totalServiceDuration = 0;
        return;
    }
    
    summaryPanel.style.display = 'block';
    summaryItems.innerHTML = '';
    
    let totalPrice = 0;
    totalServiceDuration = 0;
    
    checkboxes.forEach(cb => {
        const price = parseFloat(cb.dataset.price);
        const duration = parseInt(cb.dataset.duration);
        const name = cb.dataset.name;
        
        totalPrice += price;
        totalServiceDuration += duration;
        
        const item = document.createElement('div');
        item.className = 'summary-item';
        item.innerHTML = `
            <span>${name}</span>
            <span>RM ${price.toFixed(2)}</span>
        `;
        summaryItems.appendChild(item);
    });
    
    document.getElementById('total-duration').textContent = `${totalServiceDuration} min`;
    document.getElementById('total-price').textContent = `RM ${totalPrice.toFixed(2)}`;
}

function showStep(step) {
    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));

    document.getElementById('service-step').classList.toggle('active', step === 1);
    document.getElementById('stylist-step').classList.toggle('active', step === 2);
    document.getElementById('datetime-step').classList.toggle('active', step === 3);

    document.getElementById('step-' + step).classList.add('active');

    document.getElementById('backBtn').style.display = step === 1 ? 'none' : 'flex';
    document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'flex';
    document.getElementById('submitBtn').style.display = step === totalSteps ? 'flex' : 'none';

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Unified flash-style message (matches flash_message.php + common.css)
function showFlashMessage(message, type = 'info') {
    const existing = document.getElementById('flashMessage');
    if (existing) {
        existing.remove();
    }

    const durationMs = window.FLASH_MESSAGE_DURATION_MS || 10000;
    const iconMap = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle',
        warning: 'fas fa-exclamation-triangle'
    };

    const flash = document.createElement('div');
    flash.className = `flash-message flash-${type}`;
    flash.id = 'flashMessage';

    const icon = document.createElement('i');
    icon.className = iconMap[type] || iconMap.info;

    const span = document.createElement('span');
    span.textContent = message;

    const countdown = document.createElement('span');
    countdown.className = 'flash-countdown';
    countdown.setAttribute('aria-hidden', 'true');

    flash.appendChild(icon);
    flash.appendChild(span);
    flash.appendChild(countdown);
    document.body.appendChild(flash);

    const dismiss = () => {
        flash.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => flash.remove(), 300);
    };

    const endTime = Date.now() + durationMs;
    const updateCountdown = () => {
        const remainingMs = endTime - Date.now();
        const remainingSec = Math.max(0, Math.ceil(remainingMs / 1000));
        countdown.textContent = `${remainingSec}s`;
    };

    updateCountdown();
    const intervalId = setInterval(updateCountdown, 1000);
    const timeoutId = setTimeout(() => {
        clearInterval(intervalId);
        dismiss();
    }, durationMs);

    flash.addEventListener('click', () => {
        clearInterval(intervalId);
        clearTimeout(timeoutId);
        dismiss();
    });
}

function validateStep(step) {
    if (step === 1) {
        const checked = document.querySelectorAll('input[name="service_ids[]"]:checked').length;
        if (checked === 0) {
            showFlashMessage('Please select at least one service.', 'warning');
            return false;
        }
        return true;
    }  else if (step === 2) {
        const stylistSelected = document.querySelector('input[name="stylist_id"]:checked');
        if (!stylistSelected) {
            showFlashMessage('Please select a stylist.', 'warning');
            return false;
        }
        return true;
    } else if (step === 3) {
        const dateSelected = document.getElementById('appointment_date').value;
        const timeSelected = document.getElementById('appointment_time').value;
        if (!dateSelected) {
            showFlashMessage('Please select an appointment date.', 'warning');
            return false;
        }
        if (!timeSelected) {
            showFlashMessage('Please select an appointment time.', 'warning');
            return false;
        }
        return true;
    }
    return false;
}

function nextStep() {
    if (validateStep(currentStep)) {
        if (currentStep === 2) {
            const newlySelectedStylistId = document.querySelector('input[name="stylist_id"]:checked')?.value;
            if (newlySelectedStylistId) {
                if (newlySelectedStylistId !== selectedStylistId) { // Stylist changed
                    resetDateTimeSelection();
                }
                selectedStylistId = newlySelectedStylistId;
                fetchBusinessHours(selectedStylistId);
            }
        }
        if (currentStep < totalSteps) {
            currentStep++;
            showStep(currentStep);
        }
    }
}

function previousStep() {
    if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
    }
}

// Calendar and time slot functions
function formatDateLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function isHolidayDate(date) { // Check if date is a holiday
    const dateStr = formatDateLocal(date);
    const monthDay = dateStr.slice(5);
    return holidays.some(h => (h.is_recurring && h.month_day === monthDay) || (!h.is_recurring && h.date === dateStr));
}

// NEW: Format time in 12-hour format (2:00 PM)
function formatTime(time) {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
    return `${displayHour}:${minutes} ${ampm}`;
}

async function fetchBusinessHours(stylistId) {
    try {
        const response = await fetch('get_business_hours.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ stylist_id: stylistId })
        });
        
        const hours = await response.json();
        if (!hours.error) {
            businessHours = hours.business_hours || hours;
            holidays = hours.holidays || [];  // Store holidays
            renderCalendar();
        }
    } catch (error) {
        console.error('Error fetching business hours:', error);
    }
}


function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    document.getElementById('currentMonth').textContent = 
        currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startingDayOfWeek = firstDay.getDay();
    
    const calendarGrid = document.getElementById('calendarGrid');
    calendarGrid.innerHTML = '';
    
    const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    dayHeaders.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day header';
        header.textContent = day;
        calendarGrid.appendChild(header);
    });
    
    for (let i = 0; i < startingDayOfWeek; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day disabled';
        calendarGrid.appendChild(empty);
    }
    
    const today = new Date(); 
    today.setHours(0, 0, 0, 0);
    
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        const dayOfWeek = date.toLocaleString('en-US', { weekday: 'long' });
        const holiday = isHolidayDate(date);
        
        // Check if today and past closing time
        const isPastClosingTime = isDatePastClosingTime(date, dayOfWeek);
        
        const isDisabled = date < today || !businessHours[dayOfWeek] || holiday || isPastClosingTime;
        
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        dayCell.textContent = day;
        
        if (isDisabled) {
            dayCell.classList.add('disabled');
            if (holiday) dayCell.classList.add('holiday');
        } else {
            dayCell.classList.add('available');
            dayCell.onclick = () => selectDate(date);
            
            if (selectedDate && 
                date.getDate() === selectedDate.getDate() && 
                date.getMonth() === selectedDate.getMonth() && 
                date.getFullYear() === selectedDate.getFullYear()) {
                dayCell.classList.add('selected');
            }
        }
        
        calendarGrid.appendChild(dayCell);
    }
}

// New function to check if date is past closing time
function isDatePastClosingTime(date, dayOfWeek) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const checkDate = new Date(date);
    checkDate.setHours(0, 0, 0, 0);
    
    // Only check if it's today
    if (checkDate.getTime() !== today.getTime()) {
        return false;
    }
    
    // Check if business hours exist for this day
    if (!businessHours[dayOfWeek]) {
        return true;
    }
    
    // Get closing time
    const closingTime = businessHours[dayOfWeek].close;
    if (!closingTime) {
        return true;
    }
    
    // Create closing datetime for today
    const now = new Date();
    const todayDateStr = formatDateLocal(now);
    const closingDateTime = new Date(todayDateStr + ' ' + closingTime);
    
    // If current time is past closing time, disable the date
    return now >= closingDateTime;
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

async function selectDate(date) {
    selectedDate = date;
    renderCalendar();
    
    const dateStr = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    document.getElementById('selectedDateDisplay').textContent = dateStr;
    document.getElementById('appointment_date').value = formatDateLocal(date);
    
    await loadTimeSlots(date);
}

async function loadTimeSlots(date) {
    const container = document.getElementById('timeSlotsContainer');
    container.innerHTML = '<p class="empty-state">Loading available times...</p>';
    
    if (!selectedStylistId) {
        container.innerHTML = '<p class="empty-state">Please select a stylist first</p>';
        return;
    }

    if (isHolidayDate(date)) { // added holiday check
        container.innerHTML = '<p class="empty-state">Salon is closed for a holiday on this date.</p>';
        document.getElementById('appointment_date').value = '';
        return;
    }
    
    try {
        const response = await fetch('get_available_slots.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                date: formatDateLocal(date),
                stylist_id: selectedStylistId,
                duration: totalServiceDuration || 60,
                service_ids: getSelectedServiceIds() // added service IDs
            })
        });
        
        const slots = await response.json();
        
        if (slots.length === 0) {
            container.innerHTML = '<p class="empty-state">No available time slots for this date</p>';
            return;
        }
        
        container.innerHTML = '';
        slots.forEach(slot => {
            const slotDiv = document.createElement('div');
            slotDiv.className = 'time-slot';
            if (slot.booked) slotDiv.classList.add('booked');
            
            // UPDATED: Display time in 12-hour format
            slotDiv.textContent = formatTime(slot.time);
            
            if (!slot.booked) {
                slotDiv.onclick = () => selectTimeSlot(slot.time, slotDiv);
            }
            
            container.appendChild(slotDiv);
        });
    } catch (error) {
        container.innerHTML = '<p class="empty-state">Error loading time slots</p>';
        console.error('Error:', error);
    }
}

function selectTimeSlot(time, element) {
    document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('appointment_time').value = time;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    showStep(1);
    renderCalendar();

    // Validate the final step on submit (hidden inputs won't be validated by HTML "required")
    const form = document.getElementById('appointmentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateStep(3)) {
                e.preventDefault();
                currentStep = 3;
                showStep(3);
            }
        });
    }
});
</script>

</body>
</html>
