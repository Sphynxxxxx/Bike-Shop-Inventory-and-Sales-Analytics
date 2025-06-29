<?php
require_once "../backend/connections/config.php";

// Initialize variables
$success_message = "";
$error_message = "";
$products = [];
$recentSales = [];

// Check if PDO connection exists, if not create one
if (!isset($pdo)) {
    try {
        // Database configuration - adjust these values to match your setup
        $host = 'localhost';
        $dbname = 'bike_shop'; // Change this to your actual database name
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Database connection failed: " . $e->getMessage() . ". Please make sure the database exists and the connection details are correct.";
    }
}

// Handle delete request
if (isset($_GET['delete']) && isset($pdo)) {
    $saleId = (int)$_GET['delete'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get sale details before deleting (for inventory restoration)
        $saleStmt = $pdo->prepare("SELECT product_id, quantity FROM sales WHERE id = ?");
        $saleStmt->execute([$saleId]);
        $saleData = $saleStmt->fetch();
        
        if ($saleData) {
            // Delete the sale record
            $deleteStmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $deleteStmt->execute([$saleId]);
            
            // Restore inventory if product exists in inventory
            if (!empty($saleData['product_id'])) {
                $inventoryCheck = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE product_id = ?");
                $inventoryCheck->execute([$saleData['product_id']]);
                
                if ($inventoryCheck->fetchColumn() > 0) {
                    $restoreStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ?");
                    $restoreStmt->execute([$saleData['quantity'], $saleData['product_id']]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success_message = "Sale record deleted successfully and inventory restored!";
        } else {
            $error_message = "Sale record not found.";
        }
        
        // Redirect to clear the delete parameter
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error_message = "Error deleting sale: " . $e->getMessage();
    }
}

// Handle form submission
if ($_POST && isset($pdo)) {
    $date = $_POST['sale_date'];
    $product_category = $_POST['product_category'];
    $product_name = $_POST['product_name'];
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $total = $quantity * $price;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if product ID already exists in sales table
        if (!empty($product_id)) {
            $duplicateCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE product_id = ?");
            $duplicateCheckStmt->execute([$product_id]);
            $duplicateCount = $duplicateCheckStmt->fetchColumn();
            
            if ($duplicateCount > 0) {
                throw new Exception("Product ID '$product_id' already exists in sales records. Please use a unique Product ID.");
            }
        }
        
        // Check if product exists and has enough stock (if product_id is provided)
        if (!empty($product_id)) {
            $checkStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ?");
            $checkStmt->execute([$product_id]);
            $currentStock = $checkStmt->fetchColumn();
            
            if ($currentStock !== false && $currentStock < $quantity) {
                throw new Exception("Insufficient stock. Available: $currentStock, Requested: $quantity");
            }
        }
        
        // Insert into sales table
        $stmt = $pdo->prepare("INSERT INTO sales (sale_date, product_category, product_name, product_id, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$date, $product_category, $product_name, $product_id, $quantity, $price, $total]);
        
        // Update inventory - reduce stock (only if product_id exists in inventory)
        if (!empty($product_id)) {
            $updateCheck = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE product_id = ?");
            $updateCheck->execute([$product_id]);
            
            if ($updateCheck->fetchColumn() > 0) {
                $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?");
                $updateStmt->execute([$quantity, $product_id]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Sale recorded successfully!";
        
        // Refresh the page to clear form and show updated data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error_message = "Error recording sale: " . $e->getMessage();
    }
}

// Get recent sales for display
if (isset($pdo)) {
    try {
        $recentSalesStmt = $pdo->query("SELECT * FROM sales ORDER BY id DESC LIMIT 10");
        $recentSales = $recentSalesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet, so we'll just use empty array
        $recentSales = [];
    }
} else {
    $error_message = "Database connection not available. Please check your database configuration.";
}

// Get existing product IDs for JavaScript validation
$existingProductIds = [];
if (isset($pdo)) {
    try {
        $existingIdsStmt = $pdo->query("SELECT DISTINCT product_id FROM sales WHERE product_id IS NOT NULL AND product_id != ''");
        $existingProductIds = $existingIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Ignore error if table doesn't exist
        $existingProductIds = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Entry - Bike Shop</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 700;
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
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 12px 16px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #212529;
            box-shadow: 0 0 0 0.2rem rgba(33, 37, 41, 0.25);
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #dc3545;
        }
        
        .btn-primary {
            background-color: #212529;
            border-color: #212529;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #495057;
            border-color: #495057;
        }
        
        .btn-primary:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-danger {
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.875rem;
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
        
        .main-content {
            padding: 24px;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .total-display {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .delete-btn {
            color: #dc3545;
            background: none;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .delete-btn:hover {
            background-color: #dc3545;
            color: white;
        }
        
        .actions-column {
            width: 80px;
            text-align: center;
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: 12px;
            border: none;
        }
        
        .modal-header {
            border-bottom: 1px solid #f8f9fa;
        }
        
        .modal-footer {
            border-top: 1px solid #f8f9fa;
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
                <span class="ms-4 text-muted">Sales Entry</span>
            </div>
            
            <div class="d-flex align-items-center">
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-th-large me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="salesentry.php">
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
                    </ul>
                    
                    <a href="#" class="logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">Sales Entry</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Sales Entry</li>
                            </ol>
                        </nav>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($success_message) && !empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message) && !empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <!-- Sales Entry Form -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        New Sale Entry
                                    </h5>
                                    
                                    <form method="POST" id="salesForm">
                                        <div class="mb-3">
                                            <label for="sale_date" class="form-label">Sale Date</label>
                                            <input type="date" class="form-control" id="sale_date" name="sale_date" 
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="product_category" class="form-label">Product Category</label>
                                            <select class="form-select" id="product_category" name="product_category" required>
                                                <option value="">Select a category</option>
                                                <option value="Bikes">🚴 Bikes</option>
                                                <option value="Accessories">🔧 Accessories</option>
                                                <option value="Parts">⚙️ Parts</option>
                                                <option value="Safety">🛡️ Safety</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="product_name" class="form-label">Product Name</label>
                                            <input type="text" class="form-control" id="product_name" name="product_name" 
                                                   placeholder="Enter product name" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="product_id" class="form-label">Product ID</label>
                                            <input type="text" class="form-control" id="product_id" name="product_id" 
                                                   placeholder="Enter unique product ID" required>
                                            <div class="invalid-feedback" id="productIdError"></div>
                                            <div class="form-text">Product ID must be unique and not already exist in sales records.</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="quantity" class="form-label">Quantity</label>
                                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                                           min="1" placeholder="Enter quantity" required onchange="calculateTotal()">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="price" class="form-label">Unit Price</label>
                                                    <input type="number" class="form-control" id="price" name="price" 
                                                           step="0.01" min="0" placeholder="0.00" required onchange="calculateTotal()">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">Total Amount</label>
                                            <div class="total-display" id="total_display">₱0.00</div>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                            <i class="fas fa-save me-2"></i>
                                            Record Sale
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Sales -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">
                                        <i class="fas fa-history me-2"></i>
                                        Recent Sales
                                    </h5>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-borderless">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Product</th>
                                                    <th>Category</th>
                                                    <th>Qty</th>
                                                    <th>Total</th>
                                                    <th class="actions-column">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentSales)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-4">
                                                            No sales recorded yet
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($recentSales as $sale): ?>
                                                        <tr>
                                                            <td><?php echo date('M d', strtotime($sale['sale_date'])); ?></td>
                                                            <td>
                                                                <div class="fw-medium"><?php echo htmlspecialchars($sale['product_name']); ?></div>
                                                                <?php if (!empty($sale['product_id'])): ?>
                                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($sale['product_id']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                $category = isset($sale['product_category']) ? $sale['product_category'] : 'N/A';
                                                                $categoryIcons = [
                                                                    'Bikes' => '🚴',
                                                                    'Accessories' => '🔧',
                                                                    'Parts' => '⚙️',
                                                                    'Safety' => '🛡️'
                                                                ];
                                                                $icon = isset($categoryIcons[$category]) ? $categoryIcons[$category] : '📦';
                                                                echo '<span class="badge bg-light text-dark">' . $icon . ' ' . htmlspecialchars($category) . '</span>';
                                                                ?>
                                                            </td>
                                                            <td><?php echo $sale['quantity']; ?></td>
                                                            <td class="fw-medium">₱<?php echo number_format($sale['total'], 2); ?></td>
                                                            <td class="actions-column">
                                                                <button class="delete-btn" 
                                                                        onclick="confirmDelete(<?php echo $sale['id']; ?>, '<?php echo htmlspecialchars($sale['product_name'], ENT_QUOTES); ?>')"
                                                                        title="Delete Sale">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this sale record?</p>
                    <div class="alert alert-info">
                        <strong>Product:</strong> <span id="deleteProductName"></span><br>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            This action will also restore the sold quantity back to inventory (if applicable).
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>
                        Delete Sale
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store existing product IDs from PHP
        const existingProductIds = <?php echo json_encode($existingProductIds); ?>;
        
        function calculateTotal() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;
            const total = quantity * price;
            
            document.getElementById('total_display').textContent = '₱' + total.toFixed(2);
        }
        
        function validateProductId() {
            const productIdInput = document.getElementById('product_id');
            const productIdError = document.getElementById('productIdError');
            const submitBtn = document.getElementById('submitBtn');
            const productId = productIdInput.value.trim();
            
            // Clear previous validation
            productIdInput.classList.remove('is-invalid');
            productIdError.textContent = '';
            
            if (productId === '') {
                submitBtn.disabled = false;
                return true;
            }
            
            // Check if product ID already exists
            if (existingProductIds.includes(productId)) {
                productIdInput.classList.add('is-invalid');
                productIdError.textContent = 'This Product ID already exists. Please use a unique Product ID.';
                submitBtn.disabled = true;
                return false;
            }
            
            submitBtn.disabled = false;
            return true;
        }
        
        function confirmDelete(saleId, productName) {
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('confirmDeleteBtn').href = '?delete=' + saleId;
            
            // Store the product ID to remove from validation array after deletion
            const row = event.target.closest('tr');
            const productIdText = row.querySelector('small.text-muted')?.textContent;
            const productId = productIdText ? productIdText.replace('ID: ', '') : null;
            
            if (productId) {
                document.getElementById('confirmDeleteBtn').setAttribute('data-product-id', productId);
            }
            
            // Get or create modal instance
            const deleteModalElement = document.getElementById('deleteModal');
            let deleteModal = bootstrap.Modal.getInstance(deleteModalElement);
            
            if (!deleteModal) {
                deleteModal = new bootstrap.Modal(deleteModalElement);
            }
            
            deleteModal.show();
        }
        
        // Update existing product IDs array when delete is confirmed
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const productIdToRemove = this.getAttribute('data-product-id');
            if (productIdToRemove) {
                const index = existingProductIds.indexOf(productIdToRemove);
                if (index > -1) {
                    existingProductIds.splice(index, 1);
                }
            }
        });
        
        // Ensure modal is properly reset when hidden
        document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
            // Clear the modal data when it's hidden
            document.getElementById('deleteProductName').textContent = '';
            document.getElementById('confirmDeleteBtn').href = '#';
            document.getElementById('confirmDeleteBtn').removeAttribute('data-product-id');
        });
        
        // Add event listener for product ID validation
        document.getElementById('product_id').addEventListener('input', validateProductId);
        document.getElementById('product_id').addEventListener('blur', validateProductId);
        
        // Form submission validation
        document.getElementById('salesForm').addEventListener('submit', function(e) {
            if (!validateProductId()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Auto-calculate total when form loads
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            validateProductId();
        });
    </script>
</body>
</html>