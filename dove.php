<?php
/* ----------------- */
/* -- The Library -- */
/* ----------------- */
class Dove{
    private $path = '';
    private $client = '';
    private $expiration = 0;
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
        return $time;
    }
    function Pull(int $time = 0, bool $debug = false){
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
        if(empty($time)) return $messages;
        return array_filter($messages, function($value) use ($time) { return $value > $time; });
    }
    function Read(int $time){ return @file_get_contents($this->path . $time); }
    function Delete(int $time = 0){
        if(!empty($time)) return @unlink($this->path . $time);
        @array_walk($this->Pull(), function($value, $key) { @unlink($this->path . $value); });
        rmdir($this->path);
    }
}
/* ------------------- */
/* -- HTTP REST API -- */
/* ------------------- */
if(defined('DOVE')) return;
if($_REQUEST || isset($_SERVER['HTTP_HOST'])){
    header('Content-Type: application/json');
    $client = isset($_REQUEST['client']) ? $_REQUEST['client'] : die('{"status":"error", "message": "client not sent"}');
    if(!empty($client)){
        $cmd = isset($_REQUEST['cmd']) ? strtolower($_REQUEST['cmd']) : 'pull';
        $time = isset($_REQUEST['time']) ? $_REQUEST['time'] : 0;
        $dove = new Dove($client);
        die($cmd == 'pull' ? json_encode(array("status"=> "OK", "data" => $dove->Pull($time))) : '{"status": "OK", "data": "'.$dove->Read($time).'"}');
    }
    die('{"status":"error", "message": "not found"}');
}
/* ----------- */
/* -- Tests -- */
/* ----------- */
function debug($valid, $line) { if(!$valid){ throw new \Exception('Error: ' . var_export($valid, true) . ' - LINE: ' . $line . PHP_EOL); } }
$times = array();
$keyword = 'Salam, World!';
$dove = new Dove('abdelaziz', 1);
try { debug(sizeof($dove->Pull()) == 0, __LINE__); } catch(\Exception $ignored){}
for($i = 0; $i < 10; $i++){
    $time = $dove->Push($keyword . "-$i");
    debug(!empty($time), __LINE__);
    $times[] = $time;
}
for($i = 0; $i < 10; $i++){
    $value = $dove->Read($times[$i]);
    var_dump(array('value' => $value));
    debug(!empty($value), __LINE__);
    var_dump(array('($keyword . "-$i")' => ($keyword . "-$i")));
    debug($value === ($keyword . "-$i"), __LINE__);
    $current = sizeof($dove->Pull($times[$i], true));
    var_dump(array('current' => $current, '(9-$i)' => (9-$i)));
    debug($current === (9-$i), __LINE__);
}
for($i = 0; $i < 10; $i++){
    $dove->Delete($times[$i]);
    $value = $dove->Read($times[$i]);
    var_dump(array('value' => $value));
    debug(empty($value), __LINE__);
}
$dove->Delete();
die('OK');
?>