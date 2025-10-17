let currentReceiver = 'all';
let pollInterval;
let onlineUsersInterval;
let activityInterval;
let unreadCountInterval;
let lastMessageCount = 0;
let perUserUnreadCounts = {};

document.addEventListener('DOMContentLoaded', function() {
    console.log('Chat system initialized');
    
    // Initialize all features
    initializeChat();
    startPeriodicUpdates();
});

function initializeChat() {
    // User selection
    document.querySelectorAll('.user-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.user-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            currentReceiver = this.dataset.userid;
            document.getElementById('receiverId').value = currentReceiver;
            
            // Mark messages as read when switching to a chat
            markMessagesAsRead();
            loadMessages();
            updateAllUnreadCounts();
        });
    });

    // File button
    document.getElementById('fileBtn').addEventListener('click', function() {
        document.getElementById('file').click();
    });

    // File input change
    document.getElementById('file').addEventListener('change', function() {
        if (this.files.length > 0) {
            document.getElementById('message').placeholder = `File: ${this.files[0].name}`;
        }
    });

    // Message form submission
    document.getElementById('messageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage();
    });

    // Load initial data
    loadMessages();
    loadOnlineUsers();
    updateAllUnreadCounts();
    updateActivity();
}

function startPeriodicUpdates() {
    // Update messages every 2 seconds
    pollInterval = setInterval(loadMessages, 2000);
    
    // Update online users every 10 seconds
    onlineUsersInterval = setInterval(loadOnlineUsers, 10000);
    
    // Update user activity every 30 seconds
    activityInterval = setInterval(updateActivity, 30000);
    
    // Update unread counts every 3 seconds
    unreadCountInterval = setInterval(updateAllUnreadCounts, 3000);
}

function sendMessage() {
    const formData = new FormData(document.getElementById('messageForm'));
    const messageInput = document.getElementById('message');
    const fileInput = document.getElementById('file');
    
    // Validate: must have either message or file
    if (!messageInput.value.trim() && fileInput.files.length === 0) {
        alert('Please enter a message or select a file');
        return;
    }

    // Show loading state
    const sendBtn = document.querySelector('.send-btn');
    const originalText = sendBtn.textContent;
    sendBtn.textContent = 'Sending...';
    sendBtn.disabled = true;

    fetch('send_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            fileInput.value = '';
            messageInput.placeholder = 'Type your message...';
            loadMessages();
            updateActivity();
        } else {
            alert('Error sending message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        alert('Error sending message. Check console for details.');
    })
    .finally(() => {
        // Restore button state
        sendBtn.textContent = originalText;
        sendBtn.disabled = false;
    });
}
function loadMessages() {
    const receiverId = currentReceiver;
    
    // Get current scroll position before loading new messages
    const container = document.getElementById('messagesContainer');
    const wasAtBottom = isScrolledToBottom(container);
    
    fetch(`get_messages.php?receiver_id=${receiverId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(messages => {
        // Always ensure messages is an array
        if (!Array.isArray(messages)) {
            messages = [];
        }
        
        // Check for new messages for notification
        if (messages.length > lastMessageCount && lastMessageCount > 0) {
            showNewMessageNotification();
        }
        
        // Store the current scroll position and container height
        const oldScrollTop = container.scrollTop;
        const oldScrollHeight = container.scrollHeight;
        
        container.innerHTML = '';
        
        if (messages.length === 0) {
            container.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
            lastMessageCount = 0;
            return;
        }
        
        messages.forEach(message => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.is_sent ? 'sent' : 'received'}`;
            
            let messageHTML = `
                <div class="message-header">
                    <strong>${message.sender_name}</strong> 
                    <span>${message.timestamp}</span>
                </div>
            `;
            
            if (message.message_type === 'file') {
                messageHTML += renderFileMessage(message);
            } else {
                messageHTML += `<p>${message.message}</p>`;
            }
            
            messageDiv.innerHTML = messageHTML;
            container.appendChild(messageDiv);
        });
        
        // Add event listeners for image clicks
        container.querySelectorAll('.image-preview img').forEach(img => {
            img.addEventListener('click', function() {
                openImageModal(this.src);
            });
        });
        
        // Smart scrolling logic
        const newScrollHeight = container.scrollHeight;
        const newMessageCount = messages.length;
        
        if (wasAtBottom) {
            // User was at bottom, so scroll to bottom to see new messages
            container.scrollTop = newScrollHeight;
        } else if (newMessageCount > lastMessageCount && lastMessageCount > 0) {
            // New messages arrived but user is not at bottom
            // Calculate how much the content grew and maintain position
            const heightDifference = newScrollHeight - oldScrollHeight;
            container.scrollTop = oldScrollTop + heightDifference;
        } else {
            // Same number of messages or loading initial messages
            // Maintain the current scroll position
            container.scrollTop = oldScrollTop;
        }
        
        lastMessageCount = newMessageCount;
        
    })
    .catch(error => {
        console.error('Error loading messages:', error);
        const container = document.getElementById('messagesContainer');
        if (container.innerHTML === '') {
            container.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
        }
    });
}

