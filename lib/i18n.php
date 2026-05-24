<?php
// Minimal i18n loader and helper
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function detect_lang(): string {
    // Build the list of available languages dynamically from the lang directory
    $base = __DIR__ . '/../lang';
    $available = [];
    // include any individual lang files (fr.php, en.php, etc.)
    if (is_dir($base)) {
        $files = glob($base . '/*.php');
        if (is_array($files)) {
            foreach ($files as $f) {
                $b = basename($f);
                if ($b === 'all.php') {
                    continue;
                }
                $name = strtolower(pathinfo($b, PATHINFO_FILENAME));
                if ($name !== '') {
                    $available[] = $name;
                }
            }
        }
        // also read consolidated all.php keys
        $allFile = @realpath($base . '/all.php');
        if ($allFile && file_exists($allFile)) {
            $all = include $allFile;
            if (is_array($all)) {
                foreach (array_keys($all) as $k) {
                    if (is_string($k) && $k !== '') {
                        $available[] = strtolower($k);
                    }
                }
            }
        }
    }
    $available = array_values(array_unique($available));

    // 1) explicit GET -> save to session + cookie when allowed (allow URL to override existing session)
    if (!empty($_GET['lang'])) {
        $lang = preg_replace('/[^a-z]/', '', strtolower((string)$_GET['lang']));
        if ($lang !== '' && in_array($lang, $available, true)) {
            $_SESSION['lang'] = $lang;
                // Only attempt to set cookie if headers have not yet been sent
                if (!headers_sent()) {
                    setcookie('lang', $lang, time() + 60 * 60 * 24 * 365, '/');
                }
            return $lang;
        }
    }

    // 2) session
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $available, true)) {
        return $_SESSION['lang'];
    }

    // 3) cookie
    if (!empty($_COOKIE['lang'])) {
        $lang = preg_replace('/[^a-z]/', '', strtolower((string)$_COOKIE['lang']));
        if ($lang !== '' && in_array($lang, $available, true)) {
            $_SESSION['lang'] = $lang;
            return $lang;
        }
    }

    // 4) browser Accept-Language
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if (!empty($langs)) {
            // try each announced language in order and prefer exact matches first
            foreach ($langs as $part) {
                $primary = strtolower(substr(trim($part), 0, 2));
                if ($primary !== '' && in_array($primary, $available, true)) {
                    $_SESSION['lang'] = $primary;
                    return $primary;
                }
            }
        }
    }

    // default fallback: prefer English if available, else first available, else 'en'
    if (in_array('en', $available, true)) {
        return 'en';
    }
    if (!empty($available)) {
        return $available[0];
    }
    return 'en';
}

/**
 * Ensure some helpful derived keys exist so templates can rely on them
 * regardless of whether per-language files include them.
 */
