class SalesManager {
    constructor() {
        this.currentEditing = null;
        this.originalValue = null;
        this.allData = [];
        this.isSaving = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadData();
    }

    bindEvents() {
        // Add new row - FIXED: Only bind to the specific "rowအသစ်ထပ်ယူရန်" button
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-primary') && 
                !e.target.closest('form') && 
                e.target.closest('.btn-primary').textContent.includes('rowအသစ်ထပ်ယူရန်')) {
                this.addNewRow();
            }
        });
        
        // Save all data
        document.addEventListener('click', (e) => {
            if (e.target.closest('#saveAllData')) {
                this.saveAllData();
            }
        });
        
        // Delete all data
        document.addEventListener('click', (e) => {
            if (e.target.closest('#deleteAllData')) {
                this.deleteAllData();
            }
        });
        
        // Search functionality
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-search')) {
                this.searchData();
            }
        });
        
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-clear')) {
                this.clearSearch();
            }
        });
        
        // Table event delegation for double-click editing
        const salesTableBody = document.getElementById('salesTableBody');
        if (salesTableBody) {
            salesTableBody.addEventListener('dblclick', (e) => {
                const cell = e.target.closest('td');
                if (cell && cell.hasAttribute('data-field') && !cell.querySelector('input')) {
                    this.startEditing(cell);
                }
            });
        }
    
        // Header double-click editing
        const thead = document.querySelector('thead');
        if (thead) {
            thead.addEventListener('dblclick', (e) => {
                const header = e.target.closest('.editable-header');
                if (header && !header.querySelector('input') && !header.querySelector('select')) {
                    this.startHeaderEditing(header);
                }
            });
        }
        
        // Event delegation for ALL table buttons
        if (salesTableBody) {
            salesTableBody.addEventListener('click', (e) => {
                const saveBtn = e.target.closest('.save-btn');
                const deleteBtn = e.target.closest('.delete-btn');
                const editBtn = e.target.closest('.btn-edit');
                const deleteRowBtn = e.target.closest('.btn-delete');
                
                if (saveBtn) {
                    this.saveRow(saveBtn);
                }
                if (deleteBtn) {
                    this.deleteRow(deleteBtn);
                }
                if (editBtn) {
                    this.editRow(editBtn);
                }
                if (deleteRowBtn) {
                    const rowId = deleteRowBtn.getAttribute('data-id');
                    this.deleteRowById(rowId);
                }
            });
        }
    }

    editRow(button) {
        const row = button.closest('tr');
        // Find the first editable cell and start editing
        const firstEditableCell = row.querySelector('td[data-field]');
        if (firstEditableCell) {
            this.startEditing(firstEditableCell);
        }
    }

    deleteRowById(rowId) {
        if (confirm('ဤအချက်အလက်ကိုဖျက်မှာသေချာပါသလား?')) {
            this.deleteFromServer(rowId)
                .then(() => {
                    const row = document.querySelector(`tr[data-id="${rowId}"]`);
                    if (row) {
                        row.remove();
                        this.calculateCumulativeTotals();
                    }
                    alert('ဖျက်ပြီးပါပြီ');
                })
                .catch(error => {
                    alert('ဖျက်ရာတွင်အမှားတစ်ခုဖြစ်သည်');
                    console.error('Error:', error);
                });
        }
    }

    calculateRowTotals(row) {
        // Calculate cumulative totals
        this.calculateCumulativeTotals();
        
        // Get input values
        const totalWeight = parseFloat(row.querySelector('[data-field="total_weight"]').textContent) || 0;
        const totalSoldCount = parseFloat(row.querySelector('[data-field="total_sold_count"]').textContent) || 0;
        const deadCount = parseFloat(row.querySelector('[data-field="dead_count"]').textContent) || 0;
        const initialCount = parseInt(document.querySelector('[data-field="initial_count"]').textContent) || 4080;
        const cumulativeSoldCount = parseFloat(row.querySelector('[data-field="cumulative_sold_count"]').textContent) || 0;
        
        const weight21to30 = parseFloat(row.querySelector('[data-field="weight_21to30"]').textContent) || 0;
        const weight31to36 = parseFloat(row.querySelector('[data-field="weight_31to36"]').textContent) || 0;
        const weight37toEnd = parseFloat(row.querySelector('[data-field="weight_37to_end"]').textContent) || 0;
        
        const totalChickenWeight = parseFloat(row.querySelector('[data-field="total_chicken_weight"]').textContent) || 0;
        const totalFeedConsumptionRate = parseFloat(row.querySelector('[data-field="total_feed_consumption_rate"]').textContent) || 0;
        const currentCount = parseInt(document.querySelector('[data-field="current_count"]').textContent) || 3880;

        // 1. Calculate daily_weight = total_weight / total_sold_count
        if (totalSoldCount > 0) {
            const dailyWeight = totalWeight / totalSoldCount;
            row.querySelector('[data-field="daily_weight"]').textContent = dailyWeight.toFixed(9);
        }

        // 2. Calculate mortality_rate = (dead_count * 100) / initial_count
        if (initialCount > 0) {
            const mortalityRate = (deadCount * 100) / initialCount;
            row.querySelector('[data-field="mortality_rate"]').textContent = mortalityRate.toFixed(9);
        }

        // Get row index
        const rows = document.querySelectorAll('#salesTableBody tr');
        const rowIndex = Array.from(rows).indexOf(row);

        // 3. Calculate surplus_deficit = dead_count + cumulative_sold_count - initial_count
        if (rowIndex === 0) {  // Only for first row
            const surplusDeficit = (deadCount + cumulativeSoldCount) - initialCount;
            row.querySelector('[data-field="surplus_deficit"]').textContent = surplusDeficit.toFixed(0);
        } else {
            // For other rows, you can set it to 0 or keep their existing value
            row.querySelector('[data-field="surplus_deficit"]').textContent;
        }
        
        // 4. Calculate weight divisions ONLY for the second row
        if (rowIndex === 1) {  // Changed from rowIndex > 0 to rowIndex === 1
            const prevRow = rows[rowIndex - 1];
            const prev21to30 = parseFloat(prevRow.querySelector('[data-field="weight_21to30"]').textContent) || 0;
            const prev31to36 = parseFloat(prevRow.querySelector('[data-field="weight_31to36"]').textContent) || 0;
            const prev37toEnd = parseFloat(prevRow.querySelector('[data-field="weight_37to_end"]').textContent) || 0;
            
            // Apply the percentage calculations like Excel formulas
            const calculated21to30 = prev21to30 * 0.3;
            const calculated31to36 = prev31to36 * 0.7;
            const calculated37toEnd = prev37toEnd * 0.8;
            
            row.querySelector('[data-field="weight_21to30"]').textContent = calculated21to30.toFixed(2);
            row.querySelector('[data-field="weight_31to36"]').textContent = calculated31to36.toFixed(2);
            row.querySelector('[data-field="weight_37to_end"]').textContent = calculated37toEnd.toFixed(2);
        }
        
        // NEW: If editing first row, also update second row
        if (rowIndex === 0 && rows.length > 1) {
            const secondRow = rows[1];
            this.calculateRowTotals(secondRow); // Recursively update second row
        }

        // 5. Calculate total_chicken_weight cumulative
        let cumulativeChickenWeight = totalChickenWeight;
        if (rowIndex === 1) {
            const prevRow = rows[rowIndex - 1];
            const prevChickenWeight = parseFloat(prevRow.querySelector('[data-field="total_chicken_weight"]').textContent) || 0;
            const prev21to30 = parseFloat(prevRow.querySelector('[data-field="weight_21to30"]').textContent) || 0;
            const prev31to36 = parseFloat(prevRow.querySelector('[data-field="weight_31to36"]').textContent) || 0;
            const prev37toEnd = parseFloat(prevRow.querySelector('[data-field="weight_37to_end"]').textContent) || 0;
            
            cumulativeChickenWeight = prevChickenWeight + (prev21to30 * 0.3) + (prev31to36 * 0.7) + (prev37toEnd * 0.8);
            row.querySelector('[data-field="total_chicken_weight"]').textContent = cumulativeChickenWeight.toFixed(2);
        } else if (rowIndex >= 2) {
            // For third row and beyond, set total_chicken_weight to 0
            row.querySelector('[data-field="total_chicken_weight"]').textContent = '0';
        }

        // 6. Calculate total_feed_weight = total_feed_consumption_rate * 30.76
        const totalFeedWeight = totalFeedConsumptionRate * 30.76;
        row.querySelector('[data-field="total_feed_weight"]').textContent = totalFeedWeight.toFixed(2);

        // 7. Calculate final_weight = total_chicken_weight / current_count - ONLY FOR FIRST ROW
        if (rowIndex === 0 && currentCount > 0) {
            const finalWeight = cumulativeChickenWeight / currentCount;
            row.querySelector('[data-field="final_weight"]').textContent = finalWeight.toFixed(9);
        } else if (rowIndex > 0) {
            // For other rows, set final_weight to empty or 0
            row.querySelector('[data-field="final_weight"]').textContent = '0';
        }

        // 8. Calculate FCR = total_feed_weight / (total_chicken_weight + weight_21to30/0.3 + weight_31to36/0.7 + weight_37to_end/0.8)
        const denominator = cumulativeChickenWeight + (weight21to30 * 0.3) + (weight31to36 * 0.7) + (weight37toEnd * 0.8);
        if (denominator > 0) {
            const fcr = totalFeedWeight / denominator;
            row.querySelector('[data-field="fcr"]').textContent = fcr.toFixed(9);
        }
    }

    calculateCumulativeTotals() {
        const rows = document.querySelectorAll('#salesTableBody tr');
        let cumulativeSoldCount = 0;
        let cumulativeTotalWeight = 0;
        
        rows.forEach((row, index) => {
            const soldCount = parseFloat(row.querySelector('[data-field="sold_count"]').textContent) || 0;
            const totalWeight = parseFloat(row.querySelector('[data-field="weight_per_chicken"]').textContent) || 0;
            
            cumulativeSoldCount += soldCount;
            cumulativeTotalWeight += totalWeight;
            
            // Update cumulative fields
            row.querySelector('[data-field="total_sold_count"]').textContent = cumulativeSoldCount.toFixed(0);
            row.querySelector('[data-field="total_weight"]').textContent = cumulativeTotalWeight.toFixed(2);
        });
    }

    startHeaderEditing(header) {
        if (this.currentEditing) {
            this.finishEditing();
        }

        this.currentEditing = header;
        this.originalValue = header.textContent;
        
        const field = header.getAttribute('data-field');
        const currentValue = header.textContent.replace('ကြက်အမျိုးအစား ', '').trim();
        
        header.classList.add('editing');
        
        let input;
        if (field === 'chicken_type') {
            input = `
                <select class="header-edit-select">
                    <option value="CP" ${currentValue === 'CP' ? 'selected' : ''}>CP</option>
                    <option value="KBZ" ${currentValue === 'KBZ' ? 'selected' : ''}>KBZ</option>
                </select>
            `;
        } else {
            input = `<input type="number" class="header-edit-input" value="${currentValue}">`;
        }
        
        header.innerHTML = input;
        
        const inputElement = header.querySelector('input, select');
        inputElement.focus();
        
        if (inputElement.tagName === 'INPUT') {
            inputElement.select();
        }
        
        inputElement.addEventListener('blur', () => this.finishHeaderEditing());
        inputElement.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                this.finishHeaderEditing();
            } else if (e.key === 'Escape') {
                this.cancelHeaderEditing();
            }
        });
    }

    finishHeaderEditing() {
        if (!this.currentEditing) return;
        
        const input = this.currentEditing.querySelector('input, select');
        const newValue = input ? input.value : '';
        const field = this.currentEditing.getAttribute('data-field');
        
        this.currentEditing.classList.remove('editing');
        
        if (field === 'chicken_type') {
            this.currentEditing.textContent = `ကြက်အမျိုးအစား ${newValue}`;
        } else {
            this.currentEditing.textContent = newValue;
        }
        
        this.updateAllRowsWithHeaderValues();
        this.currentEditing = null;
        this.originalValue = null;
    }

    cancelHeaderEditing() {
        if (!this.currentEditing) return;
        
        this.currentEditing.textContent = this.originalValue;
        this.currentEditing.classList.remove('editing');
        this.currentEditing = null;
        this.originalValue = null;
    }

    updateAllRowsWithHeaderValues() {
        const chickenType = document.querySelector('[data-field="chicken_type"]').textContent.replace('ကြက်အမျိုးအစား ', '').trim();
        const initialCount = parseInt(document.querySelector('[data-field="initial_count"]').textContent) || 4080;
        const currentCount = parseInt(document.querySelector('[data-field="current_count"]').textContent) || 3880;
        
        const rows = document.querySelectorAll('#salesTableBody tr');
        rows.forEach(row => {
            if (row.getAttribute('data-id')) {
                const rowData = this.getRowData(row);
                rowData.chicken_type = chickenType;
                rowData.initial_count = initialCount;
                rowData.current_count = currentCount;
                
                this.sendDataToServer(rowData).catch(error => {
                    console.error('Error updating row with new header values:', error);
                });
            }
        });
    }

    addNewRow() {
        const tbody = document.getElementById('salesTableBody');
        if (!tbody) {
            console.error('salesTableBody not found!');
            return;
        }
        
        const chickenType = document.querySelector('[data-field="chicken_type"]').textContent.replace('ကြက်အမျိုးအစား ', '').trim();
        const initialCount = parseInt(document.querySelector('[data-field="initial_count"]').textContent) || 4080;
        const currentCount = parseInt(document.querySelector('[data-field="current_count"]').textContent) || 3880;
        
        // Get today's date in Myanmar timezone (UTC+6:30)
        const today = new Date();
        const myanmarOffset = 6.5 * 60 * 60 * 1000; // UTC+6:30 in milliseconds
        const myanmarTime = new Date(today.getTime() + myanmarOffset);
        const newDate = myanmarTime.toISOString().split('T')[0];
        
        const newRow = document.createElement('tr');
        newRow.innerHTML = this.getEmptyRowHTML(chickenType, initialCount, currentCount, newDate);
        tbody.appendChild(newRow);
        this.markRowPending(newRow);
        
        // Recalculate all rows to update cumulative values
        this.calculateCumulativeTotals();
        
        console.log('New row added with today\'s date (Myanmar):', newDate);
    }

    getEmptyRowHTML(chickenType, initialCount, currentCount, newDate) {
        // Generate a temporary ID for new rows
        const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        return `
            <td class="editable" data-field="date">${newDate}</td>
            <td class="editable" data-field="sold_count">0</td>
            <td class="editable" data-field="weight_per_chicken">0</td>
            <td class="editable" data-field="total_sold_count">0</td>
            <td class="editable" data-field="total_weight">0</td>
            <td class="editable" data-field="daily_weight">0</td>
            <td class="editable" data-field="dead_count">0</td>
            <td class="editable" data-field="mortality_rate">0</td>
            <td class="editable" data-field="cumulative_sold_count">0</td>
            <td class="editable" data-field="surplus_deficit">0</td>
            <td class="editable" data-field="weight_21to30">0</td>
            <td class="editable" data-field="weight_31to36">0</td>
            <td class="editable" data-field="weight_37to_end">0</td>
            <td class="editable" data-field="total_chicken_weight">0</td>
            <td class="editable" data-field="total_feed_consumption_rate">0</td>
            <td class="editable" data-field="total_feed_weight">0</td>
            <td class="editable" data-field="final_weight">0</td>
            <td class="editable" data-field="fcr">0</td>
            <td>
                <button class="save-btn pending">သိမ်းရန်</button>
                <button class="delete-btn" data-id="${tempId}">ဖျက်ရန်</button>
            </td>
            <td class="comment-cell">
                <div class="comment-container">
                    <button class="btn-comment" 
                        data-id="${tempId}"
                        data-has-comment="0"
                        data-comment-read="0"
                        data-current-comment=""
                        data-comment-author=""
                        data-comment-date=""
                        data-temp-row="true">
                        <i class="fa-regular fa-comment"></i>
                    </button>
                </div>
            </td>
        `;
    }


    startEditing(cell) {
        if (this.currentEditing) {
            this.finishEditing();
        }

        this.currentEditing = cell;
        this.originalValue = cell.textContent;
        
        const field = cell.getAttribute('data-field');
        const value = cell.textContent;
        
        let input;
        if (field === 'date') {
            input = `<input type="date" class="edit-input" value="${value}">`;
        } else if (field === 'comments') {
            input = `<textarea class="edit-input" rows="2">${value}</textarea>`;
        } else {
            input = `<input type="number" step="0.01" class="edit-input" value="${value}">`;
        }
        
        cell.innerHTML = input;
        
        const inputElement = cell.querySelector('input, textarea');
        inputElement.focus();
        inputElement.select();
        
        inputElement.addEventListener('blur', () => this.finishEditing());
        inputElement.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                this.finishEditing();
            } else if (e.key === 'Escape') {
                this.cancelEditing();
            }
        });
    }

    finishEditing() {
        if (!this.currentEditing) return;
        
        const input = this.currentEditing.querySelector('input, textarea');
        const newValue = input ? input.value : '';
        const field = this.currentEditing.getAttribute('data-field');
        const row = this.currentEditing.closest('tr');
        
        if (field === 'date') {
            if (!this.validateDateOrder(row, newValue)) {
                this.cancelEditing();
                return;
            }
        }
        
        this.currentEditing.textContent = newValue;
        this.calculateRowTotals(row);
        this.markRowPending(row);
        this.currentEditing = null;
        this.originalValue = null;
    }

    cancelEditing() {
        if (!this.currentEditing) return;
        
        this.currentEditing.textContent = this.originalValue;
        this.currentEditing = null;
        this.originalValue = null;
    }

    saveRow(button) {
        const row = button.closest('tr');
        const rowData = this.getRowData(row);
        
        this.sendDataToServer(rowData)
            .then(response => {
                alert('အောင်မြင်စွာသိမ်းဆည်းပြီး');
                if (response.id && !row.getAttribute('data-id')) {
                    row.setAttribute('data-id', response.id);
                }
                   // Log the save action
            HistoryLogger.logSaveRow(response.id || rowData.id);
                this.markRowSaved(row);
                this.loadData();
            })
            .catch(error => {
                alert('သိမ်းဆည်းရာတွင်အမှားတစ်ခုဖြစ်သည်');
                console.error('Error:', error);
            });
    }

    deleteRow(button) {
        if (confirm('ဤအချက်အလက်ကိုဖျက်မှာသေချာပါသလား?')) {
            const row = button.closest('tr');
            const rowId = row.getAttribute('data-id');
            
            if (rowId) {
                this.deleteFromServer(rowId)
                    .then(() => {
                        row.remove();
                        this.calculateCumulativeTotals();

                         // Log the delete action
                    HistoryLogger.logDeleteRow(rowId);

                        alert('ဖျက်ပြီးပါပြီ');
                    })
                    .catch(error => {
                        alert('ဖျက်ရာတွင်အမှားတစ်ခုဖြစ်သည်');
                        console.error('Error:', error);
                    });
            } else {
                row.remove();
                this.calculateCumulativeTotals();
            }
        }
    }

    deleteAllData() {
        const rows = document.querySelectorAll('#salesTableBody tr');
        
        if (rows.length === 0) {
            alert('ဖျက်ရန်ဒေတာမရှိပါ');
            return;
        }
        
        if (confirm('ဒေတာအားလုံးကိုဖျက်မှာသေချာပါသလား? ဤလုပ်ဆောင်ချက်ကိုပြန်လည်ရယူ၍မရပါ။')) {
            const deleteButton = document.getElementById('deleteAllData');
            const originalText = deleteButton.innerHTML;
            deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ဖျက်နေသည်...';
            deleteButton.disabled = true;
            
            const rowIds = [];
            rows.forEach(row => {
                const rowId = row.getAttribute('data-id');
                if (rowId) {
                    rowIds.push(rowId);
                }
            });
            
            if (rowIds.length > 0) {
                this.deleteAllFromServer(rowIds)
                    .then(() => {
                        this.clearAllRows();
                        // Log the delete all action
                        HistoryLogger.logDeleteAll(rowIds.length);
                        alert('ဒေတာအားလုံးအောင်မြင်စွာဖျက်ပြီးပါပြီ');
                    })
                    .catch(error => {
                        alert('ဖျက်ရာတွင်အမှားတစ်ခုဖြစ်သည်');
                        console.error('Error:', error);
                    })
                    .finally(() => {
                        deleteButton.innerHTML = originalText;
                        deleteButton.disabled = false;
                    });
            } else {
                this.clearAllRows();
                // Log the delete all action
                HistoryLogger.logDeleteAll(rowIds.length);
                alert('ဒေတာအားလုံးအောင်မြင်စွာဖျက်ပြီးပါပြီ');
                deleteButton.innerHTML = originalText;
                deleteButton.disabled = false;
            }
        }
    }
    
    clearAllRows() {
        const tbody = document.getElementById('salesTableBody');
        tbody.innerHTML = '';
        this.calculateCumulativeTotals();
    }
    
    async deleteAllFromServer(rowIds) {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const currentFarmId = urlParams.get('farm_id') || 1;
            
            const response = await fetch('delete_all_sales.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    ids: rowIds,
                    farm_id: currentFarmId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Unknown error occurred');
            }
            
            return result;
        } catch (error) {
            console.error('Delete all error:', error);
            throw error;
        }
    }

    saveAllData() {
        // Prevent multiple simultaneous saves
        if (this.isSaving) {
            console.log("Save already in progress, skipping...");
            return;
        }
        
        this.isSaving = true;
        
        const rows = document.querySelectorAll('#salesTableBody tr');
        const allData = [];
        
        const urlParams = new URLSearchParams(window.location.search);
        const currentFarmId = urlParams.get('farm_id') || 
                             document.getElementById('saveAllData').getAttribute('data-farm-id') || 
                             1;
        
        console.log("Save All - Current Farm ID:", currentFarmId);
        console.log("Save All - Number of rows:", rows.length);
        
        const rowIds = new Set();
        rows.forEach((row, index) => {
            const rowData = this.getRowData(row);
            
            if (rowData.id) {
                if (rowIds.has(rowData.id)) {
                    console.warn("DUPLICATE ROW ID FOUND:", rowData.id, "at index", index);
                }
                rowIds.add(rowData.id);
            }
            
            rowData.farm_id = currentFarmId;
            allData.push(rowData);
        });
        
        if (allData.length === 0) {
            alert('သိမ်းရန်ဒေတာမရှိပါ');
            this.isSaving = false;
            return;
        }
        
        const saveButton = document.getElementById('saveAllData');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> သိမ်းဆည်းနေသည်...';
        saveButton.disabled = true;
        
        this.sendBulkDataToServer(allData, currentFarmId)
            .then(response => {
                alert('ဒေတာအားလုံးအောင်မြင်စွာသိမ်းဆည်းပြီး');
                  // Log the save all action
    const rowCount = document.querySelectorAll('#salesTableBody tr[data-id]').length;
    HistoryLogger.logSaveAll(rowCount);
                this.loadData();
            })
            .catch(error => {
                alert('သိမ်းဆည်းရာတွင်အမှားတစ်ခုဖြစ်သည်: ' + error.message);
                console.error('Save All Error:', error);
            })
            .finally(() => {
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
                this.isSaving = false;
            });
    }

    getRowData(row) {
        const data = {};
        const cells = row.querySelectorAll('[data-field]');
        
        cells.forEach(cell => {
            const field = cell.getAttribute('data-field');
            const input = cell.querySelector('input, textarea, select');
            let value = input ? input.value.trim() : cell.textContent.trim();
            
            if (field !== 'date' && field !== 'comments') {
                value = value ? parseFloat(value) || 0 : 0;
            }
            
            data[field] = value;
        });
        
        const chickenType = document.querySelector('[data-field="chicken_type"]').textContent.replace('ကြက်အမျိုးအစား ', '').trim();
        const initialCount = parseInt(document.querySelector('[data-field="initial_count"]').textContent) || 4080;
        const currentCount = parseInt(document.querySelector('[data-field="current_count"]').textContent) || 3880;
        
        data.chicken_type = chickenType;
        data.initial_count = initialCount;
        data.current_count = currentCount;
        
        data.id = row.getAttribute('data-id') || null;
        
        const urlParams = new URLSearchParams(window.location.search);
        data.farm_id = urlParams.get('farm_id') || 
                       document.getElementById('saveAllData')?.getAttribute('data-farm-id') || 
                       1;
        
        return data;
    }

    async sendDataToServer(data) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = (window.currentGlobalPage || urlParams.get('page') || 1);
        const currentFarmId = window.currentFarmId || urlParams.get('farm_id') || 1;
        
        console.log("Sending data with farm_id:", currentFarmId, "page:", currentPage);
        
        data.page_number = currentPage;
        data.farm_id = currentFarmId;
        
        console.log("Final data being sent:", data);
        
        const response = await fetch('save_sales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error('Network error');
        }
        
        const result = await response.json();
        console.log("Server response:", result);
        return result;
    }

    async sendBulkDataToServer(data, farmId) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = (window.currentGlobalPage || urlParams.get('page') || 1);
        const farm = window.currentFarmId || farmId;
        
        console.log("Bulk Save - Farm ID:", farm, "Page:", currentPage);
        console.log("Bulk Save - Data to send:", data);
        
        data.forEach(item => {
            item.page_number = currentPage;
            item.farm_id = farm;
            
            const numericFields = [
                'sold_count', 'weight_per_chicken', 'total_sold_count', 'total_weight',
                'daily_weight', 'dead_count', 'mortality_rate', 'cumulative_sold_count', 
                'surplus_deficit', 'weight_21to30', 'weight_31to36', 'weight_37to_end',
                'total_chicken_weight', 'total_feed_consumption_rate', 'total_feed_weight',
                'final_weight', 'fcr', 'initial_count', 'current_count'
            ];
            
            numericFields.forEach(field => {
                if (item[field] !== undefined && item[field] !== null) {
                    item[field] = parseFloat(item[field]) || 0;
                }
            });
            
            if (!item.date) {
                item.date = new Date().toISOString().split('T')[0];
            }
        });
        
        const payload = {
            sales: data,
            farm_id: farm,
            page_number: currentPage
        };
        
        console.log("Bulk Save - Final payload:", payload);
        
        try {
            const response = await fetch('save_bulk_sales.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });
            
            console.log("Bulk Save - Response status:", response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error("Bulk Save - Server error response:", errorText);
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }
            
            const result = await response.json();
            console.log("Bulk Save - Success response:", result);
            return result;
            
        } catch (error) {
            console.error("Bulk Save - Fetch error:", error);
            throw error;
        }
    }

    async deleteFromServer(id) {
        const response = await fetch('delete_sales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });
        
        if (!response.ok) {
            throw new Error('Network error');
        }
        
        return await response.json();
    }

    async loadData() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 1;
            const currentFarmId = urlParams.get('farm_id') || 1;
            
            const response = await fetch(`get_sales.php?page=${currentPage}&farm_id=${currentFarmId}`);
            const data = await response.json();
            this.allData = data;
            this.populateTable(data);
            this.calculateCumulativeTotals();
            
            if (data.length > 0) {
                this.updateHeaderValues(data[0]);
            }
        } catch (error) {
            console.error('Error loading data:', error);
            this.loadSampleData();
        }
    }

    updateHeaderValues(firstRecord) {
        if (firstRecord.chicken_type) {
            document.querySelector('[data-field="chicken_type"]').textContent = `ကြက်အမျိုးအစား ${firstRecord.chicken_type}`;
        }
        if (firstRecord.initial_count) {
            document.querySelector('[data-field="initial_count"]').textContent = firstRecord.initial_count;
        }
        if (firstRecord.current_count) {
            document.querySelector('[data-field="current_count"]').textContent = firstRecord.current_count;
        }
    }

    populateTable(data) {
        const tbody = document.getElementById('salesTableBody');
        tbody.innerHTML = '';
        
        data.forEach(item => {
            const row = document.createElement('tr');
            if (item.id) {
                row.setAttribute('data-id', item.id);
            }
            
            row.innerHTML = this.getRowHTML(item);
            tbody.appendChild(row);
            this.calculateRowTotals(row);
            if (item.id) {
                this.markRowSaved(row);
            } else {
                this.markRowPending(row);
            }
        });
    }

    getRowHTML(item) {
        // Ensure all required fields have values
        const rowId = item.id || '';
        const hasComment = item.has_comment ? '1' : '0';
        const commentRead = item.comment_read ? '1' : '0';
        const currentComment = item.comments || '';
        const commentAuthor = item.comment_author || '';
        const commentDate = item.comment_created_at || '';
        
        // Determine badge class - FIXED VERSION
        let badgeHTML = '';
        if (item.has_comment) {
            let badgeClass = 'badge-comment-unread';
            
            // Use the global user data passed from PHP
            if (window.currentUser && 
                (item.comment_author_id === window.currentUser.id || window.currentUser.role === 'admin')) {
                badgeClass = 'badge-comment-admin';
            } else if (item.comment_read) {
                badgeClass = 'badge-comment-read';
            }
            
            badgeHTML = `<span class="comment-badge ${badgeClass}"></span>`;
        }
        
        return `
            <td class="editable" data-field="date">${item.date || ''}</td>
            <td class="editable" data-field="sold_count">${item.sold_count || 0}</td>
            <td class="editable" data-field="weight_per_chicken">${item.weight_per_chicken || 0}</td>
            <td class="editable" data-field="total_sold_count">${item.total_sold_count || 0}</td>
            <td class="editable" data-field="total_weight">${item.total_weight || 0}</td>
            <td class="editable" data-field="daily_weight">${item.daily_weight || 0}</td>
            <td class="editable" data-field="dead_count">${item.dead_count || 0}</td>
            <td class="editable" data-field="mortality_rate">${item.mortality_rate || 0}</td>
            <td class="editable" data-field="cumulative_sold_count">${item.cumulative_sold_count || 0}</td>
            <td class="editable" data-field="surplus_deficit">${item.surplus_deficit || 0}</td>
            <td class="editable" data-field="weight_21to30">${item.weight_21to30 || 0}</td>
            <td class="editable" data-field="weight_31to36">${item.weight_31to36 || 0}</td>
            <td class="editable" data-field="weight_37to_end">${item.weight_37to_end || 0}</td>
            <td class="editable" data-field="total_chicken_weight">${item.total_chicken_weight || 0}</td>
            <td class="editable" data-field="total_feed_consumption_rate">${item.total_feed_consumption_rate || 0}</td>
            <td class="editable" data-field="total_feed_weight">${item.total_feed_weight || 0}</td>
            <td class="editable" data-field="final_weight">${item.final_weight || 0}</td>
            <td class="editable" data-field="fcr">${item.fcr || 0}</td>
            <td>
            <button class="${item.id ? 'save-btn saved' : 'save-btn pending'}">${item.id ? 'သိမ်းပြီး' : 'သိမ်းရန်'}</button>
            <button class="delete-btn">ဖျက်ရန်</button>
        </td>
            <td class="comment-cell">
                <div class="comment-container">
                    <button class="btn-comment" 
                        data-id="${rowId}"
                        data-has-comment="${hasComment}"
                        data-comment-read="${commentRead}"
                        data-current-comment="${currentComment}"
                        data-comment-author="${commentAuthor}"
                        data-comment-date="${commentDate}">
                        <i class="fa-regular fa-comment"></i>
                        ${badgeHTML}
                    </button>
                </div>
            </td>
        `;
    }

    markRowPending(row) {
        const btn = row.querySelector('.save-btn');
        if (!btn) return;
        btn.textContent = 'သိမ်းရန်';
        btn.classList.add('pending');
        btn.classList.remove('saved');
    }

    markRowSaved(row) {
        const btn = row.querySelector('.save-btn');
        if (!btn) return;
        btn.textContent = 'သိမ်းပြီး';
        btn.classList.remove('pending');
        btn.classList.add('saved');
    }

    loadSampleData() {
        const sampleData = [
            {
                date: '2025-07-30',
                sold_count: 200,
                weight_per_chicken: 312.1,
                total_sold_count: 200,
                total_weight: 312.1,
                daily_weight: 1.56,
                dead_count: 12,
                mortality_rate: 0.29,
                cumulative_sold_count: 200,
                surplus_deficit: 212,
                weight_21to30: 45,
                weight_31to36: 32,
                weight_37_to_end: 23,
                total_chicken_weight: 1250,
                total_feed_consumption_rate: 85,
                total_feed_weight: 2614.6,
                final_weight: 0.32,
                fcr: 1.68,
                chicken_type: 'CP',
                initial_count: 4080,
                current_count: 3880,
                comments: 'မှတ်ချက်တစ်ခု',
                has_comment: 1,
                comment_read: 0,
                comment_author: 'admin',
                comment_created_at: '2025-07-30 10:00:00'
            }
        ];
        this.allData = sampleData;
        this.populateTable(sampleData);
        this.calculateCumulativeTotals();
        this.updateHeaderValues(sampleData[0]);
    }

    searchData() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (startDate && endDate) {
            this.loadFilteredData(startDate, endDate);
        } else {
            alert('ကျေးဇူးပြု၍ ရက်စွဲနှစ်ခုလုံးထည့်ပါ');
        }
    }

    clearSearch() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        this.loadData();
    }

    async loadFilteredData(startDate, endDate) {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 1;
            
            const response = await fetch(`get_sales.php?start_date=${startDate}&end_date=${endDate}&page=${currentPage}`);
            const data = await response.json();
            this.allData = data;
            this.populateTable(data);
            this.calculateCumulativeTotals();
            
            if (data.length > 0) {
                this.updateHeaderValues(data[0]);
            }
        } catch (error) {
            console.error('Error loading filtered data:', error);
        }
    }

    validateDateOrder(row, newDate) {
        const rows = document.querySelectorAll('#salesTableBody tr');
        const rowIndex = Array.from(rows).indexOf(row);
        
        if (rowIndex > 0) {
            const prevRow = rows[rowIndex - 1];
            const prevDate = prevRow.querySelector('[data-field="date"]').textContent;
            
            if (newDate <= prevDate) {
                alert('Error: Date must be later than previous row\'s date (' + prevDate + ')');
                return false;
            }
        }
        
        if (rowIndex < rows.length - 1) {
            const nextRow = rows[rowIndex + 1];
            const nextDate = nextRow.querySelector('[data-field="date"]').textContent;
            
            if (newDate >= nextDate) {
                alert('Error: Date must be earlier than next row\'s date (' + nextDate + ')');
                return false;
            }
        }
        
        return true;
    }
}