// Helper function to check if user is scrolled to bottom
function isScrolledToBottom(container) {
    const threshold = 100; // pixels from bottom to consider "at bottom"
    return container.scrollTop + container.clientHeight >= container.scrollHeight - threshold;
}

function updateAllUnreadCounts() {
    fetch('get_unread_count_per_user.php')
    .then(response => response.json())
    .then(data => {
        updatePerUserUnreadCounts(data);
        updateTotalUnreadCount(data);
    })
    .catch(error => {
        console.error('Error getting unread counts:', error);
    });
}

function updatePerUserUnreadCounts(data) {
    perUserUnreadCounts = {};
    
    // Update group chat badge
    const groupItem = document.querySelector('.user-item[data-userid="all"]');
    updateUserNotificationBadge(groupItem, data.group_unread, true);
    
    // Update individual user badges
    if (data.users && Array.isArray(data.users)) {
        data.users.forEach(user => {
            const userItem = document.querySelector(`.user-item[data-userid="${user.user_id}"]`);
            if (userItem) {
                updateUserNotificationBadge(userItem, user.unread_count, false);
                perUserUnreadCounts[user.user_id] = user.unread_count;
            }
        });
    }
}

function updateUserNotificationBadge(userItem, count, isGroup = false) {
    let badge = userItem.querySelector(isGroup ? '.group-notification-badge' : '.user-notification-badge');
    
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = isGroup ? 'group-notification-badge' : 'user-notification-badge';
            if (isGroup) {
                // For group, add badge after the text
                const textElement = userItem.querySelector('strong') || userItem;
                textElement.appendChild(badge);
            } else {
                // For users, add badge at the end
                userItem.appendChild(badge);
            }
        }
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else if (badge) {
        badge.style.display = 'none';
    }
}

function updateTotalUnreadCount(data) {
    let totalUnread = data.group_unread || 0;
    
    // Sum up all individual unread counts
    if (data.users && Array.isArray(data.users)) {
        data.users.forEach(user => {
            totalUnread += user.unread_count || 0;
        });
    }
    
    // Update header badge
    let headerBadge = document.getElementById('headerNotificationBadge');
    if (!headerBadge) {
        headerBadge = document.createElement('span');
        headerBadge.id = 'headerNotificationBadge';
        headerBadge.className = 'header-notification';
        document.querySelector('.chat-header h2').appendChild(headerBadge);
    }
    
    if (totalUnread > 0) {
        headerBadge.textContent = totalUnread > 99 ? '99+' : totalUnread;
        headerBadge.style.display = 'flex';
        document.title = `(${totalUnread}) Chat System`;
    } else {
        headerBadge.style.display = 'none';
        document.title = 'Chat System';
    }
}

function loadOnlineUsers() {
    fetch('get_online_users.php')
    .then(response => response.json())
    .then(users => {
        updateOnlineUsersUI(users);
    })
    .catch(error => {
        console.error('Error loading online users:', error);
    });
}

