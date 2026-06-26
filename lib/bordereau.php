<?php
// lib/bordereau.php
require_once __DIR__ . '/../vendor/autoload.php';
// i18n helper
if (file_exists(__DIR__ . '/../lib/i18n.php')) {
    require_once __DIR__ . '/../lib/i18n.php';
}

function sendTransferBordereau($toEmail, $beneficiaryName, $iban, $bic, $bankName, $amount, $currency, $reason, $transferId, $senderAccount)
{
    // Ensure log directory exists
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/email.log';

    $log = function($line) use ($logFile) {
        $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        @file_put_contents($logFile, "[{$ts}] " . $line . "\n", FILE_APPEND | LOCK_EX);
    };

    $log("Preparing bordereau for transferId={$transferId}, to={$toEmail}");


    // Modern professional HTML template for mail and PDF
    $date = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('d/m/Y H:i');
    // Ensure we have a visible reference to display. Try multiple sensible keys as fallbacks.
    $displayRef = '—';
    $refCandidates = [
        $transferId ?? null,
        $senderAccount['reference'] ?? null,
        $senderAccount['ref'] ?? null,
        $senderAccount['transaction_id'] ?? null,
        $senderAccount['txid'] ?? null,
        $senderAccount['paypal_txn_id'] ?? null,
        $senderAccount['payment_id'] ?? null,
        $senderAccount['id'] ?? null,
    ];
    foreach ($refCandidates as $c) {
        if (!empty($c)) {
            $displayRef = $c;
            break;
        }
    }

    // Use translations for subject (after $displayRef is determined)
    $subject = function_exists('t') ? t('email_subject_bordereau', ['reference' => $displayRef]) : "Bordereau de virement - Réf #{$displayRef}";

    $langAttr = function_exists('current_lang') ? current_lang() : 'fr';
    $refLabel = function_exists('t') ? t('reference_label') : 'Référence';
    $dateLabel = function_exists('t') ? t('date_label') : 'Date';
    $benefLabel = function_exists('t') ? t('beneficiary_label') : 'Bénéficiaire';
    $ibanLabel = function_exists('t') ? t('iban_label') : 'IBAN';
    $bicLabel = function_exists('t') ? t('bic_label') : 'BIC';
    $bankLabel = function_exists('t') ? t('bank_label') : 'Banque';
    $amountLabel = function_exists('t') ? t('amount_label') : 'Montant';
    $reasonLabel = function_exists('t') ? t('reason_label') : 'Motif';
    $performedLabel = function_exists('t') ? t('performed_by_label') : 'Virement effectué par';
    $transferHeading = function_exists('t') ? t('transfer_success') : 'Virement confirmé';

    $amountFormatted = number_format((float)$amount, 2, ',', ' ');
    $currencyLabel = strtoupper($currency ?: 'EUR');
    $heroCaption = function_exists('t') ? t('transfer_success_subtitle', ['amount' => $amountFormatted, 'currency' => $currencyLabel]) : 'Le transfert est confirmé et sera crédité sous peu.';
    if ($heroCaption === 'transfer_success_subtitle') {
        $heroCaption = 'Le transfert est confirmé et sera crédité sous peu.';
    }

    $receiptTitle = function_exists('t') ? t('receipt_title') : 'RECEIPT';
    $clientCopy = function_exists('t') ? t('client_copy') : 'Client Copy';
    $copyLabelText = function_exists('t') ? t('copy_label') : 'Copie';
    if ($copyLabelText === 'copy_label') { $copyLabelText = 'Copie'; }
    $statusLabelText = function_exists('t') ? t('status_label') : 'Statut';
    if ($statusLabelText === 'status_label') { $statusLabelText = 'Statut'; }
    $generalInfoTitle = function_exists('t') ? t('general_information') : 'Informations principales';
    if ($generalInfoTitle === 'general_information') { $generalInfoTitle = 'Informations principales'; }
    $benefSectionTitle = function_exists('t') ? t('beneficiary_section_title') : $benefLabel;
    if ($benefSectionTitle === 'beneficiary_section_title') { $benefSectionTitle = $benefLabel; }
    $transferSectionTitle = function_exists('t') ? t('transfer_section_title') : $transferHeading;
    if ($transferSectionTitle === 'transfer_section_title') { $transferSectionTitle = $transferHeading; }
    $currencyTextLabel = function_exists('t') ? t('account_currency_label') : 'Devise';
    if ($currencyTextLabel === 'account_currency_label') { $currencyTextLabel = 'Devise'; }

    $beneficiarySafe = trim((string)$beneficiaryName);
    $bankSafe = trim((string)$bankName);

    $heroMetaEntries = [];
    if ($beneficiarySafe !== '') {
        $heroMetaEntries[] = ['label' => $benefLabel, 'value' => $beneficiarySafe];
    }
    if ($bankSafe !== '') {
        $heroMetaEntries[] = ['label' => $bankLabel, 'value' => $bankSafe];
    }
    $heroMetaEntries[] = ['label' => $copyLabelText, 'value' => $clientCopy];
    $heroMetaEntries[] = ['label' => $statusLabelText, 'value' => $transferHeading];

    $generalInfoCards = [
        ['label' => $dateLabel, 'value' => $date],
        ['label' => $refLabel, 'value' => $displayRef],
        ['label' => $amountLabel, 'value' => $amountFormatted . ' ' . $currencyLabel],
        ['label' => $currencyTextLabel, 'value' => $currencyLabel],
    ];

    $benefDetailRows = [];
    $benefDetailRows[] = ['key' => $benefLabel, 'value' => $beneficiarySafe !== '' ? $beneficiarySafe : '—'];
    if (!empty($iban)) {
        $benefDetailRows[] = ['key' => $ibanLabel, 'value' => trim((string)$iban)];
    }
    if (!empty($bic)) {
        $benefDetailRows[] = ['key' => $bicLabel, 'value' => strtoupper(trim((string)$bic))];
    }
    if ($bankSafe !== '') {
        $benefDetailRows[] = ['key' => $bankLabel, 'value' => $bankSafe];
    }

    $transferRows = [];
    if (!empty($reason)) {
        $transferRows[] = ['key' => $reasonLabel, 'value' => nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')), 'isRich' => true];
    }
    $performerName = trim(($senderAccount['prenom'] ?? '') . ' ' . ($senderAccount['nom'] ?? ''));
    if ($performerName === '') {
        $performerName = $senderAccount['nom'] ?? ($senderAccount['prenom'] ?? '—');
    }
    $performerId = $senderAccount['id'] ?? $senderAccount['compte_id'] ?? null;
    $performerDisplay = $performerName;
    if (!empty($performerId)) {
        $performerDisplay .= ' · ID ' . $performerId;
    }
    $transferRows[] = ['key' => $performedLabel, 'value' => htmlspecialchars($performerDisplay, ENT_QUOTES, 'UTF-8'), 'isRich' => true];

    $contactDisplay = 'support@transferflux.com';
    $footerTpl = function_exists('t') ? t('footer_text', ['contact_display' => $contactDisplay]) : 'Ce bordereau est généré automatiquement par TRANSFERFLUX. Contact: {contact_display}';
    $footerHtml = str_replace('{contact_display}', '<a href="mailto:fluxbank3@gmail.com" style="color:#2563eb;text-decoration:none;font-weight:600;">' . htmlspecialchars($contactDisplay, ENT_QUOTES, 'UTF-8') . '</a>', $footerTpl);

    $tplVersion = '2025.12.02.1';
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($langAttr, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        @media only screen and (max-width:600px) {
            .stack-column,
            .stack-column td {
                display:block !important;
                width:100% !important;
                text-align:left !important;
            }
            .text-center-mobile { text-align:center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#e8ecff;font-family:'Space Grotesk','Inter','Segoe UI',Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#e8ecff;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:820px;background-color:#ffffff;border-radius:32px;border:1px solid #e2e8f0;overflow:hidden;" data-template-version="<?= $tplVersion; ?>">
                    <tr>
                        <td style="padding:32px;background-color:#2b3c96;background-image:linear-gradient(135deg,#2b3c96 0%,#1f2937 80%);color:#ffffff;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="stack-column" style="font-size:14px;line-height:1.4;">
                                        <div style="display:inline-block;padding:10px 16px;border:1px solid rgba(255,255,255,0.35);border-radius:18px;font-weight:700;letter-spacing:0.08em;">TRANSFERFLUX</div>
                                        <div style="margin-top:16px;font-size:0.8rem;letter-spacing:0.35em;text-transform:uppercase;opacity:0.85;"><?= htmlspecialchars($receiptTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div style="margin-top:6px;font-size:0.95rem;font-weight:600;"><?= htmlspecialchars($refLabel, ENT_QUOTES, 'UTF-8'); ?> : <span style="font-weight:700;"><?= htmlspecialchars($displayRef, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                        <div style="margin-top:4px;font-size:0.95rem;font-weight:600;"><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?> : <span style="font-weight:700;"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                        <div style="margin-top:6px;font-size:0.75rem;letter-spacing:0.2em;text-transform:uppercase;opacity:0.8;"><?= htmlspecialchars($clientCopy, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="stack-column" align="right" style="min-width:220px;font-size:14px;line-height:1.6;">
                                        <div style="text-transform:uppercase;font-size:0.78rem;letter-spacing:0.18em;color:rgba(255,255,255,0.8);"><?= htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div style="font-size:2.6rem;font-weight:700;margin-top:6px;"><?= htmlspecialchars($amountFormatted . ' ' . $currencyLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div style="margin-top:6px;font-size:0.95rem;color:rgba(255,255,255,0.85);line-height:1.4;"><?= htmlspecialchars($heroCaption, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                </tr>
                            </table>
                            <?php if (!empty($heroMetaEntries)): ?>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                                    <?php foreach (array_chunk($heroMetaEntries, 2) as $chunk): ?>
                                        <tr>
                                            <?php foreach ($chunk as $entry): ?>
                                                <td style="width:50%;padding:10px 12px;">
                                                    <div style="border:1px solid rgba(255,255,255,0.4);border-radius:18px;padding:12px 14px;">
                                                        <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.2em;color:rgba(255,255,255,0.8);margin-bottom:6px;"><?= htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div style="font-size:1.05rem;font-weight:600;word-break:break-word;"><?= htmlspecialchars($entry['value'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                            <?php if (count($chunk) === 1): ?>
                                                <td style="width:50%;padding:10px 12px;"></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 12px;">
                            <div style="font-size:0.75rem;letter-spacing:0.3em;text-transform:uppercase;color:#94a3b8;margin-bottom:14px;"><?= htmlspecialchars($generalInfoTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <?php foreach (array_chunk($generalInfoCards, 2) as $chunk): ?>
                                    <tr>
                                        <?php foreach ($chunk as $card): ?>
                                            <td style="width:50%;padding:10px;">
                                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:18px;background-color:#f8fafc;">
                                                    <tr>
                                                        <td style="padding:12px 16px;">
                                                            <div style="font-size:0.72rem;letter-spacing:0.18em;text-transform:uppercase;color:#94a3b8;"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <div style="margin-top:6px;font-size:1.05rem;font-weight:600;word-break:break-word;"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        <?php endforeach; ?>
                                        <?php if (count($chunk) === 1): ?>
                                            <td style="width:50%;padding:10px;"></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>
                    <?php if (!empty($benefDetailRows)): ?>
                        <tr>
                            <td style="padding:12px 32px 10px;">
                                <div style="font-size:0.75rem;letter-spacing:0.3em;text-transform:uppercase;color:#94a3b8;margin-bottom:14px;"><?= htmlspecialchars($benefSectionTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:20px;">
                                    <?php foreach ($benefDetailRows as $row): ?>
                                        <tr>
                                            <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;width:45%;font-size:0.92rem;font-weight:600;color:#475569;"><?= htmlspecialchars($row['key'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:1rem;font-weight:600;color:#0f172a;text-align:right;word-break:break-word;"><?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="2" style="height:1px;background-color:transparent;border-bottom:none;"></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($transferRows)): ?>
                        <tr>
                            <td style="padding:12px 32px 8px;">
                                <div style="font-size:0.75rem;letter-spacing:0.3em;text-transform:uppercase;color:#94a3b8;margin-bottom:14px;"><?= htmlspecialchars($transferSectionTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:20px;">
                                    <?php foreach ($transferRows as $row): ?>
                                        <tr>
                                            <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;width:45%;font-size:0.92rem;font-weight:600;color:#475569;"><?= htmlspecialchars($row['key'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;font-size:1rem;font-weight:600;color:#0f172a;text-align:right;word-break:break-word;">
                                                <?php if (!empty($row['isRich'])): ?>
                                                    <div style="text-align:left;line-height:1.5;font-weight:500;"><?= $row['value']; ?></div>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="2" style="height:1px;background-color:transparent;border-bottom:none;"></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding:26px 32px 36px;text-align:center;font-size:0.95rem;color:#475569;border-top:1px solid #e2e8f0;">
                            <?= $footerHtml; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<?php
    $html = ob_get_clean();
    @file_put_contents($logDir . '/bordereau_last.html', $html);

    // Load .env file if env vars not set by the web server
    $envFile = __DIR__ . '/../.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
            if ($_line[0] === '#' || strpos($_line, '=') === false) continue;
            [$_k, $_v] = explode('=', $_line, 2);
            $_k = trim($_k);
            $_v = trim($_v, " \t\n\r\0\x0B\"'");
            if (getenv($_k) === false) putenv("$_k=$_v");
        }
    }

    // Determine SMTP settings from environment variables
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
    $smtpPort = getenv('SMTP_PORT') ?: 587;
    $smtpUser = getenv('SMTP_USER') ?: 'lalyaisidore@gmail.com';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $fromEmail = getenv('EMAIL_FROM') ?: 'noreply@fluxtransfer.world';
    $fromName = getenv('EMAIL_FROM_NAME') ?: 'TRANSFERFLUX';

    // Try to generate a PDF bordereau using Dompdf if available
    $pdfPath = null;
    if (class_exists('\Dompdf\Dompdf')) {
        try {
            $dompdf = new \Dompdf\Dompdf();
            // Increase DPI / resolution so the generated PDF appears larger and crisper
            if (method_exists($dompdf, 'set_option')) {
                $dompdf->set_option('dpi', 96);
                $dompdf->set_option('isRemoteEnabled', true);
            }
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();
            $tmp = tempnam(sys_get_temp_dir(), 'bord_');
            file_put_contents($tmp, $output);
            $pdfPath = $tmp;
        } catch (Exception $e) {
            // ignore PDF generation errors and fallback to HTML attachment
            $pdfPath = null;
        }
    }

    // Create PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = ((int)$smtpPort === 465) ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

        // Enable SMTP debug output to our log for diagnosis
        $mail->SMTPDebug = 2; // verbose
        $mail->Debugoutput = function($str, $level) use ($log) {
            $log("PHPMailer debug (level={$level}): " . trim($str));
        };

        // Ajout de la pièce jointe PDF après avoir défini le corps HTML/texte
        if ($pdfPath !== null && file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath, 'bordereau.pdf');
        } else {
            // Attach HTML as a fallback file
            $tmpHtml = tempnam(sys_get_temp_dir(), 'bordhtml_');
            file_put_contents($tmpHtml, $html);
            $mail->addAttachment($tmpHtml, 'bordereau.html');
        }

        $mail->send();
        $log("Mail sent to {$toEmail} (transferId={$transferId})");

        // Cleanup temp files
        if (isset($tmpHtml) && file_exists($tmpHtml)) @unlink($tmpHtml);
        if ($pdfPath !== null && file_exists($pdfPath)) @unlink($pdfPath);
    } catch (Exception $e) {
        // Log error and cleanup
        $log('Mail error to ' . $toEmail . ' : ' . $e->getMessage());
        if (isset($tmpHtml) && file_exists($tmpHtml)) @unlink($tmpHtml);
        if ($pdfPath !== null && file_exists($pdfPath)) @unlink($pdfPath);
        throw new Exception('Mail error: ' . $e->getMessage());
    }
}

?>
