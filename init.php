<?php

require_once  __DIR__.'/crc16.php';
require_once  __DIR__.'/StartrackerParser.php'; // Парсер запросов и ответов
require_once  __DIR__.'/StartrackerServer.php';
require_once  __DIR__.'/local/system.php';


$cc = 0;

$idle_func = function($server, $parser, $data){
    global $cc;

    $cc++;

    //if ($cc > 9) return 1;
    if ( $parser->getImei() == 0) {
        //echo "Bad parser imei\n";
        return;
    }   
    //echo "**".$parser->getImei()."**\n";

    
    $parser->readAvlPacket($data);
    $vehicle = $server->findVehicleByImei($parser->getImei())[0];

    $vehicle_id = $vehicle['id'];
    
    $altitude  = 0;
    $longitude = $parser->longitude;
    $latitude  = $parser->latitude;
    $timestamp = $parser->timestamp;
    echo "координаты $longitude, $latitude\n";
    //echo $parser->last[2]."\n";
    $server->addGeoLog($vehicle_id, $longitude, $latitude, $altitude, $timestamp);
    print_r($parser->getUsefulState());
};

$ac = false;
$no_commands = false;
$send_function = function($server, $parser) {
    global $ac, $no_commands, $db_link;
    $last_read = $server->getLastRead($parser->getImei());
    
    $db_access = new DbAccess($db_link);

    if(time() - $last_read > 6 && $parser->getImei() != 0) {
        if ( !$ac ) {
            //echo "We can send command to".$parser->getImei()."\n";
            $ac = true;
        }
    } else {
        $ac = false;
        $no_commands = false;
        return;
    }

    //return ;
    $query = 'SELECT id, command, args FROM commands_queue WHERE imei = $1 AND NOT done';
    $commands = $db_access->query($query, $parser->getImei())->pgFetchAll();
    
    if (empty($commands)) {
        if (!$no_commands) {
            //echo "No commands to ".$parser->getImei()."\n";
            $no_commands = true;
        }
        return;
    } else {
        echo count($commands)." commands to ".$parser->getImei()."\n";
        $no_commands = false;
    }
    
    $id = $commands[0]['id'];
    $command = $commands[0]['command'];
    $args = $commands[0]['args'];
    
    $client = $parser->getClient();
    $time = date("His", time()-3*60*60);
    echo "Sending command \n\t#$command# \nwith args\n\t#$args#\n"; 
    $to_send = "*HQ,".$parser->getImei().",".$command.",".$time;
   // $to_send = $command;
    if(strlen($args) > 0){
        $to_send.=",".$args;
    }

    $to_send.="#";
    echo "$to_send\n";
    $server->sendCommand($to_send, $client);

    $query = 'UPDATE commands_queue SET done=true WHERE id=$1';
    $db_access->query($query, $id);
    //$length = $parser->parseCommandAvl();
    //$commands = $parser->getCommands();
    //print_r($commands);
};

set_time_limit(0); // Мы хотим жить вечно

$address = "0.0.0.0";
$port = 8181;
$max_clients    = 200;
echo "Starting\n";

$server = new StartrackerServer($address, $port, $max_clients);
$server->setDbAccess($db_link);
//$server->setWhiteImeiArray(['352093082812644']);
$server->run($idle_func,$send_function);

$sever->destroy();

