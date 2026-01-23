<div class="p-4">
    <!-- Add Destination Form -->
    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
        <h3 class="text-sm font-medium text-gray-700 mb-3">Add New Destination</h3>
        <form method="POST" action="{{ route('admin.dispatchers.store') }}" class="add-destination-form" data-domain-id="{{ $domain->id }}" data-setid="{{ $domain->setid }}" onsubmit="return false;">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">SIP URI *</label>
                    <input type="text" 
                           name="destination" 
                           required
                           pattern="^sip:((\[([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\]|([0-9]{1,3}\.){3}[0-9]{1,3}|([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}))(:[0-9]{1,5})?$"
                           placeholder="sip:10.0.1.10:5060"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Weight</label>
                    <input type="number" 
                           name="weight" 
                           value="1"
                           min="0"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Priority</label>
                    <input type="number" 
                           name="priority" 
                           value="0"
                           min="0"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">State</label>
                    <select name="state" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                        <option value="0">Active</option>
                        <option value="1">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description *</label>
                    <input type="text" 
                           name="description" 
                           required
                           placeholder="PBX Description"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                </div>
            </div>
            <input type="hidden" name="setid" value="{{ $domain->setid }}">
            <div class="mt-3">
                <button type="button" class="add-destination-submit-btn inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Destination
                </button>
            </div>
        </form>
    </div>

    <!-- Destinations Table -->
    @if($destinations->isEmpty())
        <div class="text-center py-8 text-gray-500">
            <p class="mb-4">No destinations configured. Use the form above to add one.</p>
        </div>
    @else
        <div class="mb-4">
            <p class="text-sm text-gray-600">{{ $destinations->count() }} destination(s) configured</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 font-medium text-gray-700">Destination</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Weight</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Priority</th>
                        <th class="px-4 py-2 font-medium text-gray-700">State</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Description</th>
                        <th class="px-4 py-2 font-medium text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="destinations-tbody">
                    @foreach($destinations as $destination)
                        @include('filament.tables.destination-row', ['destination' => $destination, 'domain' => $domain, 'isEditMode' => false])
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
// Helper function for escaping HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    // Handle add destination form - attach directly to form when it exists
    function setupAddDestinationForm() {
        const form = document.querySelector('.add-destination-form');
        if (!form || form._handlerAttached) return;
        
        form._handlerAttached = true;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Add destination form submitted');
            const formData = new FormData(this);
            const setid = this.dataset.setid;
            
            // Validate required fields
            if (!formData.get('destination') || !formData.get('description')) {
                alert('Please fill in all required fields (SIP URI and Description)');
                return false;
            }

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.json().then(err => {
                        console.error('Response error:', err);
                        return Promise.reject(err);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Add destination response:', data);
                if (data.success && data.dispatcher) {
                    // Add the new row to the table without reloading
                    const tbody = document.getElementById('destinations-tbody');
                    if (tbody) {
                        // Create new row HTML
                        const newRow = document.createElement('tr');
                        newRow.className = 'hover:bg-gray-50 destination-row';
                        newRow.setAttribute('data-destination-id', data.dispatcher.id);
                        newRow.innerHTML = `
                            <td class="px-4 py-2 font-mono text-sm">${escapeHtml(data.dispatcher.destination)}</td>
                            <td class="px-4 py-2">${data.dispatcher.weight}</td>
                            <td class="px-4 py-2">${data.dispatcher.priority}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full ${data.dispatcher.state == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                    ${data.dispatcher.state == 0 ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-600">${escapeHtml(data.dispatcher.description || '-')}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <button type="button" 
                                            class="edit-destination-btn text-primary-600 hover:text-primary-900 text-xs font-medium underline bg-transparent border-0 p-0 cursor-pointer"
                                            data-destination-id="${data.dispatcher.id}">
                                        Edit
                                    </button>
                                    <span class="text-gray-400">|</span>
                                    <form method="POST" 
                                          action="/admin/dispatchers/${data.dispatcher.id}"
                                          class="inline delete-destination-form"
                                          data-destination-id="${data.dispatcher.id}">
                                        <input type="hidden" name="_token" value="${formData.get('_token')}">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" 
                                                class="text-danger-600 hover:text-danger-900 text-xs font-medium underline bg-transparent border-0 p-0 cursor-pointer">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(newRow);
                        
                        // Update count
                        const countElement = document.querySelector('.text-sm.text-gray-600');
                        if (countElement) {
                            const count = tbody.querySelectorAll('tr').length;
                            countElement.textContent = count + ' destination(s) configured';
                        }
                    }
                    
                    // Reset form
                    this.reset();
                    this.querySelector('input[name="setid"]').value = setid;
                    this.querySelector('input[name="weight"]').value = '1';
                    this.querySelector('input[name="priority"]').value = '0';
                    this.querySelector('select[name="state"]').value = '0';
                } else if (data.success) {
                    // Fallback: show message and suggest refresh
                    alert('Destination added successfully! Please close and reopen the modal to see it.');
                    this.reset();
                    this.querySelector('input[name="setid"]').value = setid;
                    this.querySelector('input[name="weight"]').value = '1';
                    this.querySelector('input[name="priority"]').value = '0';
                    this.querySelector('select[name="state"]').value = '0';
                } else {
                    alert('Failed to add destination: ' + (data.message || 'Unknown error'));
                }
                return false;
            })
            .catch(error => {
                console.error('Error adding destination:', error);
                const errorMsg = error.message || (error.error || 'Unknown error');
                alert('An error occurred while adding the destination: ' + errorMsg);
                return false;
            });
        });
    }
    
    // Setup form handler immediately and also after delays
    setupAddDestinationForm();
    setTimeout(setupAddDestinationForm, 100);
    setTimeout(setupAddDestinationForm, 500);
    setTimeout(setupAddDestinationForm, 1000);
    
    // Also handle submit button clicks directly
    document.addEventListener('click', function(e) {
        const submitBtn = e.target.closest('.add-destination-submit-btn');
        if (submitBtn) {
            e.preventDefault();
            e.stopPropagation();
            const form = submitBtn.closest('.add-destination-form');
            if (form) {
                // Trigger form submission handler
                const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                form.dispatchEvent(submitEvent);
            }
        }
    }, true);

    // Handle delete form submissions using event delegation
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('.delete-destination-form');
        if (!form) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        if (!confirm('Are you sure you want to delete this destination? This will reload OpenSIPS modules.')) {
            return;
        }

        const formData = new FormData(form);
        const row = form.closest('tr');

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        updateDestinationCount();
                    }, 300);
                } else {
                    // Fallback: reload the modal content
                    window.location.reload();
                }
            } else {
                alert('Failed to delete destination: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the destination');
        });
    }, true); // Use capture phase to catch before Livewire

    // Handle edit/save toggle using event delegation on document
    // This ensures it works even if modal content loads asynchronously
    if (!window._destinationEditHandlerAttached) {
        document.addEventListener('click', function(e) {
            // Find the button element (could be clicked directly or on child element)
            const editBtn = e.target.closest('.edit-destination-btn');
            const saveBtn = e.target.closest('.save-destination-btn');
            const cancelBtn = e.target.closest('.cancel-edit-btn');

            // Only process if we're inside the destinations modal
            const modal = e.target.closest('[role="dialog"], .fi-modal');
            if (!modal) return;

            // Handle Edit button
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                const row = editBtn.closest('tr');
                const destinationId = editBtn.dataset.destinationId;
                if (row && destinationId) {
                    console.log('Edit clicked for destination:', destinationId);
                    toggleEditMode(row, destinationId, true);
                }
                return false;
            }

            // Handle Save button
            if (saveBtn) {
                e.preventDefault();
                e.stopPropagation();
                const row = saveBtn.closest('tr');
                const destinationId = saveBtn.dataset.destinationId;
                if (row && destinationId) {
                    console.log('Save clicked for destination:', destinationId);
                    saveDestination(row, destinationId);
                }
                return false;
            }

            // Handle Cancel button
            if (cancelBtn) {
                e.preventDefault();
                e.stopPropagation();
                // Reload just the modal content instead of full page
                window.location.reload();
                return false;
            }
        }, true); // Use capture phase
        
        window._destinationEditHandlerAttached = true;
    }

    function toggleEditMode(row, destinationId, isEdit) {
        if (isEdit) {
            console.log('Toggle edit mode for destination:', destinationId);
            // Switch to edit mode - replace content with form
            const cells = row.querySelectorAll('td');
            if (cells.length < 6) {
                console.error('Not enough cells in row');
                return;
            }

            const originalData = {
                destination: cells[0].textContent.trim(),
                weight: cells[1].textContent.trim(),
                priority: cells[2].textContent.trim(),
                state: cells[3].querySelector('span')?.textContent.trim() === 'Active' ? '0' : '1',
                description: cells[4].textContent.trim() === '-' ? '' : cells[4].textContent.trim()
            };

            console.log('Original data:', originalData);

            // Store original data
            row.dataset.originalData = JSON.stringify(originalData);

            // Escape HTML for input values
            const escapeHtml = (text) => {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };

            // Replace cells with inputs
            cells[0].innerHTML = `<input type="text" name="destination" value="${escapeHtml(originalData.destination)}" class="w-full px-2 py-1 text-sm border rounded" required>`;
            cells[1].innerHTML = `<input type="number" name="weight" value="${escapeHtml(originalData.weight)}" class="w-full px-2 py-1 text-sm border rounded" min="0">`;
            cells[2].innerHTML = `<input type="number" name="priority" value="${escapeHtml(originalData.priority)}" class="w-full px-2 py-1 text-sm border rounded" min="0">`;
            cells[3].innerHTML = `<select name="state" class="w-full px-2 py-1 text-sm border rounded"><option value="0" ${originalData.state === '0' ? 'selected' : ''}>Active</option><option value="1" ${originalData.state === '1' ? 'selected' : ''}>Inactive</option></select>`;
            cells[4].innerHTML = `<input type="text" name="description" value="${escapeHtml(originalData.description)}" class="w-full px-2 py-1 text-sm border rounded">`;
            
            // Replace actions with Save/Cancel
            cells[5].innerHTML = `
                <div class="flex items-center gap-2">
                    <button type="button" class="save-destination-btn text-primary-600 hover:text-primary-900 text-xs font-medium underline cursor-pointer" data-destination-id="${destinationId}">Save</button>
                    <span class="text-gray-400">|</span>
                    <button type="button" class="cancel-edit-btn text-gray-600 hover:text-gray-900 text-xs font-medium underline cursor-pointer">Cancel</button>
                </div>
            `;
        }
    }

    function saveDestination(row, destinationId) {
        const destinationInput = row.querySelector('input[name="destination"]');
        const weightInput = row.querySelector('input[name="weight"]');
        const priorityInput = row.querySelector('input[name="priority"]');
        const stateSelect = row.querySelector('select[name="state"]');
        const descriptionInput = row.querySelector('input[name="description"]');

        if (!destinationInput || !weightInput || !priorityInput || !stateSelect || !descriptionInput) {
            alert('Error: Could not find all form fields');
            return;
        }

        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('_token', document.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content || '');
        formData.append('destination', destinationInput.value);
        formData.append('weight', weightInput.value);
        formData.append('priority', priorityInput.value);
        formData.append('state', stateSelect.value);
        formData.append('description', descriptionInput.value);

        fetch(`/admin/dispatchers/${destinationId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Reload to show updated data
            } else {
                alert('Failed to update destination: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the destination');
        });
    }

    function updateDestinationCount() {
        const countElement = document.querySelector('.text-sm.text-gray-600');
        if (countElement) {
            const tbody = document.getElementById('destinations-tbody');
            const count = tbody ? tbody.querySelectorAll('tr').length : 0;
            if (count === 0) {
                // Show empty state message
                const table = tbody?.closest('table');
                if (table) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.className = 'text-center py-8 text-gray-500';
                    emptyDiv.innerHTML = '<p class="mb-4">No destinations configured. Use the form above to add one.</p>';
                    table.parentNode.replaceChild(emptyDiv, table);
                }
            } else {
                countElement.textContent = count + ' destination(s) configured';
            }
        }
    }

});
</script>
