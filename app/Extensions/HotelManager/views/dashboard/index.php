<?php
// views/dashboard/index.php
// This assumes the controller has passed the variables
?>
<div class="hotel-manager-dashboard">
    <h1>Hotel Manager Dashboard</h1>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Today's Check-ins</h3>
            <p><?= $todayCheckIns ?></p>
        </div>
        <div class="stat-card">
            <h3>Today's Revenue</h3>
            <p>R <?= number_format($todayRevenue, 2) ?></p>
        </div>
        <div class="stat-card">
            <h3>Occupancy</h3>
            <p><?= $occupiedRooms ?>/<?= $totalRooms ?> (<?= round(($occupiedRooms / $totalRooms) * 100, 1) ?>%)</p>
        </div>
    </div>

    <div class="dashboard-actions">
        <a href="/admin/hotel-manager/reservations" class="btn-primary">Manage Reservations</a>
        <a href="/admin/hotel-manager/inventory" class="btn-secondary">Manage Inventory</a>
        <a href="/admin/hotel-manager/pos" class="btn-secondary">POS Terminal</a>
    </div>
</div>