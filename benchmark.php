<?php

define('DOVE', 1);
require_once 'dove.php';

$write_duration = 30; // seconds
$write = 0;
$read = 0;
$delete = 0;

$dove = new Dove('benchmark', 1, '');
$keyword = 'Salam, World-%d!';

echo "====================================================" . PHP_EOL;
echo "Dove Benchmarking started at: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "====================================================" . PHP_EOL;
echo "Write messages for $write_duration sec..." . PHP_EOL;

$write_start = time();
for(;;){
    $id = $dove->Push(sprintf($keyword, $write++));
    if($write_start+$write_duration < time()) break;
}

echo "Write finished on: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "----------------------------------------------------" . PHP_EOL;
echo "Read all written messages..." . PHP_EOL;

$read_start = time();
$times = $dove->Pull();
for($i = 0; $i < sizeof($times); $i++){
    $message = $dove->Read($times[$i]);
    $read += empty($message) ? 0 : 1;
}
$read_finish = time();

echo "Read finished on: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "----------------------------------------------------" . PHP_EOL;
echo "Delete all written messages..." . PHP_EOL;

$delete_start = time();
for($i = 0; $i < sizeof($times); $i++){
    $dove->Delete($times[$i]);
    $delete++;
}
$delete_finish = time();

echo "Delete finished on: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "====================================================" . PHP_EOL;

if(empty($write_duration)) $write_duration = 1;
if(empty($read_duration)) $read_duration = 1;
if(empty($delete_duration)) $delete_duration = 1;

$write_speed = $write / $write_duration;
$read_duration = $read_finish - $read_start;
$read_speed = $read / ($read_duration);
$delete_duration = $delete_finish - $delete_start;
$delete_speed = $delete / ($delete_duration);

$average = ($write + $read + $delete);
$average_speed = ($write_speed + $read_speed + $delete_speed);

$write = number_format($write);
$read = number_format($read);
$delete = number_format($delete);
$average = number_format($average);

$write_speed = number_format($write_speed);
$read_speed = number_format($read_speed);
$delete_speed = number_format($delete_speed);
$average_speed = number_format($average_speed);

echo "Write Count $write (msg) in $write_duration sec" . PHP_EOL;
echo "Write Speed $write_speed (msg/sec)." . PHP_EOL;
echo "----------------------------------------------------" . PHP_EOL;
echo "Read Count $read (msg) in $read_duration sec" . PHP_EOL;
echo "Read Speed $read_speed (msg/sec)." . PHP_EOL;
echo "----------------------------------------------------" . PHP_EOL;
echo "Delete Count $delete (msg) in $delete_duration sec" . PHP_EOL;
echo "Delete Speed $delete_speed (msg/sec)." . PHP_EOL;
echo "----------------------------------------------------" . PHP_EOL;
echo "Average Count $average (msg)." . PHP_EOL;
echo "Average Speed $average_speed (msg/sec)." . PHP_EOL;
echo "====================================================" . PHP_EOL;
?>