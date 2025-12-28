<?php
session_start();
require_once 'config.php';

$pageTitle = 'Cosmos Salon';
$pageCSS = 'css/index.css';
$useIndexNav = true; //Tell head.php to use an index-style navigation bar

// Fetch all active services
$stmt = $pdo->query("
    SELECT s.*, c.category_name 
    FROM service s 
    JOIN category c ON s.category_id = c.category_id 
    WHERE s.status = 'Active' 
    ORDER BY c.category_name, s.service_name
");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$serviceCategories = [];
$serviceCategoryKeys = [];
foreach ($services as $service) {
    $categoryName = trim($service['category_name'] ?? '');
    if ($categoryName === '') {
        continue;
    }
    $categoryName = preg_replace('/\s+/', ' ', $categoryName);
    $categoryKey = strtolower($categoryName);
    if (!isset($serviceCategoryKeys[$categoryKey])) {
        $serviceCategoryKeys[$categoryKey] = true;
        $serviceCategories[] = $categoryName;
    }
}

// Group services by category for pricing section
$stmt = $pdo->query("
    SELECT c.category_id, c.category_name, 
           s.service_id, s.service_name, s.duration_minutes, s.price
    FROM category c
    LEFT JOIN service s ON c.category_id = s.category_id AND s.status = 'Active'
    WHERE c.status = 'Active'
    ORDER BY c.category_name, s.service_name
");
$categorizedServices = [];
$categorizedCategoryLabels = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categoryName = trim($row['category_name'] ?? '');
    if ($categoryName === '') {
        continue;
    }
    $categoryName = preg_replace('/\s+/', ' ', $categoryName);
    $categoryKey = strtolower($categoryName);
    if (!isset($categorizedCategoryLabels[$categoryKey])) {
        $categorizedCategoryLabels[$categoryKey] = $categoryName;
        $categorizedServices[$categorizedCategoryLabels[$categoryKey]] = [];
    }
    if ($row['service_id']) {
        $categorizedServices[$categorizedCategoryLabels[$categoryKey]][] = $row;
    }
}

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
    $stylists = [];
}

