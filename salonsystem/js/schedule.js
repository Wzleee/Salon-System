const listViewBtn = document.getElementById('listViewBtn');
const calendarGridBtn = document.getElementById('calendarGridBtn');
const datePickerTrigger = document.getElementById('datePickerTrigger');
const datePickerOverlay = document.getElementById('datePickerOverlay');
const datePickerPopup = document.getElementById('datePickerPopup');
const pickerCalendar = document.getElementById('pickerCalendar');
const pickerMonthYear = document.getElementById('pickerMonthYear');
const prevMonthBtn = document.getElementById('prevMonth');
const nextMonthBtn = document.getElementById('nextMonth');

const scheduleConfig = window.scheduleConfig || {};
const urlParams = new URLSearchParams(window.location.search);
const today = new Date();
const todayStr = formatDate(today);

const state = {
    currentDate: normalizeDate(urlParams.get('date')) || normalizeDate(scheduleConfig.currentDate) || todayStr,
    currentView: urlParams.get('view') || scheduleConfig.currentView || 'list',
};

state.month = normalizeMonth(urlParams.get('month') || scheduleConfig.month, state.currentDate);
state.year = normalizeYear(urlParams.get('year') || scheduleConfig.year, state.currentDate);
state.prevDate = normalizeDate(scheduleConfig.prevDate) || formatDate(addDays(state.currentDate, -1));
state.nextDate = normalizeDate(scheduleConfig.nextDate) || formatDate(addDays(state.currentDate, 1));

if (listViewBtn && calendarGridBtn) {
    listViewBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = '?date=' + encodeURIComponent(state.currentDate) + '&view=list';
    });

    calendarGridBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = '?date=' + encodeURIComponent(state.currentDate) +
                              '&view=calendar&month=' + state.month + '&year=' + state.year;
    });
}

document.addEventListener('keydown', function(e) {
    if (state.currentView === 'list') {
        if (e.key === 'ArrowLeft') {
            document.querySelector('.date-nav-btn[href*="date=' + encodeURIComponent(state.prevDate) + '"]')?.click();
        } else if (e.key === 'ArrowRight') {
            document.querySelector('.date-nav-btn[href*="date=' + encodeURIComponent(state.nextDate) + '"]')?.click();
        }
    }
});

let currentPickerDate = parseDateInput(state.currentDate) || today;
let pickerMonth = currentPickerDate.getMonth();
let pickerYear = currentPickerDate.getFullYear();

if (datePickerTrigger) {
    datePickerTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        datePickerOverlay?.classList.add('active');
        renderPickerCalendar();
    });
}

datePickerOverlay?.addEventListener('click', function(e) {
    if (e.target === datePickerOverlay) {
        datePickerOverlay.classList.remove('active');
    }
});

datePickerPopup?.addEventListener('click', function(e) {
    e.stopPropagation();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && datePickerOverlay?.classList.contains('active')) {
        datePickerOverlay.classList.remove('active');
    }
});

prevMonthBtn?.addEventListener('click', function() {
    pickerMonth--;
    if (pickerMonth < 0) {
        pickerMonth = 11;
        pickerYear--;
    }
    renderPickerCalendar();
});

nextMonthBtn?.addEventListener('click', function() {
    pickerMonth++;
    if (pickerMonth > 11) {
        pickerMonth = 0;
        pickerYear++;
    }
    renderPickerCalendar();
});

