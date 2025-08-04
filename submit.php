<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requiredFields = ['usdt_amount', 'network', 'payment_mode'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            echo "Error: Missing required field: $field";
            exit;
        }
    }

    $amount = floatval($_POST['usdt_amount']);
    if ($amount < 1.2 || $amount > 500) {
        echo "Amount must be between 1.2 and 500 USDT.";
        exit;
    }

    $network = $_POST['network'];
    $payment_mode = $_POST['payment_mode'];
    $wallet = $network == "TRC20" ? "TUYMmZN5oTfyjDZ8zqbqsfepdbvBgoBDz3" : "0x1A63d865D881E15D4103a5c031a67C0d63e9849C";
    $rate = ($amount > 40) ? 85 : (($amount > 10) ? 84 : 83);
    $order_id = uniqid("ORD");
    $timestamp = date("Y-m-d H:i:s");

    // Additional fields
    $upi_id = $_POST['upi_id'] ?? '';
    $cdm_account_number = $_POST['cdm_account_number'] ?? '';
    $cdm_bank_name = $_POST['cdm_bank_name'] ?? '';
    $cdm_holder_name = $_POST['cdm_holder_name'] ?? '';
    $bank_account_number = $_POST['bank_account_number'] ?? '';
    $bank_name = $_POST['bank_name'] ?? '';
    $bank_holder_name = $_POST['bank_holder_name'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    $scanner_path = '';

    if ($payment_mode == "UPI") {
        if (empty($upi_id) || empty($_FILES['scanner']['name'])) {
            echo "UPI ID and scanner file are required.";
            exit;
        }

        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $scanner_path = $target_dir . basename($_FILES["scanner"]["name"]);
        $imageFileType = strtolower(pathinfo($scanner_path, PATHINFO_EXTENSION));
        if ($imageFileType != "png") {
            echo "Only PNG scanner files allowed.";
            exit;
        }
        move_uploaded_file($_FILES["scanner"]["tmp_name"], $scanner_path);
    }

    $total_inr = $amount * $rate;

    // Save to Excel
    $file = 'orders.xlsx';
    if (file_exists($file)) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
    } else {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'Order ID', 'Timestamp', 'Amount', 'Rate', 'Total INR', 'Network',
            'Wallet', 'Payment Mode',
            'UPI ID', 'Scanner File',
            'CDM Account No.', 'CDM Bank', 'CDM Holder',
            'Bank Account No.', 'Bank Name', 'Bank Holder', 'IFSC Code', 'Status', 'Rating'
        ], null, 'A1');
    }

    $sheet->insertNewRowBefore(2, 1);
    $sheet->fromArray([
        $order_id, $timestamp, $amount, $rate, $total_inr, $network,
        $wallet, $payment_mode,
        $upi_id, $scanner_path,
        $cdm_account_number, $cdm_bank_name, $cdm_holder_name,
        $bank_account_number, $bank_name, $bank_holder_name, $ifsc_code, 'Pending', ''
    ], null, 'A2');
    $writer = new Xlsx($spreadsheet);
    $writer->save($file);

    // Email notification
    $to = "youradmin@example.com"; // Replace with your real email
    $subject = "🆕 New USDT Order - $order_id";
    $message = "New Order Received\n\nOrder ID: $order_id\nAmount: $amount USDT\nRate: ₹$rate\nMode: $payment_mode\nNetwork: $network\nTime: $timestamp";
    $headers = "From: notifier@p2pforyou.com";
    mail($to, $subject, $message, $headers);

    // HTML output with live status check
    echo "
    <html>
    <head>
        <title>Order Submitted</title>
        <style>
            body { background-color: #000; color: #fff; font-family: Arial; text-align: center; }
            .box { background: #111; padding: 20px; margin: 50px auto; border-radius: 10px; max-width: 600px; position: relative; }
            .stamp { position: absolute; top: 10px; right: 10px; font-size: 60px; opacity: 0.05; }
            #countdown { position: absolute; left: 20px; top: 20px; font-size: 18px; color: #0f0; }
        </style>
    </head>
    <body>
        <div class='stamp'>P2Pforyou</div>
        <div id='mainBox' class='box'>
            <div id='countdown'></div>
            <h2>😊 Order Submitted!</h2>
            <p>Order ID: <b>$order_id</b></p>
            <p>Status: <b id='statusText'>Waiting for Merchant to Accept</b></p>
            <p>If you have sent the USDT, the payment will be made shortly.<br>If not, the order will be rejected.</p>
        </div>

        <script>
            window.onload = function () {
                let seconds = 600;
                const timer = document.getElementById('countdown');
                function updateCountdown() {
                    let m = Math.floor(seconds / 60);
                    let s = seconds % 60;
                    timer.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                    if (seconds > 0) {
                        seconds--;
                        setTimeout(updateCountdown, 1000);
                    }
                }
                updateCountdown();
            };

            // Check status and update UI
            const orderId = '$order_id';
            const checkStatus = () => {
                fetch('check_status.php?order_id=' + orderId)
                    .then(response => response.text())
                    .then(status => {
                        if (status.trim() === 'Completed') {
                            const box = document.getElementById('mainBox');
                            box.innerHTML = `
                                <h2>✅ Order has been completed!</h2>
                                <p>Please rate us out of 5 stars.</p>
                                <form action='rate.php' method='post'>
                                    <input type='hidden' name='order_id' value='${orderId}'>
                                    <select name='rating' required>
                                        <option value='1'>⭐️</option>
                                        <option value='2'>⭐️⭐️</option>
                                        <option value='3'>⭐️⭐️⭐️</option>
                                        <option value='4'>⭐️⭐️⭐️⭐️</option>
                                        <option value='5'>⭐️⭐️⭐️⭐️⭐️</option>
                                    </select>
                                    <br><br>
                                    <button type='submit'>Submit Rating</button>
                                </form>
                            `;
                            clearInterval(statusCheckInterval);
                        }
                    });
            };

            const statusCheckInterval = setInterval(checkStatus, 3000);
        </script>
    </body>
    </html>
    ";
}
?>
