<?php
function send_otp_email(string $toEmail, string $toName, string $otp): array {
    $devMode = getenv('MAIL_DEV_MODE') ?: false;
    
    if ($devMode) {
        $logFile = __DIR__ . '/../otp_logs.txt';
        $logEntry = sprintf(
            "[%s] Email: %s | Name: %s | OTP: %s\n",
            date('Y-m-d H:i:s'),
            $toEmail,
            $toName,
            $otp
        );
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return ['success' => true, 'error' => null, 'dev_mode' => true];
    }
    
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $usePHPMailer = false;
    $phpmailerError = null;
    
    if (file_exists($autoload)) {
        require_once $autoload;
        $usePHPMailer = true;
    } else {
        $base = __DIR__ . '/PHPMailer/src';
        if (file_exists($base.'/PHPMailer.php') && file_exists($base.'/SMTP.php') && file_exists($base.'/Exception.php')) {
            require_once $base.'/PHPMailer.php';
            require_once $base.'/SMTP.php';
            require_once $base.'/Exception.php';
            $usePHPMailer = true;
        }
    }

    if ($usePHPMailer) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $host   = getenv('SMTP_HOST') ?: 'sandbox.smtp.mailtrap.io';
            $user   = getenv('SMTP_USER') ?: (getenv('MAILTRAP_USER') ?: '');
            $pass   = getenv('SMTP_PASS') ?: (getenv('MAILTRAP_PASS') ?: '');
            $port   = (int)(getenv('SMTP_PORT') ?: 2525);
            $secure = getenv('SMTP_SECURE') ?: '';
            $from   = getenv('SMTP_FROM') ?: 'no-reply@cartify.local';
            $fromName = getenv('SMTP_FROM_NAME') ?: 'Cartify';

            $fileCfgPath = __DIR__ . '/smtp_creds.php';
            if (file_exists($fileCfgPath)) {
                $cfg = include $fileCfgPath;
                if (is_array($cfg)) {
                    $host = $cfg['host'] ?? $host;
                    $port = isset($cfg['port']) ? (int)$cfg['port'] : $port;
                    $user = $cfg['username'] ?? $user;
                    $pass = $cfg['password'] ?? $pass;
                    $secure = $cfg['secure'] ?? $secure;
                    $from = $cfg['from'] ?? $from;
                    $fromName = $cfg['from_name'] ?? $fromName;
                }
            }

            $configIssues = [];
            if (!$host) { $configIssues[] = 'host missing'; }
            if (!$user) { $configIssues[] = 'username missing'; }
            if (!$pass) { $configIssues[] = 'password missing'; }
            if (is_string($pass) && stripos($pass, 'REPLACE_WITH') !== false) {
                $configIssues[] = 'placeholder password not replaced';
            }
            if (!empty($configIssues)) {
                $msg = sprintf('[%s] SMTP config error: %s (host=%s, port=%d, user=%s)\n',
                    date('Y-m-d H:i:s'),
                    implode(', ', $configIssues),
                    (string)$host,
                    (int)$port,
                    (string)$user
                );
                @file_put_contents(__DIR__ . '/../smtp_errors.log', $msg, FILE_APPEND);
                throw new \Exception('SMTP configuration invalid: ' . implode(', ', $configIssues));
            }

            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->SMTPAutoTLS = false;
            $mail->AuthType = 'LOGIN';
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = $secure;

            $mail->setFrom($from, $fromName);
            $mail->addAddress($toEmail, $toName ?: $toEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code - Cartify';
            $mail->Body    = "<p>Hello <strong>{$toName}</strong>,</p><p>Your verification code is: <strong style='font-size:1.5em;'>{$otp}</strong></p><p>This code will expire in 10 minutes.</p><p>If you did not request this code, please ignore this email.</p>";
            $mail->AltBody = "Hello {$toName},\n\nYour verification code is: {$otp}\n\nThis code will expire in 10 minutes.";

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            $phpmailerError = $e->getMessage();
            $errLine = sprintf("[%s] PHPMailer error: %s\n", date('Y-m-d H:i:s'), $phpmailerError);
            @file_put_contents(__DIR__ . '/../smtp_errors.log', $errLine, FILE_APPEND);
        }
    }
    
    $to = $toEmail;
    $subject = 'Your OTP Code - Cartify';
    $message = "Hello {$toName},\n\n";
    $message .= "Your verification code is: {$otp}\n\n";
    $message .= "This code will expire in 10 minutes.\n\n";
    $message .= "If you did not request this code, please ignore this email.\n\n";
    $message .= "Best regards,\nCartify Team";
    
    $headers = "From: Cartify <no-reply@cartify.local>\r\n";
    $headers .= "Reply-To: no-reply@cartify.local\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (@mail($to, $subject, $message, $headers)) {
        return ['success' => true, 'error' => null];
    }
    
    $errorMsg = 'Email could not be sent. ';
    if ($phpmailerError) {
        $errorMsg .= 'PHPMailer error: ' . $phpmailerError . ' | ';
    }
    $errorMsg .= 'PHP mail() also failed. Please configure SMTP or enable development mode.';
    
    $logFile = __DIR__ . '/../otp_logs.txt';
    $logEntry = sprintf("[%s] Fallback OTP (send failed). Email: %s | Name: %s | OTP: %s\n",
        date('Y-m-d H:i:s'), $toEmail, $toName, $otp);
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    return ['success' => false, 'error' => $errorMsg];
}

