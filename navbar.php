<nav class="navbar">
        <div class="user-info">
            <img src="./assets/profile_pics/<?php echo $user['profile_pic']; ?>" 
                 alt="Profile" class="profile-pic"
                 onerror="this.src='./assets/profile_pics/default_avatar.jpg'">
            <div class="user-details">
            <h3>မင်္ဂလာပါ <?php echo htmlspecialchars($user['username']); ?></h3>
            <p>သင့်ရဲ့ Dashboard မှကြိုဆိုပါတယ်..</p>
               </div>
        </div>
        
        <div class="nav-links">
            <a href="dashboard.php">ပင်မစာမျက်နှာ</a>
            <a href="profile.php">Profile ပြင်ဆင်ရန်</a>
            <a href="dashboard2.php">စာရင်းပြုစုရန်</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="usermanagement.php">Users Management</a>
            <?php endif; ?>
            <a class="logout" href="logout.php">ထွက်မည် <img class="logoutimg" src="./assets/images/logout.png" alt=""> </a>
        </div>
    </nav>