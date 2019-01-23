<?php
require_once  'ReadBuffer.php';

class StartrackerParser {
    
    private $client        = null; // Сокет для парсинга
    private $imei          = ''; // IMEI код устройства       
    private $buffer        = ''; // Буфер данных для парсинга пакета    
    
    /**
     * Основная структура пакетов
     *
     */
    private $maker = ''; 
    private $packet_type = '';
    private $time = 0;
    private $date = 0;

    public $timestamp = 0;
    public $usefull = true;
    public $latitude = 0;
    public $latitude_mark = '';
    public $longitude = 0;
    public $longitude_mark = '';
    public $speed = 0;
    public $direction = 0;
    public $vehicle_status = [];

    public $last = []; 
    private $vehicle_status_keys = [
        'temperature',
        'passw_3_error',
        'gprs_backed_up',
        'oil_cut_off',
        'battery_demolition',
        'sensor_1_high_high',
        'sensor_2_high_high',
        'sensor_1_low_on',
        'gps_rec_fault',
        'res',
        'res',
        'terminal_by_backup',
        'battery_removed',
        'gps_antenna_disconnect',
        'gps_antenna_short_circuit',
        'sensor_2_low_on',
        'door_open', 
        'fortification_on',
        'acc_off',
        'res',
        'engine',
        'custom_alarm',
        'overspeed',
        'theft_alarm',
        'robbery_alarm',
        'overspeed_speed',
        'illegal_ignition_alarm',
        'entering_alarm',
        'gps_antenna_disconnect_alarm',
        'gps_antenna_short_circuit_alarm',
        'out_alarm'
    ];

    /** 
     * Конструктор парсера
     * 
     * $client : сокет для парсинга
     * $imei   : IMEI код устройства
     *
     */
    public function __construct($client, $imei) {
        $this->client = $client;
        $this->imei   = $imei;
    }


    public function getClient() {
        return $this->client;
    }
    
    public function getCommands() {
        return $this->commands;
    }

    public function getImei() {
        return $this->imei;
    }
    
    /** 
     * Парсим AVL пакет из сокета
     *
     */
    public function parseAvlPacket () {
        if (is_null($this->client)) {
            return 0;
        }
    }

    /**
     * Парсим AVL пакет из сокета
     * 
     * $data: массив данных для парсинга пакета
     *
     */
    public function readAvlPacket ($data) {
        $buffer = new ReadBuffer($data);
        
        $data = str_replace("*", "", $data);
        $data = str_replace("#", "", $data);

        $mass = explode(',', $data);
        
        $this->maker = $mass[0];
        $this->packet_type = $mass[2];
        $i = 3; 
        
        if ($this->packet_type != 'V1') {
            $this->packet_type = ['type' => $this->packet_type, 'command_type' => $mass[3], 'result' => $mass[4]];
            if ($this->packet_type['command_type'] == 'D1') {
                $this->packet_type['result'] .= $mass[5];
                $i = 7;
            } else {
                $i = 6;
            }
        }
        
        $buffer = new ReadBuffer($mass[$i]);

        $hh = (int) $buffer->readSlice(2);
        $mm = (int) $buffer->readSlice(2);
        $ss = (int) $buffer->readSlice(2);

        $this->time = $ss+$mm*60+$hh*60*60;
        
        $this->usefull = $mass[$i+1];

        $buffer = new ReadBuffer($mass[$i+2]);

        $this->latitude = (double) $buffer->readSlice(2) ;
        $minutes = (double) $buffer->readSlice(7);
        $this->latitude += $minutes/60;
        $this->latitude_mark = $mass[$i+3];

        $buffer = new ReadBuffer($mass[$i+4]);

        $this->longitude = (double) $buffer->readSlice(3);
        $minutes = (double) $buffer->readSlice(7);
        $this->longitude += $minutes/60;
    	$this->longitude_mark = $mass[$i+5];
    	$this->speed = $mass[$i+6];
    	$this->direction = $mass[$i+7];
        
        $buffer = new ReadBuffer($mass[$i+8]);

        $dd = $buffer->readSlice(2);
        $mm = $buffer->readSlice(2);
        $yy = $buffer->readSlice(2);
        
        $dateobj = DateTime::createFromFormat("d,m,y","$dd,$mm,$yy");
        $dateobj->setTime(0,0,0);
        
        $this->date = $dateobj->getTimestamp();
    	$this->timestamp = $this->date + $this->time;
        $vehicle = unpack("N", hex2bin($mass[$i+9]))[1];
        $this->vehicle_status = [];
        for ($j=31; $j >= 0; $j--) {
            $bit = !(($vehicle >>  $j) & 1);
            $this->vehicle_status[] = $bit;
        }
        
        $len = count($mass);
        for ($j = $i+10; $j < $len ; $j++) {
            $this->last[] = $mass[$j];
        }
    }

    public function getUsefulState() {
        $useful = [];
        for($i = 0; $i<count($this->vehicle_status); $i++) {
            if ($this->vehicle_status[$i]) {
                $useful[] = $this->vehicle_status_keys[$i];
            }
        }
        return $useful;
    }

}
?>
