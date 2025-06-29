<?php
require_once "../backend/connections/config.php";

// Initialize variables
$salesOverview = ['total_sales' => 0, 'total_revenue' => 0, 'sales_change' => 0];
$inventoryOverview = ['total_products' => 0, 'total_stock' => 0];
$revenueChange = 0;
$inventoryChange = 0;
$monthlySales = [];
$salesDistribution = [];
$recentPurchases = [];
$stockAlerts = [];

// Check if PDO connection exists
if (!isset($pdo)) {
    try {
        $host = 'localhost';
        $dbname = 'bike_shop';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Connection failed - use default values
    }
}

// Get selected year from URL parameter or default to current year
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Fetch data if connection exists
if (isset($pdo)) {
    try {
        // Get available years from sales data
        $availableYearsStmt = $pdo->query("
            SELECT DISTINCT YEAR(sale_date) as year 
            FROM sales 
            WHERE sale_date IS NOT NULL 
            ORDER BY year DESC
        ");
        $availableYears = $availableYearsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no sales data, include current year
        if (empty($availableYears)) {
            $availableYears = [date('Y')];
        } elseif (!in_array(date('Y'), $availableYears)) {
            $availableYears[] = date('Y');
            rsort($availableYears);
        }
        
        // Sales Overview for selected year
        $salesStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(total), 0) as total_revenue
            FROM sales 
            WHERE YEAR(sale_date) = ?
        ");
        $salesStmt->execute([$selectedYear]);
        $salesOverview = $salesStmt->fetch();
        
        // Calculate sales change (current month vs previous month for selected year)
        $currentMonthStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as current_month
            FROM sales 
            WHERE YEAR(sale_date) = ? 
            AND MONTH(sale_date) = MONTH(CURDATE())
        ");
        $currentMonthStmt->execute([$selectedYear]);
        $currentMonth = $currentMonthStmt->fetchColumn();
        
        $previousMonthStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as previous_month
            FROM sales 
            WHERE YEAR(sale_date) = ? 
            AND MONTH(sale_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)
        ");
        $previousMonthStmt->execute([$selectedYear]);
        $previousMonth = $previousMonthStmt->fetchColumn();
        
        $revenueChange = $previousMonth > 0 ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1) : 0;
        $salesOverview['sales_change'] = abs($revenueChange);
        
        // Inventory Overview
        $inventoryStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_products,
                COALESCE(SUM(quantity), 0) as total_stock
            FROM inventory
        ");
        $inventoryOverview = $inventoryStmt->fetch();
        

        
        // Monthly Sales Data for Chart (selected year)
        $monthlySalesStmt = $pdo->prepare("
            SELECT 
                MONTH(sale_date) as month_num,
                DATE_FORMAT(sale_date, '%b') as month,
                COALESCE(SUM(total), 0) as sales,
                COALESCE(SUM(quantity), 0) as stocks
            FROM sales 
            WHERE YEAR(sale_date) = ?
            GROUP BY MONTH(sale_date), DATE_FORMAT(sale_date, '%b')
            ORDER BY MONTH(sale_date) ASC
        ");
        $monthlySalesStmt->execute([$selectedYear]);
        $monthlySales = $monthlySalesStmt->fetchAll();
        
        // Sales Distribution by Category (selected year)
        $salesDistributionStmt = $pdo->prepare("
            SELECT 
                COALESCE(product_category, 'Uncategorized') as category,
                COALESCE(SUM(total), 0) as total
            FROM sales
            WHERE YEAR(sale_date) = ?
            AND product_category IS NOT NULL
            GROUP BY product_category
            ORDER BY total DESC
        ");
        $salesDistributionStmt->execute([$selectedYear]);
        $salesDistribution = $salesDistributionStmt->fetchAll();
        
        // Recent Sales (Updated to use product_category from sales table)
        $recentSalesStmt = $pdo->query("
            SELECT 
                sale_date as purchase_date,
                product_name as product,
                product_category as category,
                quantity,
                total as total_amount,
                product_id
            FROM sales
            ORDER BY id DESC
            LIMIT 10
        ");
        $recentPurchases = $recentSalesStmt->fetchAll();
        
        // Stock Alerts (Low Stock Items) - Updated to use proper column names
        $stockAlertsStmt = $pdo->query("
            SELECT 
                product_id as sku,
                product_name as product,
                quantity
            FROM inventory
            WHERE quantity <= 20
            ORDER BY quantity ASC
            LIMIT 10
        ");
        $stockAlerts = $stockAlertsStmt->fetchAll();
        
    } catch (Exception $e) {
        // If queries fail, use default empty values
        $availableYears = [date('Y')];
    }
} else {
    $availableYears = [date('Y')];
}

// Ensure we have default values if no data
if (empty($monthlySales)) {
    $monthlySales = [];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    foreach ($months as $index => $month) {
        $monthlySales[] = ['month_num' => $index + 1, 'month' => $month, 'sales' => 0, 'stocks' => 0];
    }
} else {
    // Fill in missing months with zero values
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $existingMonths = array_column($monthlySales, 'month_num');
    $fullYearData = [];
    
    foreach ($months as $index => $month) {
        $monthNum = $index + 1;
        $existingIndex = array_search($monthNum, $existingMonths);
        if ($existingIndex !== false) {
            $fullYearData[] = $monthlySales[$existingIndex];
        } else {
            $fullYearData[] = ['month_num' => $monthNum, 'month' => $month, 'sales' => 0, 'stocks' => 0];
        }
    }
    $monthlySales = $fullYearData;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Shop Inventory and Sales Analytics</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 700;
        }
        
        .brand-icon {
            background: #000;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            border-right: 1px solid #dee2e6;
            padding: 0;
        }
        
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 8px;
            margin: 2px 0;
            padding: 12px 16px;
            font-weight: 500;
        }
        
        .nav-pills .nav-link.active {
            background-color: #212529;
            color: white;
        }
        
        .nav-pills .nav-link:hover:not(.active) {
            background-color: #f8f9fa;
            color: #212529;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 12px;
        }
        
        .card-dark {
            background: linear-gradient(135deg, #212529 0%, #495057 100%);
            color: white;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .card-dark .stat-icon {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .text-success-custom {
            color: #198754 !important;
        }
        
        .text-danger-custom {
            color: #dc3545 !important;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 24px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            margin-right: 8px;
        }
        
        .table-responsive {
            border-radius: 8px;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #6c757d;
            font-size: 0.875rem;
            padding: 16px 12px;
        }
        
        .table td {
            padding: 16px 12px;
            vertical-align: middle;
            border-top: 1px solid #f8f9fa;
        }
        
        .badge-critical {
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: 500;
        }
        
        .badge-low {
            background-color: #fef3c7;
            color: #d97706;
            font-weight: 500;
        }
        
        .category-badge {
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .view-more {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .view-more:hover {
            color: #212529;
        }
        
        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .logout-btn:hover {
            color: #212529;
        }
        
        .search-container {
            position: relative;
            width: 320px;
        }
        
        .search-container .fa-search {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-input {
            padding-left: 40px;
        }
        
        .main-content {
            padding: 24px;
        }
        
        @media (max-width: 768px) {
            .search-container {
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <div class="navbar-brand d-flex align-items-center">
                <img src="assets/logo/logo.png" alt="Bike Shop Logo" class="me-3" style="width: 40px; height: 40px; object-fit: contain;">
                <span>Bike Shop Inventory and Sales Analytics</span>
                <span class="ms-4 text-muted">Dashboard</span>
            </div>
            
            <div class="d-flex align-items-center">
                <div class="search-container me-3">
                    <i class="fas fa-search"></i>
                    <input type="text" class="form-control search-input" placeholder="Search for anything here..">
                </div>
                <button class="btn btn-link text-muted me-2">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="btn btn-link text-muted me-2">
                    <i class="fas fa-cog"></i>
                </button>
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="fas fa-user text-white"></i>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="position-relative h-100">
                    <ul class="nav nav-pills flex-column p-3">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-th-large me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="salesentry.php">
                                <i class="fas fa-shopping-cart me-2"></i> Sales Entry
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="setActiveTab(this)">
                                <i class="fas fa-history me-2"></i> Stock History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="setActiveTab(this)">
                                <i class="fas fa-boxes me-2"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-concierge-bell me-2"></i> Services
                            </a>
                        </li>
                    </ul>

                    <a href="auth/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>


            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Stats Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-subtitle mb-2 text-muted">Total Sales</h6>
                                            <h4 class="card-title mb-1"><?php echo number_format($salesOverview['total_sales']); ?></h4>
                                            <small class="text-success-custom">+<?php echo abs($salesOverview['sales_change']); ?>%</small>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                                            <h4 class="card-title mb-1">₱<?php echo number_format($salesOverview['total_revenue'], 2); ?></h4>
                                            <small class="<?php echo $revenueChange >= 0 ? 'text-success-custom' : 'text-danger-custom'; ?>">
                                                <?php echo ($revenueChange >= 0 ? '+' : '') . $revenueChange; ?>%
                                            </small>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-peso-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-subtitle mb-2 text-muted">Total Products</h6>
                                            <h4 class="card-title mb-1"><?php echo number_format($inventoryOverview['total_products']); ?></h4>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-subtitle mb-2 text-muted">Total Stock</h6>
                                            <h4 class="card-title mb-1"><?php echo number_format($inventoryOverview['total_stock']); ?></h4>
                                            <small class="text-danger-custom"><?php echo $inventoryChange; ?>%</small>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="fas fa-cubes"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-8 col-sm-12">
                            <div class="card card-dark h-100">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Inventory Insights</h5>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-light opacity-75">Low Stock Items</small>
                                            <h4 class="mb-0"><?php echo count($stockAlerts); ?></h4>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-light opacity-75">Categories</small>
                                            <h4 class="mb-0"><?php echo count($salesDistribution); ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row g-4 mb-4">
                        <!-- Sales & Inventory Comparison -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">Monthly Sales Trend</h5>
                                        <select class="form-select form-select-sm" style="width: auto;" id="salesYearSelect" onchange="changeYear(this.value)">
                                            <?php foreach ($availableYears as $year): ?>
                                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                                                    <?php echo $year; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="d-flex mb-3">
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #212529;"></div>
                                            <small class="text-muted">Sales Revenue (₱)</small>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #adb5bd;"></div>
                                            <small class="text-muted">Items Sold</small>
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="salesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sales Distribution -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">Sales by Category</h5>
                                        <select class="form-select form-select-sm" style="width: auto;" id="categoryYearSelect" onchange="changeYear(this.value)">
                                            <?php foreach ($availableYears as $year): ?>
                                                <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                                                    <?php echo $year; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="chart-container">
                                        <canvas id="distributionChart"></canvas>
                                    </div>
                                    <div class="row mt-3">
                                        <?php 
                                        $colors = ['#212529', '#6c757d', '#adb5bd', '#ced4da'];
                                        foreach (array_slice($salesDistribution, 0, 4) as $index => $category): 
                                        ?>
                                        <div class="col-6">
                                            <div class="legend-item mb-2">
                                                <div class="legend-color" style="background-color: <?php echo $colors[$index]; ?>;"></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($category['category']); ?></small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tables Row -->
                    <div class="row g-4">
                        <!-- Recent Sales -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">Recent Sales</h5>
                                        <a href="salesentry.php" class="view-more">
                                            View More <i class="fas fa-eye ms-1"></i>
                                        </a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Date</th>
                                                    <th>Product</th>
                                                    <th>Category</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentPurchases)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No recent sales found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($recentPurchases as $index => $purchase): ?>
                                                        <tr>
                                                            <td><?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></td>
                                                            <td><?php echo date('M d', strtotime($purchase['purchase_date'])); ?></td>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($purchase['product']); ?></div>
                                                                <?php if (!empty($purchase['product_id'])): ?>
                                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($purchase['product_id']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($purchase['category'])): ?>
                                                                    <span class="category-badge"><?php echo htmlspecialchars($purchase['category']); ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>₱<?php echo number_format($purchase['total_amount'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Alert -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">Stock Alert</h5>
                                        <a href="#" class="view-more">
                                            View More <i class="fas fa-eye ms-1"></i>
                                        </a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th>
                                                    <th>Product</th>
                                                    <th>Quantity</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($stockAlerts)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-4">No stock alerts</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($stockAlerts as $alert): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($alert['sku'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars($alert['product']); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo $alert['quantity'] <= 5 ? 'badge-critical' : 'badge-low'; ?>">
                                                                    <?php echo $alert['quantity']; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Year change functionality
        function changeYear(selectedYear) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('year', selectedYear);
            window.location.href = currentUrl.toString();
        }
        
        // Navigation functionality
        function setActiveTab(clickedTab) {
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Add active class to clicked tab
            clickedTab.classList.add('active');
        }
        
        // Initialize charts with dynamic data
        document.addEventListener('DOMContentLoaded', function() {
            // Sync both year selectors
            const selectedYear = <?php echo $selectedYear; ?>;
            document.getElementById('salesYearSelect').value = selectedYear;
            document.getElementById('categoryYearSelect').value = selectedYear;
            
            // Sales Chart Data (from PHP)
            const salesData = <?php echo json_encode($monthlySales); ?>;
            const months = salesData.map(item => item.month);
            const salesValues = salesData.map(item => parseFloat(item.sales) || 0);
            const stockValues = salesData.map(item => parseFloat(item.stocks) || 0);
            
            // Sales & Inventory Comparison Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Sales Revenue (₱)',
                            data: salesValues,
                            backgroundColor: '#212529',
                            borderRadius: 4,
                            borderSkipped: false,
                        },
                        {
                            label: 'Items Sold',
                            data: stockValues,
                            backgroundColor: '#adb5bd',
                            borderRadius: 4,
                            borderSkipped: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f8f9fa',
                                drawBorder: false
                            },
                            ticks: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                color: '#6c757d',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Revenue: ₱' + context.parsed.y.toLocaleString();
                                    } else {
                                        return 'Items Sold: ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        }
                    },
                    elements: {
                        bar: {
                            borderWidth: 0
                        }
                    }
                }
            });
            
            // Sales Distribution Data (from PHP)
            const distributionData = <?php echo json_encode($salesDistribution); ?>;
            const categories = distributionData.length > 0 ? distributionData.map(item => item.category) : ['No Data'];
            const categoryValues = distributionData.length > 0 ? distributionData.map(item => parseFloat(item.total) || 0) : [1];
            const colors = ['#212529', '#6c757d', '#adb5bd', '#ced4da', '#e9ecef'];
            
            // Ensure we have enough colors for all categories
            const chartColors = colors.slice(0, categories.length);
            if (chartColors.length < categories.length) {
                const additionalColors = ['#f8f9fa', '#dee2e6', '#6f42c1', '#e83e8c', '#fd7e14'];
                chartColors.push(...additionalColors.slice(0, categories.length - chartColors.length));
            }
            
            // Sales Distribution Chart
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: categories,
                    datasets: [{
                        data: categoryValues,
                        backgroundColor: chartColors,
                        borderWidth: 0,
                        cutout: '60%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ₱' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>