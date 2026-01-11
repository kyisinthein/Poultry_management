<?php
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all farms
$farms = fetchAll("SELECT * FROM farms ORDER BY farm_no ASC");

// Get current user data
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="my">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Poultry Dashboard</title>
  <link rel="stylesheet" href="./assets/css/dashboard.css">
</head>
<body>
<?php include('navbar.php'); ?>
  <div class="container">
    <!-- Search bar -->
    <div class="search-bar">
      <input type="text" id="farmSearch" placeholder="ခြံအမှတ်စဉ် သို့မဟုတ် အမည်ဖြင့် ရှာဖွေရန်...">
      <button onclick="searchFarms()"><i class="fa fa-search"></i></button>
    </div>
    
    <h1 class="dtitle">စာရင်းရှိခြံစာရင်း (<?php echo count($farms); ?> ခြံ)</h1>
   
    <div class="grid" id="farmGrid">
      <?php foreach ($farms as $farm): ?>
        <div class="card" onclick="selectFarm(<?php echo $farm['id']; ?>)">
          <div class="leftbox">
            <span class="left">ခြံ</span>
            <span class="bottom">(<?php echo $farm['farm_no']; ?>)</span>
          </div>
          <span class="text"><?php echo htmlspecialchars($farm['farm_username']); ?></span>
          <!-- <span class="badge"></span> -->
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    function selectFarm(farmId) {
      // Store selected farm in session and redirect to sell.php
      window.location.href = `sell.php?farm_id=${farmId}`;
    }

    function searchFarms() {
      const searchTerm = document.getElementById('farmSearch').value.toLowerCase();
      const farmCards = document.querySelectorAll('.card');
      
      farmCards.forEach(card => {
        const farmNumber = card.querySelector('.bottom').textContent.toLowerCase();
        const farmName = card.querySelector('.text').textContent.toLowerCase();
        
        if (farmNumber.includes(searchTerm) || farmName.includes(searchTerm)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Enable search on Enter key
    document.getElementById('farmSearch').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        searchFarms();
      }
    });
  </script>
</body>
</html>