// Include head.php (it will display the correct navigation bar based on $useIndexNav).
require_once 'head.php';
include 'flash_message.php';
?>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="lotus-icon">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M30 10C25 15 20 25 20 35C20 42 24 48 30 50C36 48 40 42 40 35C40 25 35 15 30 10Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                    <path d="M15 30C12 35 10 40 15 45C20 48 25 45 27 40C28 35 27 30 25 25C22 22 17 25 15 30Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                    <path d="M45 30C48 35 50 40 45 45C40 48 35 45 33 40C32 35 33 30 35 25C38 22 43 25 45 30Z" stroke="#9333ea" stroke-width="1.5" fill="none"/>
                    <circle cx="30" cy="15" r="1.5" fill="#9333ea"/>
                    <circle cx="30" cy="20" r="1" fill="#9333ea"/>
                </svg>
            </div>
            <div class="divider-line"></div>
            <h1>HAIR & BEAUTY SALON</h1>
            <p class="hero-subtitle">Transform your look and rejuvenate your spirit at Cosmos Salon.<br>Where professional expertise meets personalized care.</p>
            <div class="hero-buttons">
                <a href="appointment/appointment.php" class="btn-filled">BOOK APPOINTMENT</a>
                <a href="#contact" class="btn-outline">CONTACT US</a>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="about-container">
            <div class="about-content">
                <div class="about-text">
                    <span class="section-label">ABOUT US</span>
                    <h2>Welcome to Cosmos Salon</h2>
                    <p class="about-intro">Your premier destination for exceptional hair and beauty services in Selangor.</p>
                    <p>At Cosmos Salon, we believe that beauty is an art form. Our team of experienced professionals is dedicated to bringing out your natural beauty through personalized care and cutting-edge techniques.</p>
                    <p>With years of expertise in hair styling, coloring, treatments, and beauty services, we create a relaxing atmosphere where you can unwind while we work our magic. Every client is unique, and we take pride in crafting looks that reflect your individual style and personality.</p>
                    <div class="about-features">
                        <div class="feature-item">
                            <i class="fas fa-award"></i>
                            <h4>Expert Stylists</h4>
                            <p>Certified professionals with years of experience</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-star"></i>
                            <h4>Premium Products</h4>
                            <p>We use only high-quality, trusted brands</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-heart"></i>
                            <h4>Personalized Care</h4>
                            <p>Tailored services to meet your unique needs</p>
                        </div>
                    </div>
                </div>
                <div class="about-image">
                    <img src="images/salon.jpeg" alt="Cosmos Salon Interior">
                    <div class="about-stats">
                        <div class="stat-item">
                            <h3>8+</h3>
                            <p>Years Experience</p>
                        </div>
                        <div class="stat-item">
                            <h3>1000+</h3>
                            <p>Happy Clients</p>
                        </div>
                        <div class="stat-item">
                            <h3><?php echo count($services); ?>+</h3>
                            <p>Services Offered</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services/Treatments Section -->
    <section id="treatments" class="treatments-section">
        <div class="section-header">
            <span class="section-label">OUR SERVICES</span>
            <h2>Professional Treatments</h2>
        </div>

        <?php
            function slugifyCategory($text) {
                $text = strtolower($text ?? '');
                $text = preg_replace('/[^a-z0-9]+/', '-', $text);
                return trim($text, '-');
            }
        ?>

        <div class="treatments-controls">
            <div class="filter-chips" role="tablist" aria-label="Service categories">
                <button class="filter-chip active" data-filter="all" aria-pressed="true">All</button>
                <?php foreach ($serviceCategories as $categoryName): 
                    $catSlug = slugifyCategory($categoryName);
                ?>
                    <button class="filter-chip" data-filter="<?php echo $catSlug; ?>" aria-pressed="false">
                        <?php echo htmlspecialchars($categoryName); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="treatments-search">
                <i class="fas fa-search"></i>
                <input type="search" id="serviceSearch" placeholder="Search treatments..." aria-label="Search services">
            </div>
        </div>
        
        <div class="treatments-grid">
            <?php 
            $serviceIcons = [
                'haircut' => 'fas fa-cut',
                'hair cut' => 'fas fa-cut',
                'basic haircut' => 'fas fa-cut',
                'coloring' => 'fas fa-palette',
                'hair coloring' => 'fas fa-palette',
                'color' => 'fas fa-palette',
                'highlights' => 'fas fa-brush',
                'treatment' => 'fas fa-spa',
                'hair treatment' => 'fas fa-spa',
                'styling' => 'fas fa-magic',
                'hair styling' => 'fas fa-magic',
                'perm' => 'fas fa-wind',
                'rebonding' => 'fas fa-align-justify',
                'facial' => 'fas fa-smile',
                'massage' => 'fas fa-hand-sparkles',
                'manicure' => 'fas fa-hand-holding-heart',
                'pedicure' => 'fas fa-shoe-prints',
                'nail' => 'fas fa-hand-paper',
                'makeup' => 'fas fa-paint-brush',
                'waxing' => 'fas fa-fire',
            ];
            
            function getServiceIcon($serviceName, $categoryName, $iconMap) {
                $searchTerms = strtolower($serviceName . ' ' . $categoryName);
                foreach ($iconMap as $keyword => $icon) {
                    if (strpos($searchTerms, $keyword) !== false) {
                        return $icon;
                    }
                }
                return 'fas fa-star';
            }
            
            $serviceIndex = 0;
            foreach ($services as $service): 
                $icon = getServiceIcon($service['service_name'], $service['category_name'], $serviceIcons);
                $catSlug = slugifyCategory($service['category_name']);
                $keywords = strtolower(
                    trim(
                        ($service['service_name'] ?? '') . ' ' .
                        ($service['category_name'] ?? '') . ' ' .
                        ($service['description'] ?? '')
                    )
                );
                $isCollapsed = $serviceIndex >= 8 ? ' is-collapsed' : '';
            ?>
                <div class="treatment-card<?php echo $isCollapsed; ?>"
                     data-category="<?php echo $catSlug; ?>"
                     data-keywords="<?php echo htmlspecialchars($keywords, ENT_QUOTES); ?>">
                    <div class="treatment-icon">
                        <i class="<?php echo $icon; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                    <p class="treatment-description"><?php echo htmlspecialchars($service['description'] ?: 'Professional service for your beauty needs.'); ?></p>
                </div>
            <?php 
                $serviceIndex++;
            endforeach; ?>
        </div>

        <div id="noServicesMessage" class="treatments-empty" style="display: none;">
            <i class="far fa-frown"></i>
            <p>No services match your filters yet. Try a different category or keyword.</p>
        </div>

        <div class="treatments-footer">
            <button class="show-more-btn" id="showMoreServices" aria-expanded="false">
                Show more services
            </button>
            <p class="treatments-hint">Start with our top picks above, then expand when you want to browse everything.</p>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section">
        <div class="pricing-container">
            <div class="pricing-box">
                <h3>Service Pricing</h3>
                <p class="pricing-subtitle">Explore our comprehensive range of professional beauty services<br>with transparent and competitive pricing</p>
                
                <?php 
                foreach ($categorizedServices as $categoryName => $categoryServices): 
                    if (empty($categoryServices)) continue;
                ?>
                    <div class="pricing-category">
                        <div class="category-header" onclick="toggleCategory(this)">
                            <h4><?php echo htmlspecialchars($categoryName); ?></h4>
                            <button class="toggle-btn">+</button>
                        </div>
                        <div class="pricing-items" style="display: none;">
                            <?php foreach ($categoryServices as $service): ?>
                                <div class="pricing-item">
                                    <span class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                    <span class="service-duration"><?php echo $service['duration_minutes']; ?> min.</span>
                                    <span class="service-price">RM<?php echo number_format($service['price'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php 
                endforeach; 
                ?>
            </div>
        </div>
    </section>

    <!-- Availability Checker Section -->
    <section id="availability" class="availability-section">
        <div class="availability-container">
            <div class="section-header">
                <span class="section-label">REAL-TIME AVAILABILITY</span>
                <h2>Check Available Time Slots</h2>
                <p class="section-subtitle">See what times are available before booking your appointment</p>
            </div>

            <div class="availability-content">
                <div class="availability-card">
                    <div class="card-header">
                        <i class="fas fa-user-tie"></i>
                        <h3>Select a Stylist</h3>
                    </div>
                    <div class="stylists-selection">
                        <?php if (!empty($stylists)): ?>
                            <?php foreach ($stylists as $stylist): ?>
                                <label class="stylist-radio">
                                    <input type="radio" 
                                           name="availability_stylist" 
                                           value="<?php echo $stylist['stylist_id']; ?>"
                                           onchange="selectStylistForAvailability(<?php echo $stylist['stylist_id']; ?>)">
                                    <div class="stylist-option-card">
                                        <div class="stylist-icon">
                                            <img src="<?php echo htmlspecialchars(!empty($stylist['photo']) ? $stylist['photo'] : '/salonsystem/images/default-pic.jpg'); ?>"
                                                 alt="<?php echo htmlspecialchars($stylist['stylist_name']); ?>"
                                                 onerror="this.onerror=null; this.src='/salonsystem/images/default-pic.jpg';">
                                        </div>
                                        <div class="stylist-info">
                                            <h4><?php echo htmlspecialchars($stylist['stylist_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($stylist['specialization'] ?? 'Professional Stylist'); ?></p>
                                            <span class="experience"><?php echo $stylist['experience_years']; ?> years exp.</span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-message" style="grid-column: 1 / -1;">
                                <i class="fas fa-users-slash"></i>
                                No stylists available.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="availability-card" id="dateTimeCard" style="display: none;">
                    <div class="card-header">
                        <i class="fas fa-calendar-clock"></i>
                        <h3>Select Date & Time</h3>
                    </div>
                    
                    <div class="datetime-wrapper">
                        <div class="calendar-section">
                            <div class="mini-calendar">
                                <div class="calendar-controls">
                                    <button type="button" class="btn-calendar-nav" onclick="changeAvailabilityMonth(-1)">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <h4 id="availabilityMonth"></h4>
                                    <button type="button" class="btn-calendar-nav" onclick="changeAvailabilityMonth(1)">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                <div class="calendar-weekdays">
                                    <div>Sun</div>
                                    <div>Mon</div>
                                    <div>Tue</div>
                                    <div>Wed</div>
                                    <div>Thu</div>
                                    <div>Fri</div>
                                    <div>Sat</div>
                                </div>
                                <div class="calendar-days" id="availabilityCalendar"></div>
                            </div>
                        </div>

                        <div class="timeslots-section">
                            <div class="timeslots-header">
                                <h4 id="availabilitySelectedDate">Select a date to view times</h4>
                                <p class="availability-note">
                                    <i class="fas fa-info-circle"></i>
                                    Showing available time slots. Duration will be calculated based on your selected services.
                                </p>
                            </div>

                            <div class="available-slots" id="availableTimesGrid">
                                <p class="empty-message">
                                    <i class="far fa-calendar"></i>
                                    Select a date to view available time slots
                                </p>
                            </div>

                            <div class="availability-actions" id="bookingAction" style="display: none;">
                                <a href="appointment/appointment.php" class="btn-book-now">
                                    <i class="fas fa-calendar-check"></i>
                                    Book Appointment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="availability-legend">
                <div class="legend-item">
                    <span class="legend-color available"></span>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color booked"></span>
                    <span>Booked</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color selected"></span>
                    <span>Selected</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="contact-container">
            <div class="section-header">
                <span class="section-label">GET IN TOUCH</span>
                <h2>Contact Us</h2>
                <p class="section-subtitle">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
            </div>

            <div class="contact-content">
                <div class="contact-form-wrapper">
                    <form class="contact-form" id="contactForm" onsubmit="handleFormSubmit(event)">
                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" required placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" required placeholder="+60 12-345 6789">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required placeholder="How can we help you?">
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="5" required placeholder="Tell us more about your inquiry..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">SEND MESSAGE</button>

                        <div id="successMessage" style="display: none; background: #d1fae5; color: #065f46; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid #10b981;">
                            <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>
                            <strong>Thank you for contacting us!</strong>
                            <p style="margin-top: 0.5rem; margin-bottom: 0;">We've received your message and will get back to you as soon as possible.</p>
                        </div>
                    </form>
                </div>

                <div class="contact-info-wrapper">
                    <div class="contact-info-card">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-text">
                                <h4>Our Location</h4>
                                <p>Setapak Central Mall<br>Jalan Taman Ibu Kota, Taman Danau Kota<br>53300 Kuala Lumpur, Malaysia</p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-text">
                                <h4>Phone Number</h4>
                                <p><a href="tel:+60123456789">+60 12-345 6789</a></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-text">
                                <h4>Email Address</h4>
                                <p><a href="mailto:info@cosmossalon.com">info@cosmossalon.com</a></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="info-text">
                                <h4>Business Hours</h4>
                                <?php
                                $hours = $pdo->query("SELECT * FROM businesshours ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
                                while($hour = $hours->fetch(PDO::FETCH_ASSOC)):
                                    if($hour['is_closed']):
                                ?>
                                    <p><?php echo $hour['day_of_week']; ?>: <span style="color: #9333ea;">Closed</span></p>
                                <?php else: ?>
                                    <p><?php echo $hour['day_of_week']; ?>: <?php echo date('g:i A', strtotime($hour['opening_time'])) . ' - ' . date('g:i A', strtotime($hour['closing_time'])); ?></p>
                                <?php endif; endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <div class="map-wrapper">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.5789498186145!2d101.72030699999999!3d3.2047051!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc3811901a58df%3A0x87e279dc56f9b1c0!2sT%26T%20Boutique%20Salon%20%40%20Setapak%20Central!5e0!3m2!1sen!2smy!4v1764883567998!5m2!1sen!2smy" 
                            width="100%" 
                            height="300" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3>Cosmos Salon</h3>
                <p>Your trusted destination for premium hair and beauty services. We combine expert craftsmanship with personalized attention to bring out your best look.</p>
            </div>
            
            <div class="footer-column">
                <h4>Popular Services</h4>
                <ul>
                    <?php 
                    $popularServices = array_slice($services, 0, 4);
                    foreach ($popularServices as $service): 
                    ?>
                        <li><a href="#treatments"><?php echo htmlspecialchars($service['service_name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="footer-column">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#treatments">Our Services</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="appointment/appointment.php">Book Appointment</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h4>Contact Information</h4>
                <ul class="contact-info">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Setapak Central Mall, Jalan Taman Ibu Kota, 53300 Kuala Lumpur</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span>+60 12-345 6789</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>info@cosmossalon.com</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>Copyright ©2025 All rights reserved | Cosmos Salon - Your Beauty Destination</p>
        </div>
    </footer>

    <script>
        function toggleCategory(header) {
            const items = header.nextElementSibling;
            const btn = header.querySelector('.toggle-btn');
            
            if (items.style.display === 'none' || items.style.display === '') {
                items.style.display = 'block';
                btn.textContent = '-';
            } else {
                items.style.display = 'none';
                btn.textContent = '+';
            }
        }

        function initServiceFilters() {
            const filterButtons = document.querySelectorAll('.filter-chip');
            const searchInput = document.getElementById('serviceSearch');
            const showMoreBtn = document.getElementById('showMoreServices');
            const cards = Array.from(document.querySelectorAll('.treatment-card'));
            const emptyState = document.getElementById('noServicesMessage');

            if (!cards.length) return;

            const visibleLimit = 8;
            let expanded = false;

            function applyFilters() {
                const activeFilter = document.querySelector('.filter-chip.active')?.dataset.filter || 'all';
                const query = (searchInput?.value || '').toLowerCase().trim();
                let shown = 0;

                cards.forEach(card => {
                    const matchesFilter = activeFilter === 'all' || card.dataset.category === activeFilter;
                    const keywords = card.dataset.keywords || '';
                    const matchesSearch = !query || keywords.indexOf(query) !== -1;
                    const shouldShow = matchesFilter && matchesSearch;

                    card.classList.toggle('is-filtered-out', !shouldShow);

                    if (shouldShow) {
                        const overLimit = !expanded && shown >= visibleLimit;
                        card.classList.toggle('is-collapsed', overLimit);
                        if (!overLimit) {
                            shown++;
                        }
                    } else {
                        card.classList.add('is-collapsed');
                    }
                });

                const visibleCards = cards.filter(card => !card.classList.contains('is-filtered-out'));
                const anyVisible = visibleCards.length > 0;

                if (emptyState) {
                    emptyState.style.display = anyVisible ? 'none' : 'block';
                }

                if (showMoreBtn) {
                    if (!anyVisible) {
                        showMoreBtn.style.display = 'none';
                    } else if (visibleCards.length > visibleLimit) {
                        showMoreBtn.style.display = 'inline-flex';
                        showMoreBtn.textContent = expanded ? 'Show less' : 'Show more services';
                        showMoreBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    } else {
                        showMoreBtn.style.display = 'none';
                    }
                }
            }

            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterButtons.forEach(b => {
                        b.classList.remove('active');
                        b.setAttribute('aria-pressed', 'false');
                    });
                    btn.classList.add('active');
                    btn.setAttribute('aria-pressed', 'true');
                    expanded = false;
                    applyFilters();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    expanded = false;
                    applyFilters();
                });
            }

            if (showMoreBtn) {
                showMoreBtn.addEventListener('click', () => {
                    expanded = !expanded;
                    applyFilters();
                });
            }

            applyFilters();
        }

        function handleFormSubmit(event) {
            event.preventDefault();
            const form = document.getElementById('contactForm');
            const successMessage = document.getElementById('successMessage');
            successMessage.style.display = 'block';
            form.reset();
            successMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function() {
                successMessage.style.display = 'none';
            }, 5000);
        }

        // Intersection Observer for Fade In Animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.addEventListener('DOMContentLoaded', function() {
            const sectionHeaders = document.querySelectorAll('.section-header');
            sectionHeaders.forEach(header => observer.observe(header));
            
            const treatmentCards = document.querySelectorAll('.treatment-card');
            treatmentCards.forEach(card => observer.observe(card));
            
            const aboutText = document.querySelector('.about-text');
            const aboutImage = document.querySelector('.about-image');
            if (aboutText) observer.observe(aboutText);
            if (aboutImage) observer.observe(aboutImage);
            
            const pricingBox = document.querySelector('.pricing-box');
            if (pricingBox) observer.observe(pricingBox);
            
            const contactFormWrapper = document.querySelector('.contact-form-wrapper');
            const contactInfoWrapper = document.querySelector('.contact-info-wrapper');
            if (contactFormWrapper) observer.observe(contactFormWrapper);
            if (contactInfoWrapper) observer.observe(contactInfoWrapper);
        });

        document.addEventListener('DOMContentLoaded', initServiceFilters);

        // Smooth Scroll for Navigation Links
        document.querySelectorAll('.nav-links a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                if (href.startsWith('#')) {
                    e.preventDefault();
                    
                    if (href === '#') {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                        
                        document.querySelectorAll('.nav-links a').forEach(link => {
                            link.classList.remove('active');
                        });
                        this.classList.add('active');
                        return;
                    }
                    
                    const targetId = href.substring(1);
                    const targetElement = document.getElementById(targetId);

                    if (targetElement) {
                        const navHeight = document.querySelector('nav').offsetHeight;
                        const targetPosition = targetElement.offsetTop - navHeight;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                        
                        document.querySelectorAll('.nav-links a').forEach(link => {
                            link.classList.remove('active');
                        });
                        this.classList.add('active');
                    }
                }
            });
        });

        // Update active nav link on scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            const navHeight = document.querySelector('nav').offsetHeight;
            
            let currentSection = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop - navHeight - 100;
                const sectionHeight = section.offsetHeight;
                
                if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                    currentSection = section.getAttribute('id');
                }
            });
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + currentSection) {
                    link.classList.add('active');
                }
            });
        });

        // Logo click - scroll to top
        document.querySelector('.logo-section a').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            
            document.querySelectorAll('.nav-links a').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector('.nav-links a[href="#"]').classList.add('active');
        });

        // Availability Checker Variables
        let availabilityStylistId = null;
        let availabilityBusinessHours = {};
        let availabilityHolidays = [];
        let availabilityCurrentDate = new Date();
        let availabilitySelectedDate = null;

        function resetAvailabilitySelection() {
            availabilitySelectedDate = null;
            availabilityCurrentDate = new Date();
            availabilityBusinessHours = {};
            availabilityHolidays = [];
            const container = document.getElementById('availableTimesGrid');
            if (container) {
                container.innerHTML = '<p class="empty-message"><i class="far fa-calendar"></i> Select a date to view available time slots</p>';
            }
            const selectedDateLabel = document.getElementById('availabilitySelectedDate');
            if (selectedDateLabel) {
                selectedDateLabel.innerHTML = '<i class="far fa-calendar-check"></i> Select a date to view times';
            }
            const bookingAction = document.getElementById('bookingAction');
            if (bookingAction) {
                bookingAction.style.display = 'none';
            }
            renderAvailabilityCalendar();
        }

        function selectStylistForAvailability(stylistId) {
            resetAvailabilitySelection();
            availabilityStylistId = stylistId;
            fetchAvailabilityBusinessHours(stylistId);
            document.getElementById('dateTimeCard').style.display = 'block';
            
            document.getElementById('dateTimeCard').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'nearest' 
            });
        }

        async function fetchAvailabilityBusinessHours(stylistId) {
            try {
                const response = await fetch('appointment/get_business_hours.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ stylist_id: stylistId })
                });
                
                const hours = await response.json();
                if (!hours.error) {
                    availabilityBusinessHours = hours.business_hours || hours;
                    availabilityHolidays = hours.holidays || [];
                    renderAvailabilityCalendar();
                }
            } catch (error) {
                console.error('Error fetching business hours:', error);
            }
        }

        function formatAvailabilityDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function isAvailabilityHoliday(date) {
            const dateStr = formatAvailabilityDate(date);
            const monthDay = dateStr.slice(5);
            return availabilityHolidays.some(h => (h.is_recurring && h.month_day === monthDay) || (!h.is_recurring && h.date === dateStr));
        }

        function renderAvailabilityCalendar() {
            const year = availabilityCurrentDate.getFullYear();
            const month = availabilityCurrentDate.getMonth();
            
            document.getElementById('availabilityMonth').textContent = 
                availabilityCurrentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startingDayOfWeek = firstDay.getDay();
            
            const calendar = document.getElementById('availabilityCalendar');
            calendar.innerHTML = '';
            
            for (let i = 0; i < startingDayOfWeek; i++) {
                const empty = document.createElement('div');
                empty.className = 'calendar-day empty';
                calendar.appendChild(empty);
            }
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const date = new Date(year, month, day);
                const dayOfWeek = date.toLocaleString('en-US', { weekday: 'long' });
                const holiday = isAvailabilityHoliday(date);
                const isDisabled = date < today || !availabilityBusinessHours[dayOfWeek] || holiday;
                
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.textContent = day;
                
                if (isDisabled) {
                    dayCell.classList.add('disabled');
                    if (holiday) dayCell.classList.add('holiday');
                } else {
                    dayCell.classList.add('available');
                    dayCell.onclick = () => selectAvailabilityDate(date);
                    
                    if (availabilitySelectedDate && 
                        date.getDate() === availabilitySelectedDate.getDate() && 
                        date.getMonth() === availabilitySelectedDate.getMonth() && 
                        date.getFullYear() === availabilitySelectedDate.getFullYear()) {
                        dayCell.classList.add('selected');
                    }
                }
                
                calendar.appendChild(dayCell);
            }
        }

        function changeAvailabilityMonth(delta) {
            availabilityCurrentDate.setMonth(availabilityCurrentDate.getMonth() + delta);
            renderAvailabilityCalendar();
        }

        async function selectAvailabilityDate(date) {
            availabilitySelectedDate = date;
            renderAvailabilityCalendar();
            
            const dateStr = date.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric',
                year: 'numeric'
            });
            document.getElementById('availabilitySelectedDate').innerHTML = `<i class="far fa-calendar-check"></i> ${dateStr}`;
            
            await loadAvailableTimeSlots(date);
        }

        async function loadAvailableTimeSlots(date) {
            const container = document.getElementById('availableTimesGrid');
            container.innerHTML = '<p class="loading-message"><i class="fas fa-spinner fa-spin"></i> Loading available times...</p>';
            
            const duration = 60;

            if (isAvailabilityHoliday(date)) {
                container.innerHTML = '<p class="empty-message"><i class="fas fa-calendar-times"></i> Closed for a holiday on this date</p>';
                document.getElementById('bookingAction').style.display = 'none';
                return;
            }
            
            try {
                const response = await fetch('appointment/get_available_slots.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        date: formatAvailabilityDate(date),
                        stylist_id: availabilityStylistId,
                        duration: duration
                    })
                });
                
                const slots = await response.json();
                
                if (slots.length === 0) {
                    container.innerHTML = '<p class="empty-message"><i class="fas fa-calendar-times"></i> No available time slots for this date</p>';
                    document.getElementById('bookingAction').style.display = 'none';
                    return;
                }
                
                container.innerHTML = '';
                document.getElementById('bookingAction').style.display = 'block';
                
                const morning = [], afternoon = [], evening = [];
                
                slots.forEach(slot => {
                    const hour = parseInt(slot.time.split(':')[0]);
                    if (hour < 12) morning.push(slot);
                    else if (hour < 17) afternoon.push(slot);
                    else evening.push(slot);
                });
                
                if (morning.length > 0) {
                    container.innerHTML += '<div class="time-period-label"><i class="fas fa-sun"></i> Morning</div>';
                    morning.forEach(slot => container.appendChild(createTimeSlot(slot)));
                }
                
                if (afternoon.length > 0) {
                    container.innerHTML += '<div class="time-period-label"><i class="fas fa-cloud-sun"></i> Afternoon</div>';
                    afternoon.forEach(slot => container.appendChild(createTimeSlot(slot)));
                }
                
                if (evening.length > 0) {
                    container.innerHTML += '<div class="time-period-label"><i class="fas fa-moon"></i> Evening</div>';
                    evening.forEach(slot => container.appendChild(createTimeSlot(slot)));
                }
                
            } catch (error) {
                container.innerHTML = '<p class="error-message"><i class="fas fa-exclamation-circle"></i> Error loading time slots</p>';
                console.error('Error:', error);
            }
        }

        function createTimeSlot(slot) {
            const slotDiv = document.createElement('div');
            slotDiv.className = 'time-slot-item';
            
            if (slot.booked) {
                slotDiv.classList.add('booked');
                slotDiv.innerHTML = `
                    <span class="time">${formatTime(slot.time)}</span>
                    <span class="status">Booked</span>
                `;
            } else {
                slotDiv.classList.add('available');
                slotDiv.innerHTML = `
                    <span class="time">${formatTime(slot.time)}</span>
                    <span class="status"><i class="fas fa-check-circle"></i> Available</span>
                `;
            }
            
            return slotDiv;
        }

        function formatTime(time) {
            const [hours, minutes] = time.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
            return `${displayHour}:${minutes} ${ampm}`;
        }
    </script>
</body>
</html>
