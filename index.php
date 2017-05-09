<?php

// PromSvyazBank payment processing gateway emulator

require_once 'config.php';
require_once 'template.php';

define('TR_PAYMENT', 1);
define('TR_REVERT', 14);

try {
    if (!isset($_POST['MERCHANT']) || !isset($config[ $_POST['MERCHANT'] ])) {
        throw new Exception('Unknown merchant name');
    }

    $trtype = intval($_POST['TRTYPE']);
    if (!in_array($trtype, [TR_PAYMENT, TR_REVERT])) {
        throw new Exception('Unknown operation type');
    }

    if (!isset($_POST['AMOUNT'])
        || !isset($_POST['CURRENCY'])
        || !isset($_POST['ORDER'])
        || (!isset($_POST['DESC']) and $trtype == TR_PAYMENT)
        || (!isset($_POST['MERCH_NAME']) and $trtype == TR_PAYMENT)
        || !isset($_POST['TERMINAL'])
        || !isset($_POST['EMAIL'])
        || !isset($_POST['TRTYPE'])
        || !isset($_POST['TIMESTAMP'])
        || !isset($_POST['NONCE'])
        || !isset($_POST['BACKREF'])
        || !isset($_POST['P_SIGN'])
        || (!isset($_POST['ORG_AMOUNT']) and $trtype == TR_REVERT)
        || (!isset($_POST['RRN']) and $trtype == TR_REVERT)
        || (!isset($_POST['INT_REF']) and $trtype == TR_REVERT)
    ) {
        throw new Exception('There are no required parameters');
    }

    $cfg = $config[ $_POST['MERCHANT'] ];

    // Signature
    switch ($trtype) {
        case TR_PAYMENT:
            $hmac_params = [
                'AMOUNT'     => trim($_POST['AMOUNT']),
                'CURRENCY'   => trim($_POST['CURRENCY']),
                'ORDER'      => trim($_POST['ORDER']),
                'DESC'       => trim($_POST['DESC']),
                'MERCH_NAME' => trim($_POST['MERCH_NAME']),
                'MERCHANT'   => trim($_POST['MERCHANT']),
                'TERMINAL'   => trim($_POST['TERMINAL']),
                'EMAIL'      => trim($_POST['EMAIL']),
                'TRTYPE'     => trim($_POST['TRTYPE']),
                'TIMESTAMP'  => trim($_POST['TIMESTAMP']),
                'NONCE'      => trim($_POST['NONCE']),
                'BACKREF'    => trim($_POST['BACKREF']),
            ];
            break;
        case TR_REVERT:
            $hmac_params = [
                'ORDER'      => trim($_POST['ORDER']),
                'AMOUNT'     => trim($_POST['AMOUNT']),
                'CURRENCY'   => trim($_POST['CURRENCY']),
                'ORG_AMOUNT' => trim($_POST['ORG_AMOUNT']),
                'RRN'        => trim($_POST['RRN']),
                'INT_REF'    => trim($_POST['INT_REF']),
                'TRTYPE'     => trim($_POST['TRTYPE']),
                'TERMINAL'   => trim($_POST['TERMINAL']),
                'BACKREF'    => trim($_POST['BACKREF']),
                'EMAIL'      => trim($_POST['EMAIL']),
                'TIMESTAMP'  => trim($_POST['TIMESTAMP']),
                'NONCE'      => trim($_POST['NONCE']),
            ];
            break;
        default:
            $hmac_params = [];
    }

    $psign = generateHmac($hmac_params, $cfg['key']);
    if ($psign != $_POST['P_SIGN']) {
        throw new Exception('The signature is invalid');
    }

    if (isset($_POST['success']) || isset($_POST['fail']) || defined('IS_TEST')) {

        $inv_id = uniqid(); // Random payment identifier

        // Signature for payment notification
        switch ($trtype) {
            case TR_PAYMENT:
                $hmac_params = [
                    'AMOUNT'     => trim($_POST['AMOUNT']),
                    'CURRENCY'   => trim($_POST['CURRENCY']),
                    'ORDER'      => trim($_POST['ORDER']),
                    'MERCH_NAME' => trim($_POST['MERCH_NAME']),
                    'MERCHANT'   => trim($_POST['MERCHANT']),
                    'TERMINAL'   => trim($_POST['TERMINAL']),
                    'EMAIL'      => trim($_POST['EMAIL']),
                    'TRTYPE'     => trim($_POST['TRTYPE']),
                    'TIMESTAMP'  => trim($_POST['TIMESTAMP']),
                    'NONCE'      => trim($_POST['NONCE']),
                    'BACKREF'    => trim($_POST['BACKREF']),
                    'RESULT'     => isset($_POST['success']) || defined('IS_TEST') ? 0 : 3,
                    'RC'         => '-',
                    'RCTEXT'     => '-',
                    'AUTHCODE'   => '1AA1AA',
                    'RRN'        => $inv_id,
                    'INT_REF'    => '-',
                ];
                break;
            case TR_REVERT:
                $hmac_params = [
                    'ORDER'      => trim($_POST['ORDER']),
                    'AMOUNT'     => trim($_POST['AMOUNT']),
                    'CURRENCY'   => trim($_POST['CURRENCY']),
                    'ORG_AMOUNT' => trim($_POST['ORG_AMOUNT']),
                    'RRN'        => $inv_id,
                    'INT_REF'    => '-',
                    'TRTYPE'     => trim($_POST['TRTYPE']),
                    'TERMINAL'   => trim($_POST['TERMINAL']),
                    'BACKREF'    => trim($_POST['BACKREF']),
                    'EMAIL'      => trim($_POST['EMAIL']),
                    'TIMESTAMP'  => trim($_POST['TIMESTAMP']),
                    'NONCE'      => trim($_POST['NONCE']),
                    'RESULT'     => isset($_POST['success']) || defined('IS_TEST') ? 0 : 3,
                    'RC'         => '-',
                    'RCTEXT'     => '-',
                ];
                break;
            default:
                $hmac_params = [];
        }

        $extra_params = [];
        if ($trtype == 1) {
            $extra_params = [
                'NAME' => 'Cardholder Name',
                'CARD' => '4154XXXXXXXX0000',
            ];
        }

        // Payment notification URL
        $uri = $cfg['result_uri'].'?';
        $uri .= http_build_query(array_merge($hmac_params, $extra_params));
        $uri .= '&P_SIGN='.generateHmac($hmac_params, $cfg['key']);

        if (defined('IS_TEST')) {
            echo '<a href="'.$cfg['host'].$uri.'">Show the answer</a>';
            exit;
        }

        if (isset($cfg['http_auth']) and is_array($cfg['http_auth'])) {
            $auth = base64_encode($cfg['http_auth']['username'].':'.$cfg['http_auth']['password']);
            $context = stream_context_create(
                [
                    'http' => [
                        'header' => 'Authorization: Basic '.$auth,
                    ],
                ]
            );
            $response = file_get_contents($cfg['host'].$uri, false, $context);
        } else {
            $response = file_get_contents($cfg['host'].$uri);
        }

        if (isset($_POST['success']) and $response != 'OK') {
            throw new Exception('Invalid answer');
        }

        header('Location: '.trim($_POST['BACKREF']));
        exit;
    } else {
        switch ($trtype) {
            case TR_PAYMENT:
                load_template('index', ['params' => $_POST], 'layout');
                break;
            case TR_REVERT:
                load_template('revert', ['params' => $_POST], 'layout');
                break;
            default:
                throw new Exception('Unknown operation type');
        }
    }
} catch (Exception $e) {
    header('Bad request', true, 400);
    if (defined('IS_TEST')) {
        echo $e->getMessage();
    } else {
        load_template('error', ['message' => $e->getMessage()], 'layout');
    }
}

function generateHmac(array $params, $key)
{
    $str = '';
    foreach ($params as $k => $v) {
        if ($k !== 'DESC') {
            $str .= strlen($v).$v;
        }
    }

    return hash_hmac('sha1', $str, pack('H*', $key));
}
