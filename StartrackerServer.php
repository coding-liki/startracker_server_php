<?php

require_once  __DIR__.'/StartrackerParser.php';
require_once  __DIR__.'/DbAccess.php';

class StartrackerServer{
    
    private $parser           = null; // Объект парсинга ответов
    private $white_imei_array = []; // Список доверенных устройств
    private $logic_function   = null; // Функция, реализующая логику сервера после установки связи с устройством
    private $master_socket    = null; // Основной сокет сервера
    private $address          = null; // Адрес который слушает сервер
    private $port             = null; // Порт на котором работает сервер
    
    private $clients         = []; // Массив подключённых клиентов
    private $clients_imeis   = []; // Массив Imei Кодов подключённых устройств
    private $clients_max_num = 0; // Максимальное количество подключённых клиентов
    private $to_delete = [];    
    private $last_read = [];
    private $db = null;
    
    /*
     * Конструктор класса
     * 
     * $address:     Адрес который слушает сервер
     * $port:        Порт на котором работает сервер
     * $max_clients: Максимальное количество подключённых клиентов
     *
     * * */
    public function __construct($address, $port, $max_clients) {
        $this->master  = socket_create(AF_INET,SOCK_STREAM, 0);
        $this->address = $address;
        $this->port    = $port;

        $this->clients_max_num = $max_clients;

        // Разрешаем повторное использование адреса
        if (!socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)) { 
            echo socket_strerror(socket_last_error($this->master)); 
            exit;
        }
        
        $res  = true;
        
        // Подключаем прослушку порта
        $res &= @socket_bind($this->master, $address, $port);
        $res &= @socket_listen($this->master);

        // Умираем в случае ошибок
        if (!$res) {
            die ("Could_not bind and listen $address: $port\n");
        }
    }

    public function setDbAccess($resource) {
        $this->db = new DbAccess($resource);
    }

    public function getLastRead($key) {
        if (key_exists($key, $this->last_read)) {
            return $this->last_read[$key];
        }
        return 0;
    }
    public function getClients() {
        return $this->clients;
    }

    public function setWhiteImeiArray($whites) {
        $this->white_imei_array = $whites;
    }

    /**
     * Запрос доверенных IMEI из базы.
     */
    public function checkImei() {
        $this->db->query("SELECT * FROM known_imeis");
        $imeis = $this->db->pgFetchAll();

        $known_imeis = [];
        foreach($imeis as $imei) {
            $known_imeis[] = $imei['imei'];
        }
        $this->setWhiteImeiArray($known_imeis);
    }

    /**
     * Поиск транспортного средства в базе
     *
     * $imei: IMEI устройства для поиска
     *
     */
    public function findVehicleByImei($imei) {
        $this->db->query("SELECT * FROM vehicle WHERE imei = $1", $imei);
        return $this->db->pgFetchAll();
    }

    /**
     * Создание лога геоданных транспортного средства.
     *
     * $vehicle_id: id транспортного средства
     * $longitude:  долгота
     * $latitude:   широта
     * $altitude:   высота над уровнем моря
     *
     */
    public function addGeoLog($vehicle_id, $longitude, $latitude, $altitude, $timestamp) {
        $query = 'INSERT INTO geolog(vehicle_id, longitude, latitude, altitude, time) VALUES($1, $2, $3, $4, $5)';
        $this->db->query($query, [$vehicle_id, $longitude, $latitude, $altitude, $timestamp]);
    }

    /**
     * Чтение данных из сокета до победного
     *
     */
    public function readAllData($client) {
        $data = "";
        $buf = socket_read($client, 255);
        while(!empty($buf)) {
            $data .= $buf;
            $len = strlen($buf);
            if($len < 255) {
                break;
            }
            $buf = socket_read($client, 255);
        }

        return $data;
    }

    public function run($idle_func, $send_function) {
            
        $abort = false;
        $read = [$this->master];

        $NULL = NULL; // Так надо
        // Главный цикл сервера
        while (!$abort) {
            $num_changed = socket_select($read, $NULL, $NULL, 0, 10); // Количество изменений
    
            if ($num_changed > 0) {
                echo "Сокетов для проверки $num_changed\n";
                //sleep(1);

                for($i = 0; $i < count($this->to_delete); $i++) {
                    unset($this->clients[$this->to_delete[$i]]);
                    unset($this->clients_imeis[$this->to_delete[$i]]);
                }
                if (in_array($this->master, $read)) {
                    $this->checkImei();
                    if (count($this->clients) < $this->clients_max_num) {
                        $this->clients[]= socket_accept($this->master);
                        $this->clients_imeis[] =  count($this->clients)-1;
                        echo "Принято подключение (" . count($this->clients)  . " of ".$this->clients_max_num." clients)\n";
                    }
                }
                
                foreach ($this->clients as $key => $client) {
                    if (in_array($client, $read)) {
                        
                        //$data = $this->readAllData($client);
                        //echo "Данные \n***\n$data\n***\n";
                        //continue;
                        //echo "Нужно обработать $key\n";
                        $good = false;
                        $imei = $this->clients_imeis[$key];
                        $data = $this->readAllData($client);
                         
                        if (!in_array($imei, $this->white_imei_array)) {
                            
                            echo "Нужно проверить IMEI у $key\n";

                            $ident = substr($data, 0,4);

                            //echo "ident = '$ident'";
                            $input = substr($data, 4,10);

                            echo "imei = $input\n";
                            $this->last_read[$key] = time(); 
                            
                            if (empty($input) || !in_array($input, $this->white_imei_array)) {
                                //echo "Плохой или пустой IMEI!!!\n";
                                socket_shutdown($client);

                                unset($this->clients[$key]);
                                unset($this->last_read[$this->clients_imeis[$key]]);
                                unset($this->clients_imeis[$key]);
                            } else {
                                //echo "Хороший доверенный IMEI\n";
                                $good = true;
                                $this->clients_imeis[$key] = $input;
                                $this->last_read[$input] = time();
                            }
                        } else {
                            echo "Возможно нужна аутентификация у $key\n";
                            $good = true;
                        }

                        if ( $good ) {
                            
                            if (empty($data)) {
                                //echo "DATA IS EMPTY!!!\n";
                                $this->to_delete[] = $key;
                                continue;
                            }
                            
                            echo "data is ***\n$data\n***\n"; 
                            
                            $idle_func($this,new StartrackerParser($client, $this->clients_imeis[$key]), $data);
                            $this->last_read[$this->clients_imeis[$key]] = time();
                            
                            //$abort = true;
                        } else {
                            echo "Bad";
                        }
                    }
                }
            } else if(count($this->clients) > 0) {
                foreach($this->clients as $key => $client) {
                    $send_function($this, new StartrackerParser($client, $this->clients_imeis[$key]));
                }
            }
            $read   = $this->clients;
            $read[] = $this->master;
        }
    }


    public function sendCommand($command, $client){
        socket_write($client, $command);
        //echo "command = \n***\n$str\n***\n";
    }


    public function destroy() {
        socket_shutdown($this->master);
    }
}

?>
