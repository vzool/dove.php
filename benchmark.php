<?php

define('DOVE', 1);
date_default_timezone_set("Asia/Riyadh");
require_once 'dove.php';

$total = 0;

foreach([
    Dove::INTEGRITY_DISABLED => 'Dove::INTEGRITY_DISABLED',
    Dove::INTEGRITY_GENERATE_HASH => 'Dove::INTEGRITY_GENERATE_HASH',
    Dove::INTEGRITY_VERIFY_HASH => 'Dove::INTEGRITY_VERIFY_HASH',
    Dove::INTEGRITY_GENERATE_HASH | Dove::INTEGRITY_VERIFY_HASH => 'Dove::INTEGRITY_GENERATE_HASH | Dove::INTEGRITY_VERIFY_HASH',
    Dove::INTEGRITY_GENERATE_SIGNATURE => 'Dove::INTEGRITY_GENERATE_SIGNATURE',
    Dove::INTEGRITY_VERIFY_SIGNATURE => 'Dove::INTEGRITY_VERIFY_SIGNATURE',
    Dove::INTEGRITY_GENERATE_SIGNATURE | Dove::INTEGRITY_VERIFY_SIGNATURE => 'Dove::INTEGRITY_GENERATE_SIGNATURE | Dove::INTEGRITY_VERIFY_SIGNATURE',
    Dove::INTEGRITY_ALL => 'Dove::INTEGRITY_ALL',
] as $intgrity_value => $intgrity_name) {

    $write_duration = 30; // seconds
    $write = 0;
    $read = 0;
    $delete = 0;
    
    $bold_separator = "==========================================================================";
    $thin_separator = "--------------------------------------------------------------------------";
    
    echo PHP_EOL . PHP_EOL . "INTEGRITY_TYPE = $intgrity_name" . PHP_EOL . PHP_EOL;
    
    $dove = new Dove('benchmark', 1, $intgrity_value, '');
    $keyword = 'Salam, World-%d!';
    
    echo $bold_separator . PHP_EOL;
    echo "Dove Benchmarking started at: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo $bold_separator . PHP_EOL;
    echo "Write messages for (" . gmdate("H:i:s", $write_duration) .  ") ..." . PHP_EOL;
    
    $write_start = time();
    for(;;){
        $id = $dove->Push(sprintf($keyword, $write++));
        if($write_start+$write_duration < time()) break;
    }
    
    echo "Write finished on: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo $thin_separator . PHP_EOL;
    echo "Read all written messages..." . PHP_EOL;
    
    $read_start = time();
    $times = $dove->Pull();
    for($i = 0; $i < sizeof($times); $i++){
        $message = $dove->Read($times[$i]);
        $read += empty($message) ? 0 : 1;
    }
    $read_finish = time();
    
    echo "Read finished on: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo $thin_separator . PHP_EOL;
    echo "Delete all written messages..." . PHP_EOL;
    
    $delete_start = time();
    for($i = 0; $i < sizeof($times); $i++){
        $dove->Delete($times[$i]);
        $delete++;
    }
    $delete_finish = time();
    
    echo "Delete finished on: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo $bold_separator . PHP_EOL;
    
    if(empty($write_duration)) $write_duration = 1;
    if(empty($read_duration)) $read_duration = 1;
    if(empty($delete_duration)) $delete_duration = 1;
    
    $write_speed = $write / $write_duration;
    $read_duration = $read_finish - $read_start;
    $read_speed = $read / ($read_duration);
    $delete_duration = $delete_finish - $delete_start;
    $delete_speed = $delete / ($delete_duration);
    
    $average = number_format($write + $read + $delete);
    $average_speed = number_format($write_speed + $read_speed + $delete_speed);
    
    $write = number_format($write);
    $read = number_format($read);
    $delete = number_format($delete);
    
    $write_speed = number_format($write_speed);
    $read_speed = number_format($read_speed);
    $delete_speed = number_format($delete_speed);
    
    $total_duration = $write_duration + $read_duration + $delete_duration;
    $total += $total_duration;

    $total_duration = number_format($total_duration);
    
    echo "Write Count $write (msg) in (" . gmdate("H:i:s", $write_duration) . ")" . PHP_EOL;
    echo "Write Speed $write_speed (msg/sec)." . PHP_EOL;
    echo $thin_separator . PHP_EOL;
    echo "Read Count $read (msg) in (" . gmdate("H:i:s", $read_duration) . ")" . PHP_EOL;
    echo "Read Speed $read_speed (msg/sec)." . PHP_EOL;
    echo $thin_separator . PHP_EOL;
    echo "Delete Count $delete (msg) in (" . gmdate("H:i:s", $delete_duration) . ")" . PHP_EOL;
    echo "Delete Speed $delete_speed (msg/sec)." . PHP_EOL;
    echo $thin_separator . PHP_EOL;
    echo "Average Count $average (msg)." . PHP_EOL;
    echo "Average Speed $average_speed (msg/sec)." . PHP_EOL;
    echo $bold_separator . PHP_EOL;
    echo "Dove Benchmarking done at: (" . date('Y-m-d H:i:s') . ") and took (" . gmdate("H:i:s", $total_duration) . ")" . PHP_EOL;
    echo $bold_separator . PHP_EOL;
}

echo $bold_separator . PHP_EOL;
echo "Dove Benchmarking done at: (" . date('Y-m-d H:i:s') . ") and all took (" . gmdate("H:i:s", $total) .")" . PHP_EOL;
echo $bold_separator . PHP_EOL;
?>