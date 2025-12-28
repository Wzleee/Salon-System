

// ===== Message Functions =====

function closeMessage() {
    const messageBox = document.getElementById('messageBox');
    if (messageBox) {
        messageBox.style.display = 'none';
    }
}

function showMessage(message, type = 'info') {
    const existingMessage = document.getElementById('messageBox');
    if (existingMessage) {
        existingMessage.remove();
    }

    const messageBox = document.createElement('div');
    messageBox.id = 'messageBox';
    messageBox.className = `message-container ${type}`;
    
    const iconClass = type === 'success' ? 'fa-check-circle' : 
                     type === 'error' ? 'fa-exclamation-circle' :
                     type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    messageBox.innerHTML = `
        <i class="fas ${iconClass}"></i>
        <span>${message}</span>
        <button onclick="closeMessage()">&times;</button>
    `;
    
    document.body.appendChild(messageBox);
    autoHideMessage();
}

// ===== Modal Functions =====

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}


// ===== Confirmation Functions =====

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

function confirmDelete(id, name = 'item') {
    const message = `Are you sure you want to delete "${name}"?`;
    confirmAction(message, () => {
        window.location.href = `?delete_id=${id}`;
    });
}

function confirmDeleteCategory(id, name = 'category') {
    const message = `Delete category "${name}"?\n\nYou must remove or move its services first.`;
    confirmAction(message, () => {
        window.location.href = `?delete_category_id=${id}`;
    });
}


// ===== Form Functions =====

function resetForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
    }
}



function openAddModal() {
    resetForm('addServiceForm');
    openModal('addModal');
}

function closeAddModal() {
    closeModal('addModal');
}

function openEditModal(service) {
    document.getElementById('edit_service_id').value = service.service_id;
    document.getElementById('edit_service_name').value = service.service_name;
    document.getElementById('edit_category_id').value = service.category_id;
    document.getElementById('edit_duration').value = service.duration_minutes;
    document.getElementById('edit_price').value = service.price;
    document.getElementById('edit_status').value = service.status;
    document.getElementById('edit_description').value = service.description || '';
    
    openModal('editModal');
}

function closeEditModal() {
    closeModal('editModal');
}

function confirmDeleteService(id, name) {
    const message = `Are you sure you want to delete "${name}"?\n\nNote: You cannot delete services with existing appointments.`;
    confirmAction(message, () => {
        window.location.href = `?delete_id=${id}`;
    });
}

function selectCategory(categoryId) {
    const tabs = document.querySelectorAll('.category-tab');
    const categories = document.querySelectorAll('.service-category');

    tabs.forEach(tab => {
        const tabCategory = tab.getAttribute('data-category');
        tab.classList.toggle('active', tabCategory === categoryId);
    });

    categories.forEach(cat => {
        const catId = cat.getAttribute('data-category-id');
        if (categoryId === 'all' || catId === categoryId) {
            cat.style.display = '';
        } else {
            cat.style.display = 'none';
        }
    });
}

function searchServices() {
    const input = document.getElementById('searchInput');
    if (!input) return;
    
    const filter = input.value.toUpperCase();
    const rows = document.querySelectorAll('.service-row');
    
    rows.forEach(row => {
        const text = row.textContent || row.innerText;
        if (text.toUpperCase().indexOf(filter) > -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterServicesByStatus() {
    const select = document.getElementById('statusFilter');
    if (!select) return;
    
    const status = select.value;
    const rows = document.querySelectorAll('.service-row');
    
    rows.forEach(row => {
        const statusBadge = row.querySelector('.status-badge');
        if (!statusBadge) return;
        
        const rowStatus = statusBadge.textContent.trim();
        
        if (status === 'all' || rowStatus.toLowerCase() === status.toLowerCase()) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

