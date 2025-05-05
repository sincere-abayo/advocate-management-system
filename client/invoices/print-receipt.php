<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get client ID from session
$clientId = $_SESSION['client_id'];

// Check if payment ID is provided
if (!isset($_GET['payment_id']) || !is_numeric($_GET['payment_id'])) {
    $_SESSION['flash_message'] = "Invalid payment ID";
    $_SESSION['flash_type'] = "error";
    header("Location: payment-history.php");
    exit;
}

$paymentId = (int)$_GET['payment_id'];

// Connect to database
$conn = getDBConnection();

// Get payment details
$query = "
    SELECT 
        p.*,
        b.billing_id,
        b.description as invoice_description,
        b.client_id,
        u.full_name as advocate_name,
        u.email as advocate_email,
        u.phone as advocate_phone,
        cu.full_name as client_name,
        cu.email as client_email,
        cu.phone as client_phone,
        c.case_id,
        c.case_number,
        c.title as case_title
    FROM payments p
    JOIN billings b ON p.billing_id = b.billing_id
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    JOIN client_profiles cp ON b.client_id = cp.client_id
    JOIN users cu ON cp.user_id = cu.user_id
    LEFT JOIN cases c ON b.case_id = c.case_id
    WHERE p.payment_id = ? AND b.client_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $paymentId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

// Check if payment exists and belongs to the client
if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Payment not found or you don't have permission to view it";
    $_SESSION['flash_type'] = "error";
    header("Location: payment-history.php");
    exit;
}

$payment = $result->fetch_assoc();

// Close connection
$conn->close();

// Set content type to PDF
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt #<?php echo $paymentId; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .title {
            font-size: 20px;
            color: #333;
            margin-bottom: 5px;
        }
        .receipt-id {
            color: #666;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-section h2 {
            font-size: 16px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .payment-details {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .total-row td {
            border-top: 2px solid #ddd;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .print-button {
            text-align: center;
            margin: 20px 0;
        }
        .print-button button {
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button button:hover {
            background-color: #1d4ed8;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                padding: 0;
            }
            .receipt {
                border: none;
            }
        }
    </style>
</head>
<body>
<div class="print-button">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="window.history.back()" style="background-color: #6B7280; margin-left: 10px;">Go Back</button>
    </div>

    
    <div class="receipt">
        <div class="header">
            <div class="logo">Advocate Management System</div>
            <div class="title">Payment Receipt</div>
            <div class="receipt-id">Receipt #<?php echo $paymentId; ?></div>
        </div>
        
        <div class="info-grid">
            <div class="info-section">
                <h2>From</h2>
                <p>
                    <strong><?php echo htmlspecialchars($payment['advocate_name']); ?></strong><br>
                    <?php if (!empty($payment['advocate_email'])): ?>
                        Email: <?php echo htmlspecialchars($payment['advocate_email']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($payment['advocate_phone'])): ?>
                        Phone: <?php echo htmlspecialchars($payment['advocate_phone']); ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="info-section">
                <h2>To</h2>
                <p>
                    <strong><?php echo htmlspecialchars($payment['client_name']); ?></strong><br>
                    <?php if (!empty($payment['client_email'])): ?>
                        Email: <?php echo htmlspecialchars($payment['client_email']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($payment['client_phone'])): ?>
                        Phone: <?php echo htmlspecialchars($payment['client_phone']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="info-section">
            <h2>Payment Information</h2>
            <div class="info-grid">
                <div>
                    <p>
                        <strong>Payment Date:</strong> <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?><br>
                        <strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?><br>
                        <strong>Invoice #:</strong> INV-<?php echo str_pad($payment['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
                <div>
                    <?php if (!empty($payment['case_id'])): ?>
                        <p>
                            <strong>Related Case:</strong> <?php echo htmlspecialchars($payment['case_number']); ?><br>
                            <strong>Case Title:</strong> <?php echo htmlspecialchars($payment['case_title']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="payment-details">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php if (!empty($payment['notes'])): ?>
                                <?php echo htmlspecialchars($payment['notes']); ?>
                            <?php else: ?>
                                Payment for Invoice #<?php echo str_pad($payment['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                <?php if (!empty($payment['invoice_description'])): ?>
                                    - <?php echo htmlspecialchars($payment['invoice_description']); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="amount"><?php echo formatCurrency($payment['amount']); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="amount"><?php echo formatCurrency($payment['amount']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>This receipt was generated on <?php echo date('F j, Y'); ?> at <?php echo date('h:i A'); ?></p>
            <p>Advocate Management System</p>
        </div>
    </div>
</body>
</html>
