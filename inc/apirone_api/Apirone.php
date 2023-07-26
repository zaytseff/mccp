<?php

namespace ApironeApi;

require_once(__DIR__ . '/Request.php');
require_once(__DIR__ . '/LoggerWrapper.php');
require_once(__DIR__ . '/Db.php');

require_once(__DIR__ . '/Utils.php');


use \ApironeApi\Request as Request;
use \ApironeApi\LoggerWrapper as LoggerWrapper;

class Apirone {

    use Utils;

    static $LogFilePath = '';

    static $currencyIconUrl = 'https://apirone.com/static/img2/%s.svg';

    static $debug = false;

    /**
     * Get list of supported currencies
     * 
     * @param string $type wallets|accounts
     * @return Error|string|void|false 
     */
    public static function getCurrency ($abbr) {
        $result = self::currencyList();
        if ($result == false)
            return false;

        foreach ($result as $currency) {
            if ($currency->abbr == $abbr) {
                return $currency;
            }
        }

        return false; 
    }

    /**
     * Get list of supported currencies
     * 
     * @param string $type wallets|accounts
     * @return Error|string|void|false 
     */
    public static function currencyList () {
        $result = self::serviceInfo();
        if ($result == false)
            return array();

        $currencies = $result->currencies;

        foreach ($currencies as $currency) {
            if (!property_exists($currency, 'dust-rate')) {
                $currency->{'dust-rate'} = 1000;
            }
            $currency->address = '';
            $currency->icon = self::currencyIcon($currency->abbr);
            $currency->testnet = (substr_count(strtolower($currency->name), 'testnet') > 0) ? 1 : 0;
        }

        return $currencies; 
    }

    public static function accountCurrencyList($account, $activeOnly = true) {
        $accountInfo = self::accountInfo($account);
        $serviceInfo = self::serviceInfo();

        if ($accountInfo == false || $serviceInfo == false)
            return false;

        $info = $accountInfo->info;
        $currencies = $serviceInfo->currencies;

        $destinations = array();
        $activeCurrencies = array();

        // Get destinations
        foreach ($info as $item) {
            $destinations[$item->currency] = $item->destinations ? $item->destinations[0]->address : false;
        }

        // Get currencies list
        foreach ($currencies as $item) {
            unset($item->{'units'});
            unset($item->{'processing-fee'});
            unset($item->{'fee-free-limit'});
            unset($item->{'address-types'});
            unset($item->{'default-address-type'});
            unset($item->{'minimal-confirmations'});

            if (!property_exists($item, 'dust-rate')) {
                $item->{'dust-rate'} = 1000;
            }
            $item->address = $destinations[$item->abbr];
            $item->icon = self::currencyIcon($item->abbr);
            $item->testnet = (substr_count(strtolower($item->name), 'testnet') > 0) ? 1 : 0;
            if($activeOnly && !empty($item->address)) {
                $activeCurrencies[] = $item;
            }
        }

        return ($activeOnly) ? $activeCurrencies : $currencies;
    }

    /**
     * Get exchange rate
     * 
     * @param string $currency 
     * @return Error|string|void|false 
     */
    public static function ticker ($currency = false) {
        $endpoint = '/v2/ticker' . ($currency ? '?currency=' . $currency : '');
        $result = Request::execute('get', $endpoint);

        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return false;
        }
        LoggerWrapper::debug($result);

        return json_decode($result);
    }

    /**
     * Get list of supported currencies
     * 
     * @param string $type wallets|accounts
     * @return Error|string|void|false 
     */
    public static function serviceInfo ($type = 'accounts') {
        $endpoint = '/v2/' . $type;
        $result = Request::execute('options', $endpoint);

        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return false;
        }
        LoggerWrapper::debug($result);

        return json_decode($result);
    }

    /**
     * Create new account
     * 
     * @return json|false 
     */
    public static function accountCreate () {
        $endpoint = '/v2/accounts';
        $result = Request::execute('post', $endpoint);

        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return false;
        }
        LoggerWrapper::debug($result);

        return json_decode($result);
    }

    /**
     * Get account info
     * 
     * @param mixed $account_id 
     * @param mixed $currency 
     * @return json|false 
     */
    public static function accountInfo ($account_id, $currency = false) {
        $endpoint = '/v2/accounts/' . $account_id;
        $params = ($currency) ? array('currency' => $currency) : array();
        $result = Request::execute('get', $endpoint, $params);

        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return false;
        }
        LoggerWrapper::debug($result);

        return json_decode($result);
    }

    public static function setTransferAddress($account, $currency, $address) {
        $endpoint = '/v2/accounts/' . $account->account;

        $params['transfer-key'] = $account->{'transfer-key'};
        $params['currency'] = $currency;
        $params['destinations'][] = array("address" => $address);
        $params['processing-fee-policy'] = 'percentage';

        $result = Request::execute('patch', $endpoint, json_encode($params), true);
        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return false;
        }
        LoggerWrapper::debug($result);

        return json_decode($result);
    }


    // INVOICES MOTHODS

    /**
     * 
     * @param object $account
     * @param object $invoiceData 
     * @return mixed 
     */
    public static function invoiceCreate ($account, $invoiceData) {
        $endpoint = '/v2/accounts/' . $account->account . '/invoices';
        $result = Request::execute('post', $endpoint, json_encode($invoiceData), true);

        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return $result;
        }
        else
        LoggerWrapper::debug($result);

        return json_decode($result);
    }

    public static function invoiceInfoPublic($invoice_id) {
        $endpoint = '/v2/invoices/' . $invoice_id;
        $result = Request::execute('get', $endpoint);

        if (Request::isResponseError($result)) {
            LoggerWrapper::error($result);
            return false;
        }
        LoggerWrapper::debug($result);

        return json_decode($result);

    }

    // HELPERS METHODS

    public static function setLogger($logger, $debug = false) {
        LoggerWrapper::setLogger($logger, $debug);   
    }

    /**
     * Get currency icon URL
     * 
     * @param mixed $abbr 
     * @return string 
     */
    static public function currencyIcon ($abbr) {
        if ( $abbr[0] == 't') {
            $abbr = substr($abbr, 1);
        }
        return sprintf(self::$currencyIconUrl, $abbr);
    }
}
