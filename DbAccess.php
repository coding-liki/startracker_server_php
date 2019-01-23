<?php

/**
 * Класс доступа к базе данных
 *
 */
class DbAccess {

    /** Ресурс подключения к БД */
    private $resource = null;
    
    /** Тип базы данных */
    private $db_type  = '';
    private $known_types = ['pgsql'];
    
    /** результат последнего запроса */
    private $query_result = null;

    public function __construct($db_resource, $db_type = 'pgsql') {
        
        if (!in_array($db_type, $this->known_types) ) {
            $error =  "Unknown DB type '$db_type'\n Known are '".
                trim(implode(', ', $this->known_types), ', ').
                "'\n";
            trigger_error("Fatal error: $error", E_USER_ERROR);
            return;
        }
        $this->resource = $db_resource;
        
        $this->db_type  = $db_type;

    }

    public function query($query, ...$args) {
        if ($this->db_type == 'pgsql') {
            if (empty($args)) {
                $params = [];
            } else {
                $params = $args[0];
            }
            return $this->pgQuery($query, $params);
        } 
    }
    public function pgQuery ($query, $params) {
        
        if (!is_array($params)) {
            $params = [$params];
        }
        
        $this->query_result = pg_query_params($this->resource, $query, $params);
        
        if ($this->query_result) {
            return $this;
        } else {
            return false;
        }
        
    }
    public function pgFetchAll() {
        return pg_fetch_all($this->query_result);
    }
}
?>