function apply_i18n_fallbacks(array $translations): array {
    // account type default (use label if available)
    if (!array_key_exists('account_type_standard', $translations)) {
        if (!empty($translations['account_type_label'])) {
            $translations['account_type_standard'] = $translations['account_type_label'];
        } else {
            $translations['account_type_standard'] = $translations['account_type_standard'] ?? 'Standard';
        }
    }

    // transaction/timeline labels: prefer notif_* titles when present
    if (!array_key_exists('transaction_refund_received', $translations)) {
        $translations['transaction_refund_received'] = $translations['notif_refund_title'] ?? $translations['notif_refund_message'] ?? 'Refund received';
    }
    if (!array_key_exists('transaction_funds_added', $translations)) {
        $translations['transaction_funds_added'] = $translations['notif_funds_added_title'] ?? $translations['notif_funds_added_message'] ?? $translations['transactions_total'] ?? 'Funds added';
    }
    if (!array_key_exists('transaction_funds_deducted', $translations)) {
        $translations['transaction_funds_deducted'] = $translations['notif_funds_deducted_title'] ?? $translations['notif_funds_deducted_message'] ?? 'Funds deducted';
    }
    if (!array_key_exists('transaction_transfer_sent', $translations)) {
        $translations['transaction_transfer_sent'] = $translations['perform_transfer'] ?? $translations['transfer_sent'] ?? 'Transfer sent';
    }

    if (!array_key_exists('general_information', $translations)) {
        $translations['general_information'] = $translations['details_transfer']
            ?? $translations['details_transfer_paypal']
            ?? 'General information';
    }

    // hero & meta labels commonly used in the overview card
    if (!array_key_exists('hero_label', $translations)) {
        $translations['hero_label'] = $translations['account_balance'] ?? 'Available balance';
    }
    if (!array_key_exists('last_movement', $translations)) {
        $translations['last_movement'] = $translations['last_movement'] ?? 'Last movement';
    }
    if (!array_key_exists('account_currency_label', $translations)) {
        $translations['account_currency_label'] = $translations['account_currency_label'] ?? 'Account currency';
    }
    if (!array_key_exists('user_placeholder', $translations)) {
        $translations['user_placeholder'] = $translations['user_placeholder'] ?? 'User';
    }

    // Card page / card UI fallbacks
    if (!array_key_exists('card_alert_title', $translations)) {
        $translations['card_alert_title'] = 'Congratulations!';
    }
    if (!array_key_exists('card_alert_message', $translations)) {
        $translations['card_alert_message'] = 'Your debit card is available. Activate it to speed up transfers.';
    }
    if (!array_key_exists('card_section_title', $translations)) {
        $translations['card_section_title'] = 'Your card';
    }
    if (!array_key_exists('valid_until_label', $translations)) {
        $translations['valid_until_label'] = 'Valid until';
    }
    if (!array_key_exists('cvv_label', $translations)) {
        $translations['cvv_label'] = 'CVV';
    }
    if (!array_key_exists('activate_card', $translations)) {
        $translations['activate_card'] = 'Activate my card';
    }
    if (!array_key_exists('block_card', $translations)) {
        $translations['block_card'] = 'Block my card';
    }
    if (!array_key_exists('transactions_by_card', $translations)) {
        $translations['transactions_by_card'] = 'Card transactions';
    }
    if (!array_key_exists('loading_transactions', $translations)) {
        $translations['loading_transactions'] = 'Loading transactions...';
    }
    if (!array_key_exists('modal_alert_title', $translations)) {
        $translations['modal_alert_title'] = 'Alert';
    }
    if (!array_key_exists('modal_activation_unavailable', $translations)) {
        $translations['modal_activation_unavailable'] = 'Card activation is not available for security reasons, please try again later.';
    }
    if (!array_key_exists('modal_block_requires_activation', $translations)) {
        $translations['modal_block_requires_activation'] = 'This action is not allowed. Please activate your debit card first.';
    }
    if (!array_key_exists('modal_close', $translations)) {
        $translations['modal_close'] = 'Close';
    }

    // Footer labels (ensure consistent keys for nav)
    if (!array_key_exists('footer_pay', $translations)) {
        $translations['footer_pay'] = 'Pay';
    }
    if (!array_key_exists('footer_my_card', $translations)) {
        $translations['footer_my_card'] = 'My card';
    }
    if (!array_key_exists('footer_payment', $translations)) {
        $translations['footer_payment'] = 'Payment';
    }
    if (!array_key_exists('footer_account', $translations)) {
        $translations['footer_account'] = 'My account';
    }
    // Provide footer fallbacks from existing localized keys when available
    if (empty($translations['footer_pay']) && !empty($translations['pay'])) {
        $translations['footer_pay'] = $translations['pay'];
    }
    if (empty($translations['footer_my_card']) && !empty($translations['my_card'])) {
        $translations['footer_my_card'] = $translations['my_card'];
    }
    if (empty($translations['footer_payment']) && !empty($translations['payment'])) {
        $translations['footer_payment'] = $translations['payment'];
    }
    if (empty($translations['footer_account']) && !empty($translations['my_account'])) {
        $translations['footer_account'] = $translations['my_account'];
    }
    // Ensure some newer transfer-related keys exist by falling back to English defaults
    $needed_keys = array(
        'max_label',
        'info_title',
        'processing_time_bank',
        'processing_time_paypal',
        'free_label',
        'paypal_email_label',
        'field_required',
        'account_exam_message',
        'account_blocked_message',
        'virement_info_title',
        'iban_placeholder',
        'bic_placeholder',
        'bank_name_placeholder',
        'beneficiary_placeholder',
        'amount_placeholder',
        'reason_placeholder',
        'required_fields_note',
    );
    $allFile = @realpath(__DIR__ . '/../lang/all.php');
    $enDefaults = [];
    if ($allFile && file_exists($allFile)) {
        $allMap = include $allFile;
        if (is_array($allMap) && isset($allMap['en']) && is_array($allMap['en'])) {
            $enDefaults = $allMap['en'];
        }
    }
    foreach ($needed_keys as $k) {
        if (!array_key_exists($k, $translations)) {
            if (isset($enDefaults[$k]) && $enDefaults[$k] !== '') {
                $translations[$k] = $enDefaults[$k];
            } else {
                // sensible English fallback if even all.php lacks it
                switch ($k) {
                    case 'max_label': $translations[$k] = 'Max'; break;
                    case 'info_title': $translations[$k] = 'Information'; break;
                    case 'processing_time_bank': $translations[$k] = 'Processing within 1 to 3 business days. Fees:'; break;
                    case 'processing_time_paypal': $translations[$k] = 'Processing within 1 to 2 business days. Fees:'; break;
                    case 'free_label': $translations[$k] = 'Free'; break;
                    case 'paypal_email_label': $translations[$k] = 'PayPal email address'; break;
                    case 'field_required': $translations[$k] = 'This field is required.'; break;
                    case 'account_exam_message': $translations[$k] = 'Your {bank} account is under review. Please wait or contact support.'; break;
                    case 'account_blocked_message': $translations[$k] = 'Access to your {bank} account has been temporarily suspended. Please contact support.'; break;
                    case 'virement_info_title': $translations[$k] = 'Transfer information'; break;
                    case 'iban_placeholder': $translations[$k] = 'FR76 3000 6000 0112 3456 7890 189'; break;
                    case 'bic_placeholder': $translations[$k] = 'BNPAFRPPXXX'; break;
                    case 'bank_name_placeholder': $translations[$k] = 'BNP Paribas'; break;
                    case 'beneficiary_placeholder': $translations[$k] = 'John Smith'; break;
                    case 'amount_placeholder': $translations[$k] = '1 000'; break;
                    case 'reason_placeholder': $translations[$k] = 'Rent payment'; break;
                    case 'required_fields_note': $translations[$k] = 'All fields marked with * are required.'; break;
                    default: $translations[$k] = $k; break;
                }
            }
        }
    }
    return $translations;
}

