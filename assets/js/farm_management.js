let currentFarm = <?php echo $selected_farm; ?>;
let currentTable = '';
let editingRecord = null;
let isOwner = <?php echo $is_owner ? 'true' : 'false'; ?>;

// Load farm data and notifications
function loadFarmData() {
    loadTableData('person');
    loadTableData('feed');
    loadTableData('sales');
    loadTableData('medicine');
    loadTableData('audit');
    loadComments();
    loadNotifications();
}

// Load notifications for all farms
function loadNotifications() {
    fetch('get_farm_notifications.php')
        .then(response => response.json())
        .then(data => {
            // Update notification badges for each farm
            data.forEach(farm => {
                const badge = document.getElementById(`badge-${farm.farm_id}`);
                const check = document.getElementById(`check-${farm.farm_id}`);
                
                if (farm.unresolved_count > 0) {
                    badge.style.display = 'flex';
                    badge.textContent = farm.unresolved_count;
                    check.style.display = 'none';
                } else {
                    badge.style.display = 'none';
                    check.style.display = 'flex';
                }
            });
        })
        .catch(error => console.error('Error loading notifications:', error));
}

// Load comments for current farm
function loadComments() {
    if (!isOwner) return;
    
    fetch(`get_farm_comments.php?farm_id=${currentFarm}`)
        .then(response => response.json())
        .then(data => {
            const commentsList = document.getElementById('commentsList');
            const commentsCount = document.getElementById('commentsCount');
            
            commentsList.innerHTML = '';
            commentsCount.textContent = `${data.length} comments`;
            
            if (data.length === 0) {
                commentsList.innerHTML = '<p>No comments yet.</p>';
                return;
            }
            
            data.forEach(comment => {
                const commentItem = document.createElement('div');
                commentItem.className = 'comment-item';
                commentItem.innerHTML = `
                    <div class="comment-text">
                        <div>${comment.comment_text}</div>
                        <div class="comment-meta">
                            By ${comment.username} on ${comment.created_at}
                            ${comment.is_resolved ? '<span style="color: green;">(Resolved)</span>' : ''}
                        </div>
                    </div>
                    <div class="comment-actions">
                        ${!comment.is_resolved ? 
                            `<button class="btn btn-sm" onclick="resolveComment(${comment.id})">Mark Resolved</button>` : 
                            ''
                        }
                        <button class="btn btn-sm logout" onclick="deleteComment(${comment.id})">Delete</button>
                    </div>
                `;
                commentsList.appendChild(commentItem);
            });
        })
        .catch(error => console.error('Error loading comments:', error));
}

