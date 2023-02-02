<?php
/* ----------------- */
/* -- The Library -- */
/* ----------------- */
class Dove{
    const INTEGRITY_DISABLED = 0b0000; // or 0
    const INTEGRITY_GENERATE_HASH = 0b0001;
    const INTEGRITY_VERIFY_HASH = 0b0010;
    const INTEGRITY_GENERATE_SIGNATURE = 0b0100;
    const INTEGRITY_VERIFY_SIGNATURE = 0b1000;
    const INTEGRITY_ALL = 0b1111; // or 1
    private $path = '';
    private $client = '';
    private $expiration = 0;  // days
    private $integrity = Dove::INTEGRITY_ALL;
    private static $key = []; // $length => $key
    public function __construct(string $client, int $expiration_in_days = 0, int $integrity = Dove::INTEGRITY_ALL, string $hash_function = 'sha1', string $path = __DIR__ . '/.dove/'){
        $this->client = empty($hash_function) ? strtolower($client) : $hash_function(strtolower($client));
        $this->expiration = $expiration_in_days*24*60*60*1000000000; // [1 days -> 86_400 sec] => [1 sec -> 1_000_000_000 ns]
        $this->integrity = $integrity;
        $this->path = $path . $this->client . '/';
        if(!file_exists($this->path)) mkdir($this->path, 0777, true);
    }
    public function Push(string $message) : array {
        if(empty($message)) return array();
        $time = hrtime(true);
        mkdir($this->path . $time, 0777, true);
        file_put_contents($this->path . $time . '/' . 'data', $message);
        $result = array('time' => static::encode($time), 'sha256' => '', 'signature' => '');
        if($this->integrity & Dove::INTEGRITY_GENERATE_HASH) { $result['sha256'] = hash('sha256', $message); file_put_contents($this->path . $time . '/sha256', $result['sha256']); }
        if($this->integrity & Dove::INTEGRITY_GENERATE_SIGNATURE){ static::key(); $result['signature'] = base64_encode(sodium_crypto_sign_detached($message, sodium_crypto_sign_secretkey(static::$key['keypair']))); file_put_contents($this->path . $time . '/signature', $result['signature']); }
        return $result;
    }
    public function Pull(string $time = '', bool $debug = false) : array {
        $messages = array_diff(scandir($this->path, SCANDIR_SORT_DESCENDING), array('.', '..'));
        if(!empty($this->expiration)){
            $current = hrtime(true);
            $messages = array_filter($messages, function($value) use ($current, $debug) {
                $diff = ($current - (int) $value);
                $old = $diff > $this->expiration;
                if($debug) var_dump(array('debug' => $debug, 'value' => (int) $value, 'expiration' => $this->expiration, 'current' => $current, 'diff' => $diff));
                if($old) $this->Delete($value);
                return !$old;
            });
        }
        if(empty($time)) return array_map(function($value){ return static::encode($value); }, $messages);
        return array_map(function($value){ return static::encode($value); }, array_filter($messages, function($value) use ($time) { return $value > static::decode($time); }));
    }
    public function Read(string $time) : array { static::key();
        $time = static::decode($time);
        $hash = @file_get_contents($this->path . $time . '/sha256');
        $signature = @file_get_contents($this->path . $time . '/signature');
        $data = @file_get_contents($this->path . $time . '/data');
        return array(
            'status' => empty($data) ? 'MISSING' : 'OK',
            'sha256' => $hash,
            'signature' => $signature,
            'time' => $time,
            'data' => $data,
            'integrity' => array(
                'sha256' => $this->integrity & Dove::INTEGRITY_VERIFY_HASH ? hash('sha256', $data) === $hash : false,
                'signature' => ($this->integrity & Dove::INTEGRITY_VERIFY_SIGNATURE) && !empty($data) && !empty($signature) && !empty(static::$key) ? sodium_crypto_sign_verify_detached(base64_decode($signature), $data, sodium_crypto_sign_publickey(static::$key['keypair'])) : false,
            ),
        );
    }
    private function removeMessage($time) : void { foreach(['data', 'sha256', 'signature'] as $file) @unlink($this->path . $time . '/' . $file); rmdir($this->path . $time); }
    public function Delete(string $time = '') : bool {
        $time = static::decode($time);
        if(!empty($time)){ $this->removeMessage($time); return true; }
        @array_walk($this->Pull(), function($value, $key) { $this->removeMessage($value); });
        rmdir($this->path);
        return true;
    }
    public static function key() : void {
        if(empty(static::$key)) {
            $file = file_get_contents(__FILE__);
            $id = strrev(hash('sha512', __FILE__) . hash('sha512', $file) . hash('sha512', json_encode(stat(__FILE__)) . __LINE__));
            static::$key = [
                24 => substr(hash('sha512', $id . self::class . __FILE__ . $file), 0, 24), // nonce
                32 => substr(hash('sha512', strrev($file . __FILE__ . self::class . $id)), 0, 32), // symmetric key
                'keypair' => sodium_crypto_sign_seed_keypair(substr($id, 0, 32)), // Ed25519 keypair
            ];
        }
    }
    public static function base64url_encode($data) : string { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
    public static function base64url_decode($data) : string { return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); }
    public static function encode(string $data) : string { static::key();
        return static::base64url_encode(sodium_crypto_secretbox($data, static::$key[24], static::$key[32]));
    }
    public static function decode(string $data) : string { static::key();
        return sodium_crypto_secretbox_open(static::base64url_decode($data), static::$key[24], static::$key[32]);
    }
    /* ------------------- */
    /* -- HTTP REST API -- */
    /* ------------------- */
    public static function Serve(bool $block = true, int $expiration_in_days = 0, int $integrity = Dove::INTEGRITY_ALL, string $hash_function = 'sha1', string $path = __DIR__ . '/.dove/') : void {
        if($_REQUEST || isset($_SERVER['HTTP_HOST']) || $block){
            $start = hrtime(true);
            header('Content-Type: application/json');
            $client = isset($_REQUEST['client']) ? $_REQUEST['client'] : die('{"status":"ERROR", "message": "client not sent"}');
            if(!empty($client)){
                $cmd = isset($_REQUEST['cmd']) ? strtolower($_REQUEST['cmd']) : 'pull';
                $time = isset($_REQUEST['time']) ? $_REQUEST['time'] : 0;
                $dove = new static($client, $expiration_in_days, $integrity, $hash_function, $path);
                $result = $cmd == 'pull' ? array('status' => 'OK', 'data' => $dove->Pull($time)) : $dove->Read($time);
                $result['msec'] = (hrtime(true) - $start) / 1000000; // 1_000_000
                die(json_encode($result));
            }
            die('{"status":"ERROR", "message": "not found"}');
        }
    }
    /* ----------- */
    /* -- Tests -- */
    /* ----------- */
    public static function debug(string $message, bool $valid, int $line) : void { if(!$valid){ throw new \Exception("Error($message): " . var_export($valid, true) . ' - on line: ' . $line . PHP_EOL); } }
    public static function Test() : void {
        $keyword = 'Salam, World!';
        static::debug('base64url_encode/base64url_decode functions', static::base64url_decode(static::base64url_encode($keyword)) === $keyword, __LINE__);
        static::debug('encode/decode functions', static::decode(static::encode($keyword)) === $keyword, __LINE__);
        $dove = new static('abdelaziz', 1, Dove::INTEGRITY_ALL);
        $times = array();
        try { static::debug('messages count should be zero', sizeof($dove->Pull()) == 0, __LINE__); } catch(\Exception $ignored){}
        for($i = 0; $i < 10; $i++){
            $pushed_result = $dove->Push($keyword . "-$i");
            var_dump(array('pushed_result' => $pushed_result));
            static::debug('pushed_result should not be empty', !empty($pushed_result), __LINE__);
            static::debug('time should not be empty', !empty($pushed_result['time']), __LINE__);
            static::debug('sha256 should not be empty', !empty($pushed_result['sha256']), __LINE__);
            static::debug('signature should not be empty', !empty($pushed_result['signature']), __LINE__);
            $times[] = $pushed_result['time'];
        }
        for($i = 0; $i < 10; $i++){
            $value = $dove->Read($times[$i]);
            var_dump(array('value' => $value));
            static::debug('value should not be empty', !empty($value), __LINE__);
            var_dump(array('($keyword . "-$i")' => ($keyword . "-$i")));
            static::debug('data should be equal', $value['data'] === ($keyword . "-$i"), __LINE__);
            static::debug('time should not be empty', !empty($value['time']), __LINE__);
            static::debug('sha256 should not be empty', !empty($value['sha256']), __LINE__);
            static::debug('signature should not be empty', !empty($value['signature']), __LINE__);
            static::debug('integrity.sha256 should be true', $value['integrity']['sha256'], __LINE__);
            static::debug('integrity.integrity should be true', $value['integrity']['signature'], __LINE__);
            $current = sizeof($dove->Pull($times[$i], true));
            var_dump(array('current' => $current, '(9-$i)' => (9-$i)));
            static::debug('current should be equal', $current === (9-$i), __LINE__);
        }
        for($i = 0; $i < 10; $i++){
            static::debug('Dove::Delete() function output should not be empty', !empty($dove->Delete($times[$i])), __LINE__);
            $value = $dove->Read($times[$i]);
            var_dump(array('value' => $value));
            static::debug('data should be empty', empty($value['data']), __LINE__);
            static::debug('status should equal MISSING', $value['status'] === 'MISSING', __LINE__);
            static::debug('sha256 should be false', $value['sha256'] === false, __LINE__);
            static::debug('signature should be false', $value['signature'] === false, __LINE__);
        }
        $dove->Delete();
        die('OK');
    }
}
if(defined('DOVE')) return;
Dove::Serve(false);
Dove::Test();
?>