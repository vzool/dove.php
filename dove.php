<?php
/* ----------------- */
/* -- The Library -- */
/* ----------------- */
class Dove{
    private $path = '';
    private $client = '';
    private $expiration = 0;  // days
    private static $key = []; // $length => $key
    function __construct(string $client, int $expiration_in_days = 0, string $hash_function = 'sha1', string $path = __DIR__ . '/.dove/'){
        $this->client = empty($hash_function) ? strtolower($client) : $hash_function(strtolower($client));
        $this->expiration = $expiration_in_days*24*60*60; // 1 days -> 86_400 sec
        if(function_exists('hrtime')) $this->expiration *= 1000000000; // 1 sec -> 1_000_000_000 ns
        $this->path = $path . $this->client . '/';
        if(!file_exists($this->path)) mkdir($this->path, 0777, true);
    }
    function Push(string $message){
        if(empty($message)) return;
        $time = function_exists('hrtime') ? hrtime(true) : microtime(true);
        file_put_contents($this->path . $time, $message);
        return static::encode($time);
    }
    function Pull(string $time = '', bool $debug = false){
        $messages = array_diff(scandir($this->path, SCANDIR_SORT_DESCENDING), array('.', '..'));
        if(!empty($this->expiration)){
            $current = function_exists('hrtime') ? hrtime(true) : microtime(true);
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
    function Read(string $time){ return @file_get_contents($this->path . static::decode($time)); }
    function Delete(string $time = ''){
        if(!empty($time)) return @unlink($this->path . static::decode($time));
        @array_walk($this->Pull(), function($value, $key) { @unlink($this->path . $value); });
        rmdir($this->path);
    }
    public static function key(){
        if(empty(static::$key)) {
            $file = file_get_contents(__FILE__);
            static::$key = [
                24 => substr(hash('sha512', self::class . __FILE__ . $file), 0, 24),
                32 => substr(hash('sha512', strrev($file . __FILE__ . self::class)), 0, 32),
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
    public static function Serve(){
        if($_REQUEST || isset($_SERVER['HTTP_HOST'])){
            header('Content-Type: application/json');
            $client = isset($_REQUEST['client']) ? $_REQUEST['client'] : die('{"status":"error", "message": "client not sent"}');
            if(!empty($client)){
                $cmd = isset($_REQUEST['cmd']) ? strtolower($_REQUEST['cmd']) : 'pull';
                $time = isset($_REQUEST['time']) ? $_REQUEST['time'] : 0;
                $dove = new static($client);
                die($cmd == 'pull' ? json_encode(array("status"=> "OK", "data" => $dove->Pull($time))) : '{"status": "OK", "data": "'.$dove->Read($time).'"}');
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
        $dove = new static('abdelaziz', 1);
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
            static::debug($value === ($keyword . "-$i"), __LINE__);
            $current = sizeof($dove->Pull($times[$i], true));
            var_dump(array('current' => $current, '(9-$i)' => (9-$i)));
            static::debug($current === (9-$i), __LINE__);
        }
        for($i = 0; $i < 10; $i++){
            $dove->Delete($times[$i]);
            $value = $dove->Read($times[$i]);
            var_dump(array('value' => $value));
            static::debug(empty($value), __LINE__);
        }
        $dove->Delete();
        die('OK');
    }
}
if(defined('DOVE')) return;
Dove::Serve();
Dove::Test();
?>