// Select farm
function selectFarm(farmId) {
    currentFarm = farmId;
    
    // Update active button
    document.querySelectorAll('.farm-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Load data for all tables and comments
    loadFarmData();
}

// Load table data
function loadTableData(tableName) {
    fetch(`get_farm_data.php?table=${tableName}&farm_id=${currentFarm}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById(`${tableName}TableBody`);
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="20" style="text-align: center;">No data available</td></tr>';
                return;
            }
            
            data.forEach(record => {
                const row = createTableRow(tableName, record);
                tbody.appendChild(row);
            });
        })
        .catch(error => {
            console.error('Error loading data:', error);
        });
}

// Create table row based on table type
function createTableRow(tableName, record) {
    const row = document.createElement('tr');
    
    switch(tableName) {
        case 'person':
            row.innerHTML = `
                <td>${record.age || ''}</td>
                <td>${record.date || ''}</td>
                <td>${record.company_account || ''}</td>
                <td>${record.feed_input || ''}</td>
                <td>${record.feed_total || ''}</td>
                <td>${record.company_balance || ''}</td>
                <td>${record.feed_balance || ''}</td>
                <td>${record.daily_feed_rate || ''}</td>
                <td>${record.total_feed_rate || ''}</td>
                <td>${record.weight || ''}</td>
                <td>${record.dead || ''}</td>
                <td>${record.total_dead || ''}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm" onclick="editRecord('person', ${record.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm logout" onclick="deleteRecord('person', ${record.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </td>
            `;
            break;
        case 'feed':
            row.innerHTML = `
                <td>${record.feed_type || ''}</td>
                <td>${record.feed_name || ''}</td>
                <td>${record.quantity || ''}</td>
                <td>${record.price || ''}</td>
                <td>${record.cost || ''}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm" onclick="editRecord('feed', ${record.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm logout" onclick="deleteRecord('feed', ${record.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </td>
            `;
            break;
        // Add other table cases here...
    }
    
    return row;
}

// Open modal for adding records
function openModal(table) {
    currentTable = table;
    editingRecord = null;
    
    const modal = document.getElementById('recordModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('recordForm');
    
    modalTitle.textContent = `Add ${getTableName(table)} Record`;
    form.innerHTML = generateFormFields(table);
    
    modal.style.display = 'flex';
}

// Close modal
function closeModal() {
    const modal = document.getElementById('recordModal');
    modal.style.display = 'none';
}

// Generate form fields based on table type
function generateFormFields(table) {
    let fields = '';
    
    switch(table) {
        case 'person':
            fields = `
                <div class="form-group">
                    <label for="age">အသက်</label>
                    <input type="text" class="form-control" id="age" name="age">
                </div>
                <div class="form-group">
                    <label for="date">ရက်စွဲ</label>
                    <input type="date" class="form-control" id="date" name="date">
                </div>
                <div class="form-group">
                    <label for="company_account">ကုမ္ပဏီဝင်စာ</label>
                    <input type="text" class="form-control" id="company_account" name="company_account">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="feed_input">စပ်စာဝင်</label>
                        <input type="text" class="form-control" id="feed_input" name="feed_input">
                    </div>
                    <div class="form-group">
                        <label for="feed_total">အစာပေါင်း</label>
                        <input type="text" class="form-control" id="feed_total" name="feed_total">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_balance">ကုမ္ပဏီလက်ကျန်</label>
                        <input type="number" class="form-control" id="company_balance" name="company_balance" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="feed_balance">စပ်စာကျန်</label>
                        <input type="text" class="form-control" id="feed_balance" name="feed_balance">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="daily_feed_rate">နေ့စဉ်အစာစားနှုန်း</label>
                        <input type="text" class="form-control" id="daily_feed_rate" name="daily_feed_rate">
                    </div>
                    <div class="form-group">
                        <label for="total_feed_rate">စုစုပေါင်းအစာစားနှုန်း</label>
                        <input type="text" class="form-control" id="total_feed_rate" name="total_feed_rate">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="weight">အလေးချိန်</label>
                        <input type="number" class="form-control" id="weight" name="weight" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="dead">အသေ</label>
                        <input type="number" class="form-control" id="dead" name="dead">
                    </div>
                    <div class="form-group">
                        <label for="total_dead">စုစုပေါင်းအသေ</label>
                        <input type="number" class="form-control" id="total_dead" name="total_dead">
                    </div>
                </div>
            `;
            break;
        // Add other table form fields...
    }
    
    return fields;
}

// Get table display name
function getTableName(table) {
    const tableNames = {
        'person': 'Person in Charge',
        'feed': 'Feed Summary',
        'sales': 'Sales Summary',
        'medicine': 'Medicine List',
        'audit': 'Farm Audit'
    };
    return tableNames[table] || 'Record';
}

// Edit record
function editRecord(table, recordId) {
    currentTable = table;
    editingRecord = recordId;
    
    // Fetch record data and populate form
    fetch(`get_farm_record.php?table=${table}&id=${recordId}`)
        .then(response => response.json())
        .then(record => {
            const modal = document.getElementById('recordModal');
            const modalTitle = document.getElementById('modalTitle');
            const form = document.getElementById('recordForm');
            
            modalTitle.textContent = `Edit ${getTableName(table)} Record`;
            form.innerHTML = generateFormFields(table);
            
            // Populate form with record data
            populateForm(record);
            
            modal.style.display = 'flex';
        });
}

// Delete record
function deleteRecord(table, recordId) {
    if (confirm('Are you sure you want to delete this record?')) {
        fetch('delete_farm_record.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `table=${table}&id=${recordId}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadTableData(table);
            } else {
                alert('Error deleting record: ' + result.error);
            }
        });
    }
}

// Submit comment
document.getElementById('commentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('farm_id', currentFarm);
    formData.append('comment_text', document.getElementById('commentText').value);
    
    fetch('add_farm_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.getElementById('commentText').value = '';
            loadComments();
            loadNotifications();
        } else {
            alert('Error adding comment: ' + result.error);
        }
    });
});

// Resolve comment
function resolveComment(commentId) {
    fetch('resolve_farm_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `comment_id=${commentId}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            loadComments();
            loadNotifications();
        } else {
            alert('Error resolving comment: ' + result.error);
        }
    });
}

// Delete comment
function deleteComment(commentId) {
    if (confirm('Are you sure you want to delete this comment?')) {
        fetch('delete_farm_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `comment_id=${commentId}`
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                loadComments();
                loadNotifications();
            } else {
                alert('Error deleting comment: ' + result.error);
            }
        });
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadFarmData();
    
    // Set up save record button
    document.getElementById('saveRecordBtn').addEventListener('click', function() {
        saveRecord();
    });
});

// Save record function
function saveRecord() {
    const form = document.getElementById('recordForm');
    const formData = new FormData(form);
    formData.append('table', currentTable);
    formData.append('farm_id', currentFarm);
    
    if (editingRecord) {
        formData.append('id', editingRecord);
    }
    
    fetch('save_farm_record.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeModal();
            loadTableData(currentTable);
        } else {
            alert('Error saving record: ' + result.error);
        }
    });
}

// Populate form with existing data
function populateForm(record) {
    for (const key in record) {
        const input = document.getElementById(key);
        if (input) {
            input.value = record[key];
        }
    }
}