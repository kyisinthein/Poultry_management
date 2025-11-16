// Dashboard specific functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard initialized');
    updateDashboardNotification();
    
    // Update badge every 5 seconds
    setInterval(updateDashboardNotification, 5000);
});

function updateDashboardNotification() {
    fetch('get_total_unread.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.getElementById('headerNotification');
        if (badge) {
            if (data.total_unread > 0) {
                badge.textContent = data.total_unread > 99 ? '99+' : data.total_unread;
                badge.style.display = 'flex';
                
                // Also update browser tab title
                document.title = `(${data.total_unread}) Polutry Dashboard`;
            } else {
                badge.style.display = 'none';
                document.title = 'Polutry Dashboard';
            }
        }
    })
    .catch(error => {
        console.error('Error updating dashboard notification:', error);
    });
}