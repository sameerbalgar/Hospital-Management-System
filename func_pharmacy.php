<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

if (!$con) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// -------------------------------------------------------------
// Auto-create Phase 5 tables if missing (suppliertb, pharmacisttb,
// medicine_stock_movements)
// -------------------------------------------------------------
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `pharmacisttb` (
  `pharmacist_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(50) NOT NULL,
  `contact` VARCHAR(15) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pharmacist_id`),
  UNIQUE KEY `username` (`username`),
  INDEX `idx_pharma_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS `suppliertb` (
  `supplier_id` INT(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  INDEX `idx_supplier_name` (`supplier_name`),
  INDEX `idx_supplier_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS `medicine_stock_movements` (
  `movement_id` INT(11) NOT NULL AUTO_INCREMENT,
  `medicine_id` INT(11) NOT NULL,
  `supplier_id` INT(11) DEFAULT NULL,
  `movement_type` VARCHAR(20) NOT NULL DEFAULT 'RESTOCK',
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `batch_no` VARCHAR(50) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `reference_note` VARCHAR(255) DEFAULT NULL,
  `performed_by` VARCHAR(50) NOT NULL,
  `movement_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  INDEX `idx_mov_medicine` (`medicine_id`),
  INDEX `idx_mov_supplier` (`supplier_id`),
  INDEX `idx_mov_type` (`movement_type`),
  INDEX `idx_mov_date` (`movement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

// Seed default suppliers if empty
$chk_sup_seed = mysqli_query($con, "SELECT supplier_id FROM suppliertb LIMIT 1");
if ($chk_sup_seed && mysqli_num_rows($chk_sup_seed) === 0) {
    mysqli_query($con, "INSERT INTO `suppliertb` (`supplier_id`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `status`) VALUES
    (1, 'MedLife Pharma Dist', 'Rajesh Sharma', 'orders@medlifepharma.com', '9811223344', '12 Industrial Area, Phase 1, Mumbai', 'Active'),
    (2, 'Apex Healthcare', 'Suresh Patel', 'contact@apexhealth.in', '9822334455', '45 Biotech Park, Ahmedabad', 'Active'),
    (3, 'Global Meds Wholesale', 'Anita Desai', 'sales@globalmeds.com', '9833445566', '78 Pharma Hub, Hyderabad', 'Active'),
    (4, 'Prime Distributors', 'Vikas Gupta', 'prime.dist@gmail.com', '9844556677', '23 Wholesale Market, Delhi', 'Active');");
}

// Seed default pharmacist if pharmacisttb is empty
$check_pharma = mysqli_query($con, "SELECT pharmacist_id FROM pharmacisttb LIMIT 1");
if ($check_pharma && mysqli_num_rows($check_pharma) === 0) {
    $default_user = 'pharmacist';
    $default_pass = password_hash('pharma123', PASSWORD_DEFAULT);
    $default_name = 'Head Pharmacist';
    $default_email = 'pharmacist@hospital.com';
    $default_contact = '9876543210';
    $stmt_seed = mysqli_prepare($con, "INSERT INTO pharmacisttb (username, password, name, email, contact, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    if ($stmt_seed) {
        mysqli_stmt_bind_param($stmt_seed, "sssss", $default_user, $default_pass, $default_name, $default_email, $default_contact);
        mysqli_stmt_execute($stmt_seed);
        mysqli_stmt_close($stmt_seed);
    }
}

// -------------------------------------------------------------
// Helper: Calculate medicine status dynamically
// -------------------------------------------------------------
if (!function_exists('calculate_medicine_status')) {
    function calculate_medicine_status($quantity, $expiry_date) {
        $today = date('Y-m-d');
        if ($expiry_date < $today) {
            return 'Expired';
        }
        if ($quantity <= 0) {
            return 'Out of Stock';
        }
        if ($quantity <= 10 && $quantity > 0) {
            return 'Low Stock';
        }
        return 'Available';
    }
}

// -------------------------------------------------------------
// 1. Pharmacist Login Handler
// -------------------------------------------------------------
if (isset($_POST['pharmasub'])) {
    $username = trim($_POST['pharmacist_username'] ?? '');
    $password = trim($_POST['pharmacist_password'] ?? '');

    if (empty($username) || empty($password)) {
        echo "<script>alert('Please enter both username and password.'); window.location.href = 'index.php';</script>";
        exit();
    }

    $stmt = mysqli_prepare($con, "SELECT pharmacist_id, username, password, name, status FROM pharmacisttb WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $authenticated = false;
        if (password_verify($password, $row['password'])) {
            $authenticated = true;
        } elseif ($row['password'] === $password) {
            // Rehash legacy plain text password if present
            $authenticated = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $rehash_stmt = mysqli_prepare($con, "UPDATE pharmacisttb SET password = ? WHERE pharmacist_id = ?");
            mysqli_stmt_bind_param($rehash_stmt, "si", $new_hash, $row['pharmacist_id']);
            mysqli_stmt_execute($rehash_stmt);
            mysqli_stmt_close($rehash_stmt);
        }

        if ($authenticated) {
            if ($row['status'] !== 'Active') {
                echo "<script>alert('Your pharmacist account is inactive. Please contact administrator.'); window.location.href = 'index.php';</script>";
                exit();
            }

            $_SESSION['pharmacist_id'] = $row['pharmacist_id'];
            $_SESSION['pharmacist_username'] = $row['username'];
            $_SESSION['pharmacist_name'] = $row['name'];

            header("Location: pharmacist-panel.php");
            exit();
        }
    }

    echo "<script>alert('Invalid Pharmacist Username or Password. Try again!'); window.location.href = 'index.php';</script>";
    exit();
}

// -------------------------------------------------------------
// 2. Pharmacist Logout Handler
// -------------------------------------------------------------
if (isset($_GET['logout_pharmacist'])) {
    unset($_SESSION['pharmacist_id']);
    unset($_SESSION['pharmacist_username']);
    unset($_SESSION['pharmacist_name']);
    header("Location: index.php");
    exit();
}

// -------------------------------------------------------------
// 3. Add Medicine Handler
// -------------------------------------------------------------
if (isset($_POST['add_medicine'])) {
    $med_name     = trim($_POST['medicine_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $batch_no     = trim($_POST['batch_no'] ?? '');
    $expiry_date  = trim($_POST['expiry_date'] ?? '');
    $quantity     = intval($_POST['quantity'] ?? 0);
    $unit_price   = floatval($_POST['unit_price'] ?? 0);
    $supplier     = trim($_POST['supplier'] ?? '');

    if (empty($med_name) || empty($batch_no) || empty($expiry_date) || $quantity < 0 || $unit_price < 0) {
        echo "<script>alert('Please fill all required medicine fields correctly.'); window.location.href = 'pharmacist-panel.php#list-addmed';</script>";
        exit();
    }

    $status = calculate_medicine_status($quantity, $expiry_date);

    $stmt = mysqli_prepare($con, "INSERT INTO medicinetb (medicine_name, generic_name, category, manufacturer, batch_no, expiry_date, quantity, unit_price, supplier, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssssidss", $med_name, $generic_name, $category, $manufacturer, $batch_no, $expiry_date, $quantity, $unit_price, $supplier, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo "<script>alert('Medicine added successfully!'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
        exit();
    } else {
        $error = mysqli_error($con);
        mysqli_stmt_close($stmt);
        echo "<script>alert('Failed to add medicine: " . addslashes($error) . "'); window.location.href = 'pharmacist-panel.php#list-addmed';</script>";
        exit();
    }
}

// -------------------------------------------------------------
// 4. Edit Medicine Handler
// -------------------------------------------------------------
if (isset($_POST['edit_medicine'])) {
    $med_id       = intval($_POST['medicine_id'] ?? 0);
    $med_name     = trim($_POST['medicine_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $batch_no     = trim($_POST['batch_no'] ?? '');
    $expiry_date  = trim($_POST['expiry_date'] ?? '');
    $quantity     = intval($_POST['quantity'] ?? 0);
    $unit_price   = floatval($_POST['unit_price'] ?? 0);
    $supplier     = trim($_POST['supplier'] ?? '');

    if ($med_id <= 0 || empty($med_name) || empty($batch_no) || empty($expiry_date)) {
        echo "<script>alert('Invalid medicine details provided.'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
        exit();
    }

    $status = calculate_medicine_status($quantity, $expiry_date);

    $stmt = mysqli_prepare($con, "UPDATE medicinetb SET medicine_name=?, generic_name=?, category=?, manufacturer=?, batch_no=?, expiry_date=?, quantity=?, unit_price=?, supplier=?, status=? WHERE medicine_id=?");
    mysqli_stmt_bind_param($stmt, "ssssssidssi", $med_name, $generic_name, $category, $manufacturer, $batch_no, $expiry_date, $quantity, $unit_price, $supplier, $status, $med_id);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo "<script>alert('Medicine updated successfully!'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
        exit();
    } else {
        $error = mysqli_error($con);
        mysqli_stmt_close($stmt);
        echo "<script>alert('Failed to update medicine: " . addslashes($error) . "'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
        exit();
    }
}

// -------------------------------------------------------------
// 5. Delete Medicine Handler
// -------------------------------------------------------------
if (isset($_GET['delete_medicine'])) {
    $med_id = intval($_GET['delete_medicine'] ?? 0);
    if ($med_id > 0) {
        $check_sales = mysqli_query($con, "SELECT item_id FROM pharmacy_sale_items WHERE medicine_id = $med_id LIMIT 1");
        if ($check_sales && mysqli_num_rows($check_sales) > 0) {
            echo "<script>alert('Cannot delete this medicine because it has existing sales records. You can update its quantity to 0 instead.'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
            exit();
        }
        $check_movs = mysqli_query($con, "SELECT movement_id FROM medicine_stock_movements WHERE medicine_id = $med_id LIMIT 1");
        if ($check_movs && mysqli_num_rows($check_movs) > 0) {
            echo "<script>alert('Cannot delete this medicine because it has historical stock purchase/dispense records. Do not delete; use the Restock tab to adjust stock instead.'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
            exit();
        }

        $stmt = mysqli_prepare($con, "DELETE FROM medicinetb WHERE medicine_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $med_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo "<script>alert('Medicine deleted successfully.'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
            exit();
        }
        mysqli_stmt_close($stmt);
    }
    echo "<script>alert('Unable to delete medicine.'); window.location.href = 'pharmacist-panel.php#list-med';</script>";
    exit();
}

// -------------------------------------------------------------
// 6. Dispense Medicine Handler (with MySQL Transaction)
// -------------------------------------------------------------
if (isset($_POST['dispense_submit'])) {
    $pid            = intval($_POST['pid'] ?? 0);
    $appointment_id = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
    $doctor_name    = trim($_POST['doctor_name'] ?? '');
    $payment_type   = trim($_POST['payment_type'] ?? 'Cash');
    $notes          = trim($_POST['notes'] ?? '');
    $pharmacist     = $_SESSION['pharmacist_username'] ?? 'pharmacist';

    $med_ids  = $_POST['med_id'] ?? [];
    $quantities = $_POST['dispense_qty'] ?? [];

    if ($pid <= 0) {
        echo "<script>alert('Please select a valid patient.'); window.location.href = 'pharmacist-panel.php#list-dispense';</script>";
        exit();
    }

    if (empty($med_ids) || !is_array($med_ids)) {
        echo "<script>alert('Please select at least one medicine to dispense.'); window.location.href = 'pharmacist-panel.php#list-dispense';</script>";
        exit();
    }

    // Verify patient exists in patreg
    $check_pat = mysqli_query($con, "SELECT pid, fname, lname FROM patreg WHERE pid = $pid LIMIT 1");
    if (!$check_pat || mysqli_num_rows($check_pat) === 0) {
        echo "<script>alert('Patient ID not found in system!'); window.location.href = 'pharmacist-panel.php#list-dispense';</script>";
        exit();
    }

    // Start Database Transaction
    mysqli_begin_transaction($con);

    try {
        $today = date('Y-m-d');
        $items_to_dispense = [];
        $total_bill_amount = 0.00;

        for ($i = 0; $i < count($med_ids); $i++) {
            $current_med_id = intval($med_ids[$i]);
            $current_qty    = intval($quantities[$i] ?? 0);

            if ($current_med_id <= 0 || $current_qty <= 0) {
                continue; // Skip blank rows
            }

            // Lock row for update to prevent concurrent overselling
            $stmt_med = mysqli_prepare($con, "SELECT medicine_id, medicine_name, quantity, unit_price, expiry_date, batch_no FROM medicinetb WHERE medicine_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_med, "i", $current_med_id);
            mysqli_stmt_execute($stmt_med);
            $res_med = mysqli_stmt_get_result($stmt_med);
            $med_row = mysqli_fetch_assoc($res_med);
            mysqli_stmt_close($stmt_med);

            if (!$med_row) {
                throw new Exception("Medicine with ID $current_med_id does not exist.");
            }

            if ($med_row['expiry_date'] < $today) {
                throw new Exception("Cannot dispense '" . $med_row['medicine_name'] . "' because it is EXPIRED (Expiry: " . $med_row['expiry_date'] . ").");
            }

            if ($med_row['quantity'] < $current_qty) {
                throw new Exception("Insufficient stock for '" . $med_row['medicine_name'] . "'. Requested: $current_qty, Available: " . $med_row['quantity'] . ".");
            }

            $unit_price = floatval($med_row['unit_price']);
            $subtotal   = $unit_price * $current_qty;
            $total_bill_amount += $subtotal;

            $items_to_dispense[] = [
                'medicine_id' => $current_med_id,
                'medicine_name' => $med_row['medicine_name'],
                'quantity' => $current_qty,
                'unit_price' => $unit_price,
                'subtotal' => $subtotal,
                'remaining_qty' => $med_row['quantity'] - $current_qty,
                'expiry_date' => $med_row['expiry_date'],
                'batch_no' => $med_row['batch_no']
            ];
        }

        if (empty($items_to_dispense)) {
            throw new Exception("No valid items were submitted for dispensing.");
        }

        // Insert Header into pharmacy_sales
        $stmt_sale = mysqli_prepare($con, "INSERT INTO pharmacy_sales (pid, appointment_id, doctor_name, pharmacist_username, total_amount, payment_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_sale, "iissdss", $pid, $appointment_id, $doctor_name, $pharmacist, $total_bill_amount, $payment_type, $notes);
        if (!mysqli_stmt_execute($stmt_sale)) {
            throw new Exception("Failed to record pharmacy sale: " . mysqli_stmt_error($stmt_sale));
        }
        $new_bill_id = mysqli_insert_id($con);
        mysqli_stmt_close($stmt_sale);

        // Insert items and deduct stock
        $stmt_item = mysqli_prepare($con, "INSERT INTO pharmacy_sale_items (bill_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt_upd  = mysqli_prepare($con, "UPDATE medicinetb SET quantity = ?, status = ? WHERE medicine_id = ?");
        $stmt_mov  = mysqli_prepare($con, "INSERT INTO medicine_stock_movements (medicine_id, supplier_id, movement_type, quantity, unit_price, batch_no, expiry_date, reference_note, performed_by) VALUES (?, NULL, 'DISPENSE', ?, ?, ?, ?, ?, ?)");

        foreach ($items_to_dispense as $item) {
            // Insert line item
            mysqli_stmt_bind_param($stmt_item, "iiidd", $new_bill_id, $item['medicine_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
            if (!mysqli_stmt_execute($stmt_item)) {
                throw new Exception("Failed to insert item " . $item['medicine_name']);
            }

            // Calculate new status
            $new_status = calculate_medicine_status($item['remaining_qty'], $item['expiry_date']);

            // Update medicine stock
            mysqli_stmt_bind_param($stmt_upd, "isi", $item['remaining_qty'], $new_status, $item['medicine_id']);
            if (!mysqli_stmt_execute($stmt_upd)) {
                throw new Exception("Failed to update stock for " . $item['medicine_name']);
            }

            // Record DISPENSE stock movement
            $ref_note = "Dispense Bill #$new_bill_id";
            mysqli_stmt_bind_param($stmt_mov, "iidssss", $item['medicine_id'], $item['quantity'], $item['unit_price'], $item['batch_no'], $item['expiry_date'], $ref_note, $pharmacist);
            if (!mysqli_stmt_execute($stmt_mov)) {
                throw new Exception("Failed to record stock movement for " . $item['medicine_name']);
            }
        }

        mysqli_stmt_close($stmt_item);
        mysqli_stmt_close($stmt_upd);
        mysqli_stmt_close($stmt_mov);

        // Commit transaction
        mysqli_commit($con);

        echo "<script>alert('Medicines successfully dispensed! Bill #" . $new_bill_id . " generated. Total: $" . number_format($total_bill_amount, 2) . "'); window.location.href = 'pharmacist-panel.php#list-sales';</script>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($con);
        echo "<script>alert('Dispense Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'pharmacist-panel.php#list-dispense';</script>";
        exit();
    }
}

// -------------------------------------------------------------
// 7. Medicine Stock Restock / Purchase Handler (Admin only)
// -------------------------------------------------------------
if (isset($_POST['admin_restock_submit'])) {
    $med_id         = intval($_POST['restock_medicine_id'] ?? 0);
    $supplier_id    = !empty($_POST['restock_supplier_id']) ? intval($_POST['restock_supplier_id']) : null;
    $movement_type  = trim($_POST['movement_type'] ?? 'RESTOCK');
    $quantity       = intval($_POST['restock_quantity'] ?? 0);
    $unit_price     = floatval($_POST['restock_unit_price'] ?? 0);
    $batch_no       = trim($_POST['restock_batch_no'] ?? '');
    $expiry_date    = trim($_POST['restock_expiry_date'] ?? '');
    $reference_note = trim($_POST['restock_reference_note'] ?? '');
    $performed_by   = $_SESSION['username'] ?? 'Admin';

    if ($med_id <= 0) {
        echo "<script>alert('Please select a valid medicine.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }
    if ($quantity <= 0) {
        echo "<script>alert('Restock quantity must be greater than 0.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }
    if ($unit_price < 0) {
        echo "<script>alert('Unit price cannot be negative.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }
    if (empty($batch_no)) {
        echo "<script>alert('Batch number is required.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }
    if (empty($expiry_date)) {
        echo "<script>alert('Valid expiry date is required.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }
    $today = date('Y-m-d');
    if ($expiry_date < $today) {
        echo "<script>alert('Cannot add stock with an expired date.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }

    $supplier_name = '';
    if ($supplier_id !== null && $supplier_id > 0) {
        $chk_sup = mysqli_query($con, "SELECT supplier_name FROM suppliertb WHERE supplier_id = $supplier_id LIMIT 1");
        if ($sup_row = mysqli_fetch_assoc($chk_sup)) {
            $supplier_name = $sup_row['supplier_name'];
        } else {
            echo "<script>alert('Selected supplier does not exist.'); window.location.href = 'admin-panel1.php#list-restock';</script>";
            exit();
        }
    }

    mysqli_begin_transaction($con);
    try {
        // Lock row for update
        $stmt_lck = mysqli_prepare($con, "SELECT * FROM medicinetb WHERE medicine_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt_lck, "i", $med_id);
        mysqli_stmt_execute($stmt_lck);
        $res_lck = mysqli_stmt_get_result($stmt_lck);
        $med_cur = mysqli_fetch_assoc($res_lck);
        mysqli_stmt_close($stmt_lck);

        if (!$med_cur) {
            throw new Exception("Medicine not found in database.");
        }

        // Record immutable stock movement
        $stmt_mov = mysqli_prepare($con, "INSERT INTO medicine_stock_movements (medicine_id, supplier_id, movement_type, quantity, unit_price, batch_no, expiry_date, reference_note, performed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_mov, "iisidssss", $med_id, $supplier_id, $movement_type, $quantity, $unit_price, $batch_no, $expiry_date, $reference_note, $performed_by);
        if (!mysqli_stmt_execute($stmt_mov)) {
            throw new Exception("Failed to record stock movement: " . mysqli_stmt_error($stmt_mov));
        }
        mysqli_stmt_close($stmt_mov);

        // Update medicine stock
        $new_quantity = $med_cur['quantity'] + $quantity;

        // Batch / Expiry handling:
        // Update primary active batch if old batch was 0 or expired, or preserve earliest active batch
        $active_batch  = $med_cur['batch_no'];
        $active_expiry = $med_cur['expiry_date'];
        $active_price  = $med_cur['unit_price'];
        $active_sup    = $med_cur['supplier'];

        if ($med_cur['quantity'] <= 0 || $med_cur['expiry_date'] < $today) {
            $active_batch  = $batch_no;
            $active_expiry = $expiry_date;
            if ($unit_price > 0) $active_price = $unit_price;
            if (!empty($supplier_name)) $active_sup = $supplier_name;
        }

        $new_status = calculate_medicine_status($new_quantity, $active_expiry);

        $stmt_upd = mysqli_prepare($con, "UPDATE medicinetb SET quantity = ?, batch_no = ?, expiry_date = ?, unit_price = ?, supplier = ?, status = ? WHERE medicine_id = ?");
        mysqli_stmt_bind_param($stmt_upd, "issdssi", $new_quantity, $active_batch, $active_expiry, $active_price, $active_sup, $new_status, $med_id);
        if (!mysqli_stmt_execute($stmt_upd)) {
            throw new Exception("Failed to update medicine inventory: " . mysqli_stmt_error($stmt_upd));
        }
        mysqli_stmt_close($stmt_upd);

        mysqli_commit($con);
        echo "<script>alert('Stock successfully added! " . addslashes($med_cur['medicine_name']) . " stock increased from " . $med_cur['quantity'] . " to " . $new_quantity . " units.'); window.location.href = 'admin-panel1.php#list-movements';</script>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($con);
        echo "<script>alert('Restock Error: " . addslashes($e->getMessage()) . "'); window.location.href = 'admin-panel1.php#list-restock';</script>";
        exit();
    }
}

// -------------------------------------------------------------
// 8. Add Supplier Handler
// -------------------------------------------------------------
if (isset($_POST['add_supplier_submit'])) {
    $sname    = trim($_POST['supplier_name'] ?? '');
    $cperson  = trim($_POST['contact_person'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $status   = trim($_POST['status'] ?? 'Active');

    if (empty($sname)) {
        echo "<script>alert('Supplier name is required.'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
        exit();
    }

    $dup_check = mysqli_prepare($con, "SELECT supplier_id FROM suppliertb WHERE LOWER(supplier_name) = LOWER(?)");
    mysqli_stmt_bind_param($dup_check, "s", $sname);
    mysqli_stmt_execute($dup_check);
    mysqli_stmt_store_result($dup_check);
    if (mysqli_stmt_num_rows($dup_check) > 0) {
        mysqli_stmt_close($dup_check);
        echo "<script>alert('A supplier with this name already exists.'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
        exit();
    }
    mysqli_stmt_close($dup_check);

    $stmt_s = mysqli_prepare($con, "INSERT INTO suppliertb (supplier_name, contact_person, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_s, "ssssss", $sname, $cperson, $email, $phone, $address, $status);
    if (mysqli_stmt_execute($stmt_s)) {
        echo "<script>alert('Supplier registered successfully!'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
    } else {
        echo "<script>alert('Failed to add supplier: " . addslashes(mysqli_error($con)) . "'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
    }
    mysqli_stmt_close($stmt_s);
    exit();
}

// -------------------------------------------------------------
// 9. Edit Supplier Handler
// -------------------------------------------------------------
if (isset($_POST['edit_supplier_submit'])) {
    $sid      = intval($_POST['edit_supplier_id'] ?? 0);
    $sname    = trim($_POST['edit_supplier_name'] ?? '');
    $cperson  = trim($_POST['edit_contact_person'] ?? '');
    $email    = trim($_POST['edit_email'] ?? '');
    $phone    = trim($_POST['edit_phone'] ?? '');
    $address  = trim($_POST['edit_address'] ?? '');
    $status   = trim($_POST['edit_status'] ?? 'Active');

    if ($sid > 0 && !empty($sname)) {
        $dup_u = mysqli_prepare($con, "SELECT supplier_id FROM suppliertb WHERE LOWER(supplier_name) = LOWER(?) AND supplier_id != ?");
        mysqli_stmt_bind_param($dup_u, "si", $sname, $sid);
        mysqli_stmt_execute($dup_u);
        mysqli_stmt_store_result($dup_u);
        if (mysqli_stmt_num_rows($dup_u) > 0) {
            mysqli_stmt_close($dup_u);
            echo "<script>alert('Another supplier with this name already exists.'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
            exit();
        }
        mysqli_stmt_close($dup_u);

        $stmt_u = mysqli_prepare($con, "UPDATE suppliertb SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ? WHERE supplier_id = ?");
        mysqli_stmt_bind_param($stmt_u, "ssssssi", $sname, $cperson, $email, $phone, $address, $status, $sid);
        if (mysqli_stmt_execute($stmt_u)) {
            echo "<script>alert('Supplier updated successfully!'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
        } else {
            echo "<script>alert('Failed to update supplier.'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
        }
        mysqli_stmt_close($stmt_u);
        exit();
    }
}

// -------------------------------------------------------------
// 10. Toggle Supplier Status
// -------------------------------------------------------------
if (isset($_GET['toggle_supplier'])) {
    $sid = intval($_GET['toggle_supplier']);
    $res = mysqli_query($con, "SELECT status FROM suppliertb WHERE supplier_id = $sid");
    if ($r = mysqli_fetch_assoc($res)) {
        $new_st = ($r['status'] === 'Active') ? 'Inactive' : 'Active';
        mysqli_query($con, "UPDATE suppliertb SET status = '$new_st' WHERE supplier_id = $sid");
        echo "<script>alert('Supplier marked as " . $new_st . "'); window.location.href = 'admin-panel1.php#list-suppliers';</script>";
    }
    exit();
}
?>
