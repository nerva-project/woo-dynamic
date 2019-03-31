<?php

/**
 * library.php
 *
 * Written using the JSON RPC specification -
 * http://json-rpc.org/wiki/specification
 *
 * @author Kacper Rowinski <krowinski@implix.com>
 * http://implix.com
 * Modified to work with monero-rpc wallet by Serhack and cryptochangements
 */

define('CRYPTONOTE_PUBLIC_ADDRESS_BASE58_PREFIX', 0x3800);
define('CRYPTONOTE_PUBLIC_INTEGRATED_ADDRESS_BASE58_PREFIX', 0x7081);
define('CRYPTONOTE_PUBLIC_SUBADDRESS_BASE58_PREFIX', 0x1080);

require_once('SHA3.php');
require_once('base58.php');

class Nerva_Library
{
    protected $url = null, $parameters_structure = 'array';
    protected $curl_options = array(
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 8
    );
    protected $host;
    protected $port;
    private $httpErrors = array(
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        405 => '405 Method Not Allowed',
        406 => '406 Not Acceptable',
        408 => '408 Request Timeout',
        500 => '500 Internal Server Error',
        502 => '502 Bad Gateway',
        503 => '503 Service Unavailable'
    );

    public function __construct($pHost, $pPort)
    {
        $this->validate(false === extension_loaded('curl'), 'The curl extension must be loaded to use this class!');
        $this->validate(false === extension_loaded('json'), 'The json extension must be loaded to use this class!');
        $this->validate(false === extension_loaded('bcmath'), 'The bcmath extension must be loaded to use this class!');
        
        $this->host = $pHost;
        $this->port = $pPort;
        $this->url = $pHost . ':' . $pPort . '/json_rpc';

        $this->base58 = new Monero_base58();
    }

    public function keccak_256($message)
    {
        $keccak256 = SHA3::init (SHA3::KECCAK_256);
        $keccak256->absorb (hex2bin($message));
        return bin2hex ($keccak256->squeeze (32)) ;
    }

    public function validate($pFailed, $pErrMsg)
    {
        if ($pFailed) {
            echo $pErrMsg;
        }
    }

    public function setCurlOptions($pOptionsArray)
    {
        if (is_array($pOptionsArray)) {
            $this->curl_options = $pOptionsArray + $this->curl_options;
        } else {
            echo 'Invalid options type.';
        }
        return $this;
    }

    public function _print($json)
    {
        $json_encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo $json_encoded;
    }

    public function address()
    {
        $address = $this->_run('getaddress');
        return $address;
    }

    public function _run($method, $params = null)
    {
        $result = $this->request($method, $params);
        return $result; //the result is returned as an array
    }

    private function request($pMethod, $pParams)
    {
        static $requestId = 0;
        // generating uniuqe id per process
        $requestId++;
        // check if given params are correct
        $this->validate(false === is_scalar($pMethod), 'Method name has no scalar value');
        $request = json_encode(array('jsonrpc' => '2.0', 'method' => $pMethod, 'params' => $pParams, 'id' => $requestId));
        $responseMessage = $this->getResponse($request);
        $responseDecoded = json_decode($responseMessage, true);
        $jsonErrorMsg = $this->getJsonLastErrorMsg();
        $this->validate(!is_null($jsonErrorMsg), $jsonErrorMsg . ': ' . $responseMessage);
        $this->validate(empty($responseDecoded['id']), 'Invalid response data structure: ' . $responseMessage);
        $this->validate($responseDecoded['id'] != $requestId, 'Request id: ' . $requestId . ' is different from Response id: ' . $responseDecoded['id']);
        if (isset($responseDecoded['error'])) {
            $errorMessage = 'Request have return error: ' . $responseDecoded['error']['message'] . '; ' . "\n" .
                'Request: ' . $request . '; ';
            if (isset($responseDecoded['error']['data'])) {
                $errorMessage .= "\n" . 'Error data: ' . $responseDecoded['error']['data'];
            }
            $this->validate(!is_null($responseDecoded['error']), $errorMessage);
        }
        return $responseDecoded['result'];
    }