// Simplified history logging for specific actions only
class HistoryLogger {
    static logAction(actionType, description, recordId = 0) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentFarmId = urlParams.get('farm_id') || 1;
        const currentPage = urlParams.get('page') || 1;
        
        const logData = {
            farm_id: parseInt(currentFarmId),
            page_number: parseInt(currentPage),
            action_type: actionType,
            table_name: 'sales_summary',
            record_id: parseInt(recordId) || 0,
            description: description
        };
        
        console.log('Logging action:', logData);
        
        // Send to server but don't wait for response
        fetch('log_history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(logData)
        })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                console.error('Failed to log history:', result.error);
            }
        })
        .catch(error => {
            console.error('Error logging history:', error);
        });
    }
    
    // Specific action loggers
    static logSaveRow(rowId) {
        this.logAction('UPDATE', `အရောင်းမှတ်တမ်းသိမ်းဆည်းခြင်း - ID: ${rowId}`, rowId);
    }
    
    static logDeleteRow(rowId) {
        this.logAction('DELETE', `အရောင်းမှတ်တမ်းဖျက်ခြင်း - ID: ${rowId}`, rowId);
    }
    
    static logSaveAll(rowCount) {
        this.logAction('UPDATE', `ဒေတာအားလုံးသိမ်းဆည်းခြင်း - အရေအတွက်: ${rowCount} ခု`);
    }
    
    static logDeleteAll(rowCount) {
        this.logAction('DELETE', `ဒေတာအားလုံးဖျက်ခြင်း - အရေအတွက်: ${rowCount} ခု`);
    }
    
    static logNewPage() {
        this.logAction('INSERT', 'စာမျက်နှာအသစ်ထည့်သွင်းခြင်း');
    }
}
// Initialize the sales manager when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.salesManager = new SalesManager();
});
