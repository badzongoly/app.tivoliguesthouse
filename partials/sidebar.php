<div class="sidebar">
<div class="d-flex align-items-center mb-3">


<div class="h4 m-0">
<img src="img/Tivoli.png" alt="Girl in a jacket" width="120" height="120">    
</div>
</div>
<div class="list-group">
<a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Dashboard</a>
<a href="bookings.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Manage Bookings</a>
<a href="rooms.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Manage Rooms</a>
<a href="extras.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Manage Extras</a>
<a href="extra_sales.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">View Sales Report</a>
<a href="stock.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Add Stock</a>
<?php if ($user['role'] == 'admin') { ?>
<a href="reports.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Reports</a>
<a href="payments.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Payments</a>
<a href="expenses.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Expenses</a>
<a href="users.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Users</a>
<a href="admin_recovery.php" class="list-group-item list-group-item-action bg-transparent text-dark border-0">Historical Recovery</a>
<?php } ?>

</div> 

<div class="text-secondary small mt-4">Logged in as <?php echo htmlspecialchars($user['name'] ?? ''); ?></div>
</div>