function renderPickerCalendar() {
    if (!pickerCalendar || !pickerMonthYear) return;

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
    
    pickerMonthYear.textContent = `${monthNames[pickerMonth]} ${pickerYear}`;
    
    pickerCalendar.innerHTML = '';

    const dayHeaders = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    dayHeaders.forEach(day => {
        const header = document.createElement('div');
        header.className = 'picker-day-header';
        header.textContent = day;
        pickerCalendar.appendChild(header);
    });
    
    const firstDay = new Date(pickerYear, pickerMonth, 1);
    const startingDayOfWeek = firstDay.getDay();
    
    const daysInMonth = new Date(pickerYear, pickerMonth + 1, 0).getDate();
    
    const prevMonthDays = new Date(pickerYear, pickerMonth, 0).getDate();
    
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
        const day = prevMonthDays - i;
        const dayDiv = document.createElement('div');
        dayDiv.className = 'picker-day other-month';
        dayDiv.textContent = day;
        pickerCalendar.appendChild(dayDiv);
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'picker-day';
        dayDiv.textContent = day;
        
        const dateStr = `${pickerYear}-${String(pickerMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        if (dateStr === todayStr) {
            dayDiv.classList.add('today');
        }
        
        if (dateStr === state.currentDate) {
            dayDiv.classList.add('selected');
        }
        
        dayDiv.addEventListener('click', function() {
            window.location.href = `?date=${dateStr}&view=${state.currentView}`;
        });
        
        pickerCalendar.appendChild(dayDiv);
    }
    
    const totalCells = pickerCalendar.children.length - 7; 
    const remainingCells = 42 - totalCells - 7; 
    for (let i = 1; i <= remainingCells; i++) {
        const dayDiv = document.createElement('div');
        dayDiv.className = 'picker-day other-month';
        dayDiv.textContent = i;
        pickerCalendar.appendChild(dayDiv);
    }
}

document.querySelectorAll('.stylist-avatar').forEach(img => {
    img.addEventListener('error', function() {
        this.style.display = 'none';
        const placeholder = this.nextElementSibling;
        if (placeholder) {
            placeholder.style.display = 'block';
        }
    });
});

// overlap list toggle for stacked appointments
document.querySelectorAll('.overlap-toggle').forEach(btn => {
    const list = btn.nextElementSibling;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (list) list.classList.toggle('show');
    });
});
document.addEventListener('click', function() {
    document.querySelectorAll('.overlap-list.show').forEach(el => el.classList.remove('show'));
});

function formatDate(dateObj) {
    const year = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function parseDateInput(value) {
    if (!value) return null;
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) return parsed;

    const parts = value.split('-').map(Number);
    if (parts.length === 3 && parts.every(num => Number.isFinite(num))) {
        const [y, m, d] = parts;
        const fallback = new Date(y, m - 1, d);
        if (!Number.isNaN(fallback.getTime())) return fallback;
    }
    return null;
}

function normalizeDate(value) {
    const parsed = parseDateInput(value);
    return parsed ? formatDate(parsed) : null;
}

function addDays(baseDateStr, days) {
    const base = parseDateInput(baseDateStr) || today;
    const clone = new Date(base.getTime());
    clone.setDate(clone.getDate() + days);
    return clone;
}

function normalizeMonth(value, fallbackDateStr) {
    const monthNum = parseInt(value, 10);
    if (!Number.isNaN(monthNum) && monthNum >= 1 && monthNum <= 12) return monthNum;
    const fallbackDate = parseDateInput(fallbackDateStr) || today;
    return fallbackDate.getMonth() + 1;
}

function normalizeYear(value, fallbackDateStr) {
    const yearNum = parseInt(value, 10);
    if (!Number.isNaN(yearNum) && yearNum >= 2000 && yearNum <= 2100) return yearNum;
    const fallbackDate = parseDateInput(fallbackDateStr) || today;
    return fallbackDate.getFullYear();
}


document.addEventListener('DOMContentLoaded', function() {
    
   
    const offDayCheckbox = document.getElementById('editOff');
    const timeFields = ['editStart', 'editEnd', 'editBreakStart', 'editBreakEnd'];
    
    if (offDayCheckbox) {
        offDayCheckbox.addEventListener('change', function() {
            const isOff = this.checked;
            
            timeFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.disabled = isOff;
                    field.style.opacity = isOff ? '0.5' : '1';
                    field.style.cursor = isOff ? 'not-allowed' : 'text';
                    
                    // Clear values when off
                    if (isOff) {
                        field.value = '';
                    }
                }
            });
            
            // Visual feedback
            timeFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const parent = field?.parentElement;
                if (parent) {
                    parent.style.opacity = isOff ? '0.5' : '1';
                }
            });
        });
        
        // Initialize on page load
        if (offDayCheckbox.checked) {
            offDayCheckbox.dispatchEvent(new Event('change'));
        }
    }
});