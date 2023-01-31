<?php
/* ----------------- */
/* -- The Library -- */
/* ----------------- */
define('DOVE_STARTED', hrtime(true));
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
    private $integrity = Dove::INTEGRITY_DISABLED;
    private static $key = []; // $length => $key
    public function __construct(string $client, int $expiration_in_days = 0, int $integrity = Dove::INTEGRITY_DISABLED, string $hash_function = 'sha1', string $path = __DIR__ . '/.dove/'){
        $this->client = empty($hash_function) ? strtolower($client) : $hash_function(strtolower($client));
        $this->expiration = $expiration_in_days*24*60*60*1000000000; // [1 days -> 86_400 sec] => [1 sec -> 1_000_000_000 ns]
        $this->integrity = $integrity;
        $this->path = $path . $this->client . '/';
        if(!file_exists($this->path)) mkdir($this->path, 0777, true);
    }
    public function Push(string $message){
        if(empty($message)) return;
        $time = hrtime(true);
        mkdir($this->path . $time, 0777, true);
        file_put_contents($this->path . $time . '/' . 'data', $message);
        if($this->integrity & Dove::INTEGRITY_GENERATE_HASH) file_put_contents($this->path . $time . '/sha256', hash('sha256', $message));
        if($this->integrity & Dove::INTEGRITY_GENERATE_SIGNATURE){ static::key(); file_put_contents($this->path . $time . '/signature', base64_encode(sodium_crypto_sign_detached($message, sodium_crypto_sign_secretkey(static::$key['keypair'])))); }
        return static::encode($time);
    }
    public function Pull(string $time = '', bool $debug = false){
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
    public function Read(string $time){ static::key();
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
    private function removeMessage($time){ foreach(['data', 'sha256', 'signature'] as $file) @unlink($this->path . $time . '/' . $file); rmdir($this->path . $time); }
    public function Delete(string $time = ''){
        $time = static::decode($time);
        if(!empty($time)){ $this->removeMessage($time); return true; }
        @array_walk($this->Pull(), function($value, $key) { $this->removeMessage($value); });
        rmdir($this->path);
        return true;
    }
    public static function key(){
        if(empty(static::$key)) {
            $file = file_get_contents(__FILE__);
            $id = strrev(hash('sha512', __FILE__) . hash('sha512', $file) . hash('sha512', json_encode(stat(__FILE__)) . __LINE__));
            static::$key = [
                24 => substr(hash('sha512', $id . self::class . __FILE__ . $file), 0, 24), // nonce
                32 => substr(hash('sha512', strrev($file . __FILE__ . self::class . $id)), 0, 32), // symmetric key
                'keypair' => sodium_crypto_sign_seed_keypair(substr($id, 0, 32)),
            ];
        }
    }
    public static function base64url_encode($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
    public static function base64url_decode($data) { return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); }
    public static function encode(string $data){ static::key();
        return static::base64url_encode(sodium_crypto_secretbox($data, static::$key[24], static::$key[32]));
    }
    public static function decode(string $data){ static::key();
        return sodium_crypto_secretbox_open(static::base64url_decode($data), static::$key[24], static::$key[32]);
    }
    /* ------------------- */
    /* -- HTTP REST API -- */
    /* ------------------- */
    public static function Serve(bool $block = true, int $expiration_in_days = 0, int $integrity = Dove::INTEGRITY_DISABLED, string $hash_function = 'sha1', string $path = __DIR__ . '/.dove/'){
        if($_REQUEST || isset($_SERVER['HTTP_HOST']) || $block){
            SERVE: header('Content-Type: application/json');
            $client = isset($_REQUEST['client']) ? $_REQUEST['client'] : die('{"status":"error", "message": "client not sent"}');
            if(!empty($client)){
                $cmd = isset($_REQUEST['cmd']) ? strtolower($_REQUEST['cmd']) : 'pull';
                $time = isset($_REQUEST['time']) ? $_REQUEST['time'] : 0;
                $dove = new static($client, $expiration_in_days, $integrity, $hash_function, $path);
                $result = $cmd == 'pull' ? array('status' => 'OK', 'data' => $dove->Pull($time)) : $dove->Read($time);
                $result['msec'] = (hrtime(true) - DOVE_STARTED) / 1000000;
                die(json_encode($result));
            }
            die('{"status":"error", "message": "not found"}');
        }
    }
    /* ----------- */
    /* -- Tests -- */
    /* ----------- */
    public static function debug($valid, $line) { if(!$valid){ throw new \Exception('Error: ' . var_export($valid, true) . ' - LINE: ' . $line . PHP_EOL); } }
    public static function Test(){
        $keyword = 'Salam, World!';
        static::debug(static::base64url_decode(static::base64url_encode($keyword)) === $keyword, __LINE__);
        static::debug(static::decode(static::encode($keyword)) === $keyword, __LINE__);
        $dove = new static('abdelaziz', 1, Dove::INTEGRITY_ALL);
        $times = array();
        try { static::debug(sizeof($dove->Pull()) == 0, __LINE__); } catch(\Exception $ignored){}
        for($i = 0; $i < 10; $i++){
            $time = $dove->Push($keyword . "-$i");
            var_dump(array('time' => $time));
            static::debug(!empty($time), __LINE__);
            $times[] = $time;
        }
        for($i = 0; $i < 10; $i++){
            $value = $dove->Read($times[$i]);
            var_dump(array('value' => $value));
            static::debug(!empty($value), __LINE__);
            var_dump(array('($keyword . "-$i")' => ($keyword . "-$i")));
            static::debug($value['data'] === ($keyword . "-$i"), __LINE__);
            static::debug(!empty($value['time']), __LINE__);
            static::debug(!empty($value['sha256']), __LINE__);
            static::debug(!empty($value['signature']), __LINE__);
            static::debug($value['integrity']['sha256'], __LINE__);
            static::debug($value['integrity']['signature'], __LINE__);
            $current = sizeof($dove->Pull($times[$i], true));
            var_dump(array('current' => $current, '(9-$i)' => (9-$i)));
            static::debug($current === (9-$i), __LINE__);
        }
        for($i = 0; $i < 10; $i++){
            static::debug(!empty($dove->Delete($times[$i])), __LINE__);
            $value = $dove->Read($times[$i]);
            var_dump(array('value' => $value));
            static::debug(empty($value['data']), __LINE__);
            static::debug($value['status'] === 'MISSING', __LINE__);
            static::debug(empty($value['sha256']), __LINE__);
            static::debug(empty($value['signature']), __LINE__);
        }
        $dove->Delete();
        die('OK');
    }
}
if(defined('DOVE')) return;
Dove::Serve(false);
Dove::Test();
?>