function send_purchase_confirmation_email(string $toEmail, string $toName, array $items, float $total): array {
    $devMode = getenv('MAIL_DEV_MODE') ?: false;
    
    if ($devMode) {
        $logFile = __DIR__ . '/../otp_logs.txt';
        $logEntry = sprintf(
            "[%s] Purchase Email: %s | Name: %s | Total: $%.2f\n",
            date('Y-m-d H:i:s'),
            $toEmail,
            $toName,
            $total
        );
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return ['success' => true, 'error' => null, 'dev_mode' => true];
    }
    
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $usePHPMailer = false;
    $phpmailerError = null;
    
    if (file_exists($autoload)) {
        require_once $autoload;
        $usePHPMailer = true;
    } else {
        $base = __DIR__ . '/PHPMailer/src';
        if (file_exists($base.'/PHPMailer.php') && file_exists($base.'/SMTP.php') && file_exists($base.'/Exception.php')) {
            require_once $base.'/PHPMailer.php';
            require_once $base.'/SMTP.php';
            require_once $base.'/Exception.php';
            $usePHPMailer = true;
        }
    }

    if ($usePHPMailer) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            $host   = getenv('SMTP_HOST') ?: 'sandbox.smtp.mailtrap.io';
            $user   = getenv('SMTP_USER') ?: (getenv('MAILTRAP_USER') ?: '');
            $pass   = getenv('SMTP_PASS') ?: (getenv('MAILTRAP_PASS') ?: '');
            $port   = (int)(getenv('SMTP_PORT') ?: 2525);
            $secure = getenv('SMTP_SECURE') ?: '';
            $from   = getenv('SMTP_FROM') ?: 'no-reply@cartify.local';
            $fromName = getenv('SMTP_FROM_NAME') ?: 'Cartify';

            $fileCfgPath = __DIR__ . '/smtp_creds.php';
            if (file_exists($fileCfgPath)) {
                $cfg = include $fileCfgPath;
                if (is_array($cfg)) {
                    $host = $cfg['host'] ?? $host;
                    $port = isset($cfg['port']) ? (int)$cfg['port'] : $port;
                    $user = $cfg['username'] ?? $user;
                    $pass = $cfg['password'] ?? $pass;
                    $secure = $cfg['secure'] ?? $secure;
                    $from = $cfg['from'] ?? $from;
                    $fromName = $cfg['from_name'] ?? $fromName;
                }
            }

            $configIssues = [];
            if (!$host) { $configIssues[] = 'host missing'; }
            if (!$user) { $configIssues[] = 'username missing'; }
            if (!$pass) { $configIssues[] = 'password missing'; }
            if (is_string($pass) && stripos($pass, 'REPLACE_WITH') !== false) {
                $configIssues[] = 'placeholder password not replaced';
            }
            if (!empty($configIssues)) {
                throw new \Exception('SMTP configuration invalid: ' . implode(', ', $configIssues));
            }

            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->SMTPAutoTLS = false;
            $mail->AuthType = 'LOGIN';
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = $secure;

            $mail->setFrom($from, $fromName);
            $mail->addAddress($toEmail, $toName ?: $toEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Your Purchase is Being Shipped - Cartify';
            
            $itemsList = '';
            foreach ($items as $item) {
                $itemsList .= "<tr><td>{$item['name']}</td><td>{$item['quantity']}</td><td>$" . number_format($item['price'], 2) . "</td><td>$" . number_format($item['price'] * $item['quantity'], 2) . "</td></tr>";
            }
            
            $mail->Body = "<p>Hello <strong>{$toName}</strong>,</p><p>Great news! Your purchase is being shipped.</p><table style='width:100%; border-collapse: collapse;'><thead><tr><th style='text-align:left; padding:8px; border-bottom:1px solid #ddd;'>Product</th><th style='text-align:left; padding:8px; border-bottom:1px solid #ddd;'>Qty</th><th style='text-align:left; padding:8px; border-bottom:1px solid #ddd;'>Price</th><th style='text-align:left; padding:8px; border-bottom:1px solid #ddd;'>Subtotal</th></tr></thead><tbody>{$itemsList}</tbody><tfoot><tr><td colspan='3' style='text-align:right; padding:8px; border-top:2px solid #ddd;'><strong>Total:</strong></td><td style='padding:8px; border-top:2px solid #ddd;'><strong>$" . number_format($total, 2) . "</strong></td></tr></tfoot></table><p>Your items will be delivered soon. Thank you for shopping with Cartify!</p><p>Best regards,<br>Cartify Team</p>";
            
            $itemsListText = '';
            foreach ($items as $item) {
                $itemsListText .= "{$item['name']} (x{$item['quantity']}) - $" . number_format($item['price'], 2) . " = $" . number_format($item['price'] * $item['quantity'], 2) . "\n";
            }
            
            $mail->AltBody = "Hello {$toName},\n\nGreat news! Your purchase is being shipped.\n\nOrder Details:\n{$itemsListText}\nTotal: $" . number_format($total, 2) . "\n\nYour items will be delivered soon. Thank you for shopping with Cartify!\n\nBest regards,\nCartify Team";

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            $phpmailerError = $e->getMessage();
        }
    }
    
    $to = $toEmail;
    $subject = 'Your Purchase is Being Shipped - Cartify';
    $message = "Hello {$toName},\n\n";
    $message .= "Great news! Your purchase is being shipped.\n\n";
    $message .= "Order Details:\n";
    foreach ($items as $item) {
        $message .= "{$item['name']} (x{$item['quantity']}) - $" . number_format($item['price'], 2) . " = $" . number_format($item['price'] * $item['quantity'], 2) . "\n";
    }
    $message .= "\nTotal: $" . number_format($total, 2) . "\n\n";
    $message .= "Your items will be delivered soon. Thank you for shopping with Cartify!\n\n";
    $message .= "Best regards,\nCartify Team";
    
    $headers = "From: Cartify <no-reply@cartify.local>\r\n";
    $headers .= "Reply-To: no-reply@cartify.local\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    if (@mail($to, $subject, $message, $headers)) {
        return ['success' => true, 'error' => null];
    }
    
    $errorMsg = 'Email could not be sent. ';
    if ($phpmailerError) {
        $errorMsg .= 'PHPMailer error: ' . $phpmailerError . ' | ';
    }
    $errorMsg .= 'PHP mail() also failed.';
    
    return ['success' => false, 'error' => $errorMsg];
}
