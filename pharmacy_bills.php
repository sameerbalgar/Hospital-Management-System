<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$con = mysqli_connect("localhost", "root", "", "myhmsdb");

if (!$con) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$pid = $_SESSION['pid'] ?? '';

if (empty($pid)) {
    header("Location: index1.php");
    exit();
}

$pp_user    = $_SESSION['username'] ?? ($_SESSION['fname'] ?? 'Patient');
$pp_initial = strtoupper(substr(trim($_SESSION['fname'] ?? 'P'), 0, 1));

$view_bill_id = isset($_GET['bill_id']) ? intval($_GET['bill_id']) : 0;

// -------------------------------------------------------------
// Load a single bill (only if it belongs to the logged-in patient)
// -------------------------------------------------------------
$bill = null;
$bill_items = [];
if ($view_bill_id > 0) {
    $stmt = mysqli_prepare($con, "SELECT s.*, CONCAT(p.fname, ' ', p.lname) AS patient_name
                                  FROM pharmacy_sales s
                                  LEFT JOIN patreg p ON s.pid = p.pid
                                  WHERE s.bill_id = ? AND s.pid = ?");
    mysqli_stmt_bind_param($stmt, "ii", $view_bill_id, $pid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $bill = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($bill) {
        $stmt_i = mysqli_prepare($con, "SELECT m.medicine_name, m.generic_name, si.quantity, si.unit_price, si.subtotal
                                        FROM pharmacy_sale_items si
                                        JOIN medicinetb m ON si.medicine_id = m.medicine_id
                                        WHERE si.bill_id = ? ORDER BY si.item_id ASC");
        mysqli_stmt_bind_param($stmt_i, "i", $view_bill_id);
        mysqli_stmt_execute($stmt_i);
        $res_i = mysqli_stmt_get_result($stmt_i);
        while ($item = mysqli_fetch_assoc($res_i)) {
            $bill_items[] = $item;
        }
        mysqli_stmt_close($stmt_i);
    }
}

// -------------------------------------------------------------
// Load all bills for the logged-in patient (list view)
// -------------------------------------------------------------
$bills = [];
$items_by_bill = [];
if ($view_bill_id === 0) {
    $stmt_b = mysqli_prepare($con, "SELECT bill_id, sale_date, doctor_name, pharmacist_username, total_amount, payment_type, notes
                                    FROM pharmacy_sales
                                    WHERE pid = ? ORDER BY bill_id DESC");
    mysqli_stmt_bind_param($stmt_b, "i", $pid);
    mysqli_stmt_execute($stmt_b);
    $res_b = mysqli_stmt_get_result($stmt_b);
    while ($b = mysqli_fetch_assoc($res_b)) {
        $bills[] = $b;
    }
    mysqli_stmt_close($stmt_b);

    if (count($bills) > 0) {
        $stmt_i = mysqli_prepare($con, "SELECT si.bill_id, m.medicine_name, m.generic_name, si.quantity, si.unit_price, si.subtotal
                                        FROM pharmacy_sale_items si
                                        JOIN pharmacy_sales s ON si.bill_id = s.bill_id AND s.pid = ?
                                        JOIN medicinetb m ON si.medicine_id = m.medicine_id
                                        ORDER BY s.bill_id DESC, si.item_id ASC");
        mysqli_stmt_bind_param($stmt_i, "i", $pid);
        mysqli_stmt_execute($stmt_i);
        $res_i = mysqli_stmt_get_result($stmt_i);
        while ($item = mysqli_fetch_assoc($res_i)) {
            $items_by_bill[$item['bill_id']][] = $item;
        }
        mysqli_stmt_close($stmt_i);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Smart Hospital - Pharmacy Bills</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="patient-panel.css">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Sans&display=swap" rel="stylesheet">

    <style>
      .bill-header {
        border-bottom: 3px double #342ac1;
        padding-bottom: 10px;
        margin-bottom: 20px;
      }
      .bill-title {
        font-family: 'IBM Plex Sans', sans-serif;
        color: #342ac1;
      }
      .bill-card {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
      }
      .bill-label {
        font-weight: 600;
        color: #495057;
      }
      @media print {
        .no-print {
          display: none !important;
        }
        body {
          padding-top: 0 !important;
        }
        .bill-card {
          border: none;
          box-shadow: none;
        }
      }
    </style>
  </head>

  <body class="pp-body">
    <header class="pp-topbar">
      <div class="pp-topbar-left">
        <a href="admin-panel.php" class="pp-topbar-title" style="text-decoration:none;color:#243b53;display:block;">
          <strong>Smart Hospital</strong>
          <small>Patient Portal &bull; Pharmacy Bills</small>
        </a>
      </div>
      <div class="pp-topbar-right">
        <a href="admin-panel.php" class="pp-topbar-user" title="Back to Dashboard">
          <div class="pp-avatar-sm"><?php echo htmlspecialchars($pp_initial); ?></div>
          <div class="pp-uname">
            <strong><?php echo htmlspecialchars($pp_user); ?></strong>
            <small>Patient Account</small>
          </div>
        </a>
        <a href="logout.php" class="pp-topbar-logout" title="Logout" onclick="return confirm('Are you sure you want to log out?');"><i class="fa fa-sign-out" aria-hidden="true"></i></a>
      </div>
    </header>

    <main class="pp-page">

      <?php if ($view_bill_id > 0): ?>

        <?php if ($bill): ?>
          <div class="pp-paper">

            <div class="bill-header text-center">
              <h3 class="bill-title mb-0"><i class="fa fa-hospital-o" aria-hidden="true"></i> SMART HOSPITAL</h3>
              <h5 class="text-uppercase text-dark mt-2">Pharmacy Bill / Invoice</h5>
              <div class="text-muted small">Generated on <?php echo date('d M Y, h:i A'); ?></div>
            </div>

            <div class="row mb-2">
              <div class="col-sm-6">
                <span class="bill-label">Bill No.:</span>
                <span><?php echo htmlspecialchars($bill['bill_id']); ?></span>
              </div>
              <div class="col-sm-6">
                <span class="bill-label">Date &amp; Time:</span>
                <span><?php echo htmlspecialchars($bill['sale_date']); ?></span>
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-sm-6">
                <span class="bill-label">Patient Name:</span>
                <span><?php echo htmlspecialchars($bill['patient_name']); ?></span>
              </div>
              <div class="col-sm-6">
                <span class="bill-label">Patient ID:</span>
                <span><?php echo htmlspecialchars($bill['pid']); ?></span>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-sm-6">
                <span class="bill-label">Doctor:</span>
                <span><?php echo htmlspecialchars($bill['doctor_name'] ?? '—'); ?></span>
              </div>
              <div class="col-sm-6">
                <span class="bill-label">Pharmacist:</span>
                <span><?php echo htmlspecialchars($bill['pharmacist_username']); ?></span>
              </div>
            </div>

            <div class="table-responsive">
            <table class="table pp-table">
              <thead>
                <tr>
                  <th scope="col">Medicine</th>
                  <th scope="col" class="text-center">Quantity</th>
                  <th scope="col" class="text-right">Unit Price</th>
                  <th scope="col" class="text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($bill_items) > 0): ?>
                  <?php foreach ($bill_items as $item): ?>
                    <tr>
                      <td>
                        <?php echo htmlspecialchars($item['medicine_name']); ?>
                        <?php if (!empty($item['generic_name'])): ?>
                          <br><small class="text-muted"><?php echo htmlspecialchars($item['generic_name']); ?></small>
                        <?php endif; ?>
                      </td>
                      <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                      <td class="text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                      <td class="text-right"><?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted">No medicines recorded for this bill.</td></tr>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th scope="row" colspan="3" class="text-right bill-label">Total Amount</th>
                  <td class="text-right"><strong><?php echo number_format($bill['total_amount'], 2); ?></strong></td>
                </tr>
                <tr>
                  <th scope="row" colspan="3" class="text-right bill-label">Payment Method</th>
                  <td class="text-right"><?php echo htmlspecialchars($bill['payment_type']); ?></td>
                </tr>
              </tfoot>
            </table>
            </div>

            <?php if (!empty($bill['notes'])): ?>
              <div class="mt-2">
                <span class="bill-label">Notes:</span>
                <span><?php echo htmlspecialchars($bill['notes']); ?></span>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mt-4 no-print">
              <a href="pharmacy_bills.php" class="pp-btn pp-btn-outline pp-btn-sm"><i class="fa fa-arrow-left"></i> Back to Pharmacy Bills</a>
              <button type="button" class="pp-btn pp-btn-primary pp-btn-sm" onclick="window.print();"><i class="fa fa-print"></i> Print Bill</button>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center mt-5">
            <h4 class="text-danger"><i class="fa fa-exclamation-triangle"></i> Bill not found or you are not authorised to view it.</h4>
            <a href="pharmacy_bills.php" class="pp-btn pp-btn-primary pp-btn-sm mt-3"><i class="fa fa-arrow-left"></i> Back to Pharmacy Bills</a>
          </div>
        <?php endif; ?>

      <?php else: ?>

        <div class="pp-card">
          <div class="pp-card-header">
            <h5 class="pp-card-title"><i class="fa fa-money" aria-hidden="true"></i> My Pharmacy Bills</h5>
          </div>
          <div class="pp-card-body">

          <?php if (count($bills) > 0): ?>
            <div class="table-responsive">
              <table class="table pp-table">
                <thead>
                  <tr>
                    <th scope="col">Bill No.</th>
                    <th scope="col">Date &amp; Time</th>
                    <th scope="col">Doctor</th>
                    <th scope="col">Pharmacist</th>
                    <th scope="col">Medicines</th>
                    <th scope="col">Quantity</th>
                    <th scope="col">Unit Price</th>
                    <th scope="col">Subtotal</th>
                    <th scope="col">Total Amount</th>
                    <th scope="col">Payment Method</th>
                    <th scope="col">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($bills as $bill_row):
                      $cur_items = $items_by_bill[$bill_row['bill_id']] ?? [];
                      $row_count = count($cur_items) > 0 ? count($cur_items) : 1;
                  ?>
                    <?php if ($row_count === 1): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($bill_row['bill_id']); ?></td>
                        <td><?php echo htmlspecialchars($bill_row['sale_date']); ?></td>
                        <td><?php echo htmlspecialchars($bill_row['doctor_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($bill_row['pharmacist_username']); ?></td>
                        <td>
                          <?php if (count($cur_items) > 0): ?>
                            <?php echo htmlspecialchars($cur_items[0]['medicine_name']); ?>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo count($cur_items) > 0 ? htmlspecialchars($cur_items[0]['quantity']) : '—'; ?></td>
                        <td><?php echo count($cur_items) > 0 ? number_format($cur_items[0]['unit_price'], 2) : '—'; ?></td>
                        <td><?php echo count($cur_items) > 0 ? number_format($cur_items[0]['subtotal'], 2) : '—'; ?></td>
                        <td><strong><?php echo number_format($bill_row['total_amount'], 2); ?></strong></td>
                    <td><?php echo htmlspecialchars($bill_row['payment_type']); ?></td>
                    <td><a href="pharmacy_bills.php?bill_id=<?php echo urlencode($bill_row['bill_id']); ?>" class="pp-btn pp-btn-primary pp-btn-sm">View Pharmacy Bill</a></td>
                      </tr>
                    <?php else: ?>
                      <tr>
                        <td rowspan="<?php echo $row_count; ?>"><?php echo htmlspecialchars($bill_row['bill_id']); ?></td>
                        <td rowspan="<?php echo $row_count; ?>"><?php echo htmlspecialchars($bill_row['sale_date']); ?></td>
                        <td rowspan="<?php echo $row_count; ?>"><?php echo htmlspecialchars($bill_row['doctor_name'] ?? '—'); ?></td>
                        <td rowspan="<?php echo $row_count; ?>"><?php echo htmlspecialchars($bill_row['pharmacist_username']); ?></td>
                        <?php $first = true; foreach ($cur_items as $idx => $cur_item): ?>
                          <?php if (!$first) { echo '<tr>'; } ?>
                          <td><?php echo htmlspecialchars($cur_item['medicine_name']); ?></td>
                          <td><?php echo htmlspecialchars($cur_item['quantity']); ?></td>
                          <td><?php echo number_format($cur_item['unit_price'], 2); ?></td>
                          <td><?php echo number_format($cur_item['subtotal'], 2); ?></td>
                          <?php if ($first): ?>
                            <td rowspan="<?php echo $row_count; ?>"><strong><?php echo number_format($bill_row['total_amount'], 2); ?></strong></td>
                            <td rowspan="<?php echo $row_count; ?>"><?php echo htmlspecialchars($bill_row['payment_type']); ?></td>
                            <td rowspan="<?php echo $row_count; ?>"><a href="pharmacy_bills.php?bill_id=<?php echo urlencode($bill_row['bill_id']); ?>" class="pp-btn pp-btn-primary pp-btn-sm">View Pharmacy Bill</a></td>
                          <?php endif; ?>
                          <?php echo '</tr>'; $first = false; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="pp-empty">
              <div class="pp-empty-icon"><i class="fa fa-money" aria-hidden="true"></i></div>
              <h5>No pharmacy bills found</h5>
              <p>You don&rsquo;t have any pharmacy bills yet.</p>
            </div>
          <?php endif; ?>
          </div>
        </div>

      <?php endif; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
  </body>
</html>