    protected function & getResponse(&$pRequest)
    {
        // do the actual connection
        $ch = curl_init();
        if (!$ch) {
            throw new RuntimeException('Could\'t initialize a cURL session');
        }
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $pRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (!curl_setopt_array($ch, $this->curl_options)) {
            throw new RuntimeException('Error while setting curl options');
        }
        // send the request
        $response = curl_exec($ch);
        // check http status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (isset($this->httpErrors[$httpCode])) {
            echo 'Response Http Error - ' . $this->httpErrors[$httpCode];
        }
        // check for curl error
        if (0 < curl_errno($ch)) {
           echo '[ERROR] Failed to connect to nerva-wallet-rpc at ' . $this->host . ' port '. $this->port .'</br>';
        }
        // close the connection
        curl_close($ch);
        return $response;
    }

    //prints result as json

    function getJsonLastErrorMsg()
    {
        if (!function_exists('json_last_error_msg')) {
            function json_last_error_msg()
            {
                static $errors = array(
                    JSON_ERROR_NONE => 'No error',
                    JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
                    JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
                    JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
                    JSON_ERROR_SYNTAX => 'Syntax error',
                    JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
                );
                $error = json_last_error();
                return array_key_exists($error, $errors) ? $errors[$error] : 'Unknown error (' . $error . ')';
            }
        }

        // Fix PHP 5.2 error caused by missing json_last_error function
        if (function_exists('json_last_error')) {
            return json_last_error() ? json_last_error_msg() : null;
        } else {
            return null;
        }
    }

    public function getbalance()
    {
        $balance = $this->_run('getbalance');
        return $balance;
    }

    public function getheight()
    {
        $height = $this->_run('getheight');
        return $height;
    }

    public function incoming_transfer($type)
    {
        $incoming_parameters = array('transfer_type' => $type);
        $incoming_transfers = $this->_run('incoming_transfers', $incoming_parameters);
        return $incoming_transfers;
    }

    public function get_transfers($input_type, $input_value)
    {
        $get_parameters = array($input_type => $input_value);
        $get_transfers = $this->_run('get_transfers', $get_parameters);
        return $get_transfers;
    }

    public function view_key()
    {
        $query_key = array('key_type' => 'view_key');
        $query_key_method = $this->_run('query_key', $query_key);
        return $query_key_method;
    }

    public function make_integrated_address($payment_id)
    {
        $params = array('payment_id' => $payment_id);
        $res = $this->_run('make_integrated_address', $params);
        return $res;
    }

    function encode_varint($data)
    {
        $orig = $data;

        if ($data < 0x80)
        {
            return bin2hex(pack('C', $data));
        }

        $encodedBytes = [];
        while ($data > 0)
        {
            $encodedBytes[] = 0x80 | ($data & 0x7f);
            $data >>= 7;
        }

        $encodedBytes[count($encodedBytes)-1] &= 0x7f;
        $bytes = call_user_func_array('pack', array_merge(array('C*'), $encodedBytes));;
        return bin2hex($bytes);
    }

    public function decode_address($address)
    {
        $decoded = $this->base58->decode($address);

        $prefix = $this->encode_varint(CRYPTONOTE_PUBLIC_ADDRESS_BASE58_PREFIX);
        $prefix_length = strlen($prefix);
        $public_spendkey = substr($decoded, $prefix_length, 64);
        $public_viewkey = substr($decoded, 64 + $prefix_length, 64);

        return array(
            "spendkey" => $public_spendkey,
            "viewkey" => $public_viewkey
        );
    }

    public function make_integrated_address_non_rpc($std_addr, $payment_id)
    {
        $decoded = $this->decode_address($std_addr);
        $prefix = $this->encode_varint(CRYPTONOTE_PUBLIC_INTEGRATED_ADDRESS_BASE58_PREFIX);
        $data = $prefix . $decoded['spendkey'] . $decoded['viewkey'] . $payment_id;
        $hash = substr($this->keccak_256($data), 0, 8);

        $res = array();
        $res['payment_id'] = $payment_id;
        $res['integrated_address'] = $this->base58->encode($data.$hash);
        return $res;
    }

    public function split_integrated_address($integrated_address)
    {
        if (!isset($integrated_address)) {
            echo "Error: Integrated_Address mustn't be null";
        } else {
            $split_params = array('integrated_address' => $integrated_address);
            $split_methods = $this->_run('split_integrated_address', $split_params);
            return $split_methods;
        }
    }

    public function transfer($amount, $address, $mixin = 4)
    {
        $new_amount = $amount * 1000000000000;
        $destinations = array('amount' => $new_amount, 'address' => $address);
        $transfer_parameters = array('destinations' => array($destinations), 'mixin' => $mixin, 'get_tx_key' => true, 'unlock_time' => 0, 'payment_id' => '');
        $transfer_method = $this->_run('transfer', $transfer_parameters);
        return $transfer_method;
    }

    public function get_payments($payment_id)
    {
        $get_payments_parameters = array('payment_id' => $payment_id);
        $get_payments = $this->_run('get_payments', $get_payments_parameters);
        return $get_payments;
    }
}
    
class XnvNodeTools
{
    public function get_api_url($testnet)
    {
        if ($testnet)
            return "https://xnv5.getnerva.org/api/"; //xnv5 only
        else
            return "https://xnv2.getnerva.org/api/"; //xnv1-xnv3
    }

    public function get_last_block_height($testnet)
    {
        $curl = curl_init();

        $url = $this->get_api_url($testnet);
        
        curl_setopt_array($curl, array(
                                       CURLOPT_RETURNTRANSFER => 1,
                                       CURLOPT_URL => $url . "getblockcount.php",
                                       ));
        $resp = curl_exec($curl);
        curl_close($curl);
        
        $array = json_decode($resp, true);
        return $array['result']['count'] - 1;
    }
    
    public function get_tx_hashes_from_block($height, $testnet)
    {
        $curl = curl_init();

        $url = $this->get_api_url($testnet);
        
        curl_setopt_array($curl, array(
                                       CURLOPT_RETURNTRANSFER => 1,
                                       CURLOPT_URL => $url . "getblockbyheight.php?height=" . $height,
                                       ));
        $resp = curl_exec($curl);
        curl_close($curl);
        
        $array = json_decode($resp, true);
        
        if (!isset($array['result']['tx_hashes'])){
            return null;
        }

        $tx_hashes = $array['result']['tx_hashes'];

        if (count($tx_hashes) > 0) {
            return $tx_hashes;
        }
        else {
            return null;
        }
    }
    
    public function check_txs($tx_hash, $address, $view_key, $testnet)
    {
        $url = $this->get_api_url($testnet);
        
        $hash_string = "?";
        foreach($tx_hash as $th) {
            $hash_string .= "hash[]=" . $th . "&";
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url . "decodeoutputs.php" . $hash_string . "address=" . $address . "&viewkey=" . $view_key));
        $resp = curl_exec($curl);
        curl_close($curl);
        $array = json_decode($resp, true);
        return $array['result']['decoded_outs'];
    }
}