function updateOnlineUsersUI(onlineUsers) {
    const userItems = document.querySelectorAll('.user-item');
    const onlineUserIds = onlineUsers ? onlineUsers.map(user => user.id.toString()) : [];
    
    userItems.forEach(item => {
        const userId = item.dataset.userid;
        if (userId && userId !== 'all') {
            const statusElement = item.querySelector('.user-status') || createStatusElement(item);
            if (onlineUserIds.includes(userId)) {
                statusElement.className = 'user-status online-status';
            } else {
                statusElement.className = 'user-status offline-status';
            }
        }
    });
}

function createStatusElement(userItem) {
    const statusElement = document.createElement('span');
    statusElement.className = 'user-status offline-status';
    userItem.insertBefore(statusElement, userItem.firstChild);
    return statusElement;
}

function updateActivity() {
    fetch('update_activity.php')
    .then(response => response.json())
    .then(data => {
        // activity updated
    })
    .catch(error => {
        console.error('Error updating activity:', error);
    });
}

function markMessagesAsRead() {
    const formData = new FormData();
    formData.append('receiver_id', currentReceiver);
    
    fetch('mark_as_read.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Update the specific badge immediately
        if (currentReceiver === 'all') {
            updateUserNotificationBadge(
                document.querySelector('.user-item[data-userid="all"]'), 
                0, 
                true
            );
        } else {
            updateUserNotificationBadge(
                document.querySelector(`.user-item[data-userid="${currentReceiver}"]`), 
                0, 
                false
            );
        }
        
        updateAllUnreadCounts();
    })
    .catch(error => {
        console.error('Error marking messages as read:', error);
    });
}

function showNewMessageNotification() {
    let notification = document.getElementById('newMessageNotification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'newMessageNotification';
        notification.className = 'new-message-notification';
        notification.textContent = 'New message received!';
        document.body.appendChild(notification);
    }
    
    notification.style.display = 'block';
    playNotificationSound();
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
}

function playNotificationSound() {
    // To add a notification sound file
    // const audio = new Audio('notification.mp3');
    // audio.play().catch(e => console.log('Audio play failed:', e));
}

function renderFileMessage(message) {
    const fileExtension = getFileExtension(message.file_name);
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension);
    const isPDF = fileExtension === 'pdf';
    const isDocument = ['doc', 'docx', 'txt'].includes(fileExtension);
    const isArchive = ['zip', 'rar', '7z'].includes(fileExtension);
    
    let fileHTML = '<div class="file-message">';
    
    if (isImage) {
        fileHTML += `
            <div class="image-preview">
                <img src="${message.file_path}" alt="${message.file_name}" 
                     title="Click to view full size">
            </div>
        `;
    } else {
        let icon = '📄';
        if (isPDF) icon = '📕';
        else if (isDocument) icon = '📝';
        else if (isArchive) icon = '📦';
        else if (fileExtension === 'mp4' || fileExtension === 'avi' || fileExtension === 'mov') icon = '🎬';
        else if (fileExtension === 'mp3' || fileExtension === 'wav') icon = '🎵';
        
        fileHTML += `
            <div class="file-info">
                <span class="file-icon">${icon}</span>
                <div class="file-details">
                    <div class="file-name">${message.file_name}</div>
                    <div class="file-size">${formatFileSize(message.file_size)}</div>
                </div>
                <a href="${message.file_path}" download="${message.file_name}" 
                   style="margin-left: 10px; padding: 5px 10px; color:#ffff;" class="btn">
                   Download
                </a>
            </div>
        `;
    }
    
    if (message.message) {
        fileHTML += `<p style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef;">${message.message}</p>`;
    }
    
    fileHTML += '</div>';
    return fileHTML;
}

function getFileExtension(filename) {
    return filename ? filename.split('.').pop().toLowerCase() : '';
}

function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function openImageModal(src) {
    let modal = document.getElementById('imageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'imageModal';
        modal.className = 'image-modal';
        modal.innerHTML = `
            <span class="close-modal">&times;</span>
            <img class="modal-content" id="modalImage">
        `;
        document.body.appendChild(modal);
        
        modal.querySelector('.close-modal').addEventListener('click', closeImageModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeImageModal();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeImageModal();
        });
    }
    
    modal.style.display = 'flex';
    document.getElementById('modalImage').src = src;
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    if (modal) {
        modal.style.display = 'none';
    }
}