function load_translations(string $lang): array {
    $base = __DIR__ . '/../lang';
    $file = @realpath($base . '/' . $lang . '.php');
    $fallback = @realpath($base . '/en.php');
    $allFile = @realpath($base . '/all.php');

    // 1) try language-specific file (securely inside lang dir)
        if ($file && stripos($file, realpath($base)) === 0 && file_exists($file)) {
        $translations = include $file;
        $translations = is_array($translations) ? $translations : [];

        // If we have a consolidated `all.php`, merge any missing keys from it for this language.
        if ($allFile && file_exists($allFile)) {
            $all = include $allFile;
            if (is_array($all) && isset($all[$lang]) && is_array($all[$lang])) {
                // Only add keys that are missing in the language-specific file
                foreach ($all[$lang] as $k => $v) {
                    if (!array_key_exists($k, $translations)) {
                        $translations[$k] = $v;
                    }
                }
            }
            // Also merge top-level string keys in `all.php` that are not language-scoped
            foreach ($all as $k => $v) {
                if (is_string($k)) {
                    // skip already-scoped entries
                    if (is_array($v)) {
                        continue;
                    }
                    if (!array_key_exists($k, $translations)) {
                        $translations[$k] = $v;
                    }
                }
            }
        }
        // apply helpful fallbacks so templates don't need to edit every language file
        return apply_i18n_fallbacks($translations);
    }

    // 2) try consolidated all.php map
    if ($allFile && file_exists($allFile)) {
        $all = include $allFile;
        if (is_array($all) && isset($all[$lang]) && is_array($all[$lang])) {
            $translations = $all[$lang];
            return apply_i18n_fallbacks($translations);
        }
    }

    // 3) fallback to en.php
    $translations = [];
    if ($fallback && file_exists($fallback)) {
        $translations = include $fallback;
    }
    $translations = is_array($translations) ? $translations : [];
    return apply_i18n_fallbacks($translations);
}

function t(string $key, array $replace = []): string {
    static $translations = null;
    if ($translations === null) {
        $lang = detect_lang();
        $translations = load_translations($lang);
    }
    $text = $translations[$key] ?? $key;
    foreach ($replace as $k => $v) {
        // Ensure replacement is a string (avoid passing NULL which is deprecated in newer PHP)
        $text = str_replace('{' . $k . '}', (string)($v ?? ''), $text);
    }
    return $text;
}

// expose current language helper
function current_lang(): string {
    return $_SESSION['lang'] ?? detect_lang();
}
