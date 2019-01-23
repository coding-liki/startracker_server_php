<?php

class ReadBuffer{
    
    private $data     = ''; // Бинарная строка данных
    private $position = 0; // Текущая позиция чтения
    private $eof = false;
    private $length = 0;
    /** 
     * Конструктор класса
     * 
     * $data: Бинарная строка данных
     * $pos: Начальная позиция
     *
     */
    public function __construct($data, $pos = 0) {
        $this->data = $data;
        $this->position = $pos;
        $this->length = strlen($data);
    }

    /**
     * Проверяет и устанавливает флпг окончания строки
     *
     */
    public function checkEof() {
        if ($this->position >= $this->length) {
            $this->eof = true;
            return true;
        }
        
        $this->eof = false;
        return false;
    }
    
   
    public function isEof(){
        return $this->eof;
    }

    /**
     * Читает символ и сдвигает позицию на один байт
     *
     */
    public function readChar() {
        if ($this->isEof()) {
            return false;
        }
        $ch = unpack("C", $this->data[$this->position])[1];
        $this->position++;
        
        $this->checkEof();
        return $ch;
    }

    /**
     * Читает символ из указанной позиции
     * не сдвигает позицию
     *
     */
    public function getChar($pos) {
        if ($pos < 0 && $pos >= $this->length) {
            return false;
        }
        $ch = unpack("C", $this->data[$pos])[1];

        return $ch;
    }

    /**
     * Читает U_SHORT и сдвигает позицию на 2 байта
     *
     */
    public function readUnsignedShort() {
        if ($this->isEof() or $this->position+2 > $this->length) {
            return false;
        }
        $ch = unpack("S", substr($this->data , $this->position, 2))[1];
        $this->position += 2;
        
        $this->checkEof();
        return $ch;
    }

    /**
     * Читает U_SHORT из указанной позиции
     * не сдвигает позицию
     *
     */
    public function getUnsignedShort($pos) {
        if ($pos < 0 && $pos+2 > $this->length) {
            return false;
        }
        $ch = unpack("S", substr($this->data , $pos, 2))[1];

        return $ch;
    }

    /**
     * Читает S_SHORT и сдвигает позицию на 2 байта
     *
     */
    public function readSignedShort() {
        if ($this->isEof() or $this->position+2 > $this->length) {
            return false;
        }
        $ch = unpack("s", substr($this->data , $this->position, 2))[1];
        $this->position += 2;
        
        $this->checkEof();
        return $ch;
    }

    /**
     * Читает S_SHORT из указанной позиции
     * не сдвигает позицию
     *
     */
    public function getSignedShort($pos) {
        if ($pos < 0 && $pos+2 > $this->length) {
            return false;
        }
        $ch = unpack("s", substr($this->data , $pos, 2))[1];

        return $ch;
    }
    
    /**
     * Читает U_INT и сдвигает позицию на 4 байта
     *
     */
    public function readUnsignedInt() {
        if ($this->isEof() or $this->position+4 > $this->length) {
            return false;
        }
        $ch = unpack("N", substr($this->data , $this->position, 4))[1];
        $this->position += 4;
        
        $this->checkEof();
        return $ch;
    }

    /**
     * Читает U_INT из указанной позиции
     * не сдвигает позицию
     *
     */
    public function getUnsignedInt($pos) {
        if ($pos < 0 && $pos+4 > $this->length) {
            return false;
        }
        $ch = unpack("N", substr($this->data , $pos, 4))[1];

        return $ch;
    }

    /**
     * Читает U_LONG и сдвигает позицию на 8 байт
     *
     */
    public function readUnsignedLong() {
        if ($this->isEof() or $this->position+8 > $this->length) {
            return false;
        }
        $ch = unpack("J", substr($this->data , $this->position, 8))[1];
        $this->position += 8;
        
        $this->checkEof();
        return $ch;
    }

    /**
     * Читает U_LONG из указанной позиции
     * не сдвигает позицию
     *
     */
    public function getUnsignedLong($pos) {
        if ($pos < 0 && $pos+8 > $this->length) {
            return false;
        }
        $ch = unpack("J", substr($this->data , $pos, 8))[1];

        return $ch;
    }
    /**
     * Читаем заданное количество байт и распаковываем в массив по формату
     * 
     * $format: Формат для распаковки функцией unpack
     * $size: количество байт для распаковыки и сдвига позиции чтения
     *
     */
    public function readBytes($format, $size) {
        if ($this->isEof() or $this->position+$size > $this->length) {
            return false;
        }

        $ch =  unpack($format, substr($this->data , $this->position, $size)); 

        $this->position += $size;
        return $ch;
    }
    
    /**
     * Читаем заданное количество байт из заданной позиции 
     * и распаковываем в массив по формату
     * Не свигаем позицию
     *
     * $format: Формат для распаковки функцией unpack
     * $size: количество байт для распаковыки 
     *
     */
    public function getBytes($format, $size, $pos) {
        if ($this->isEof() or $pos+$size > $this->length) {
            return false;
        }

        $ch =  unpack($format, substr($this->data , $pos, $size)); 

        return $ch;
    }
    
    /**
     * Возвращает длину буфера
     *
     */
    public function getLength() {
        return $this->length;
    }
    /**
     * Возвращает подстроку из буфера
     * 
     * $start: начало подстроки
     * $num:   количество символов в подстроке
     *
     */
    public function getSlice($start, $num = 1) {
        return substr($this->data, $start, $num);
    }

    public function readSlice($num, $add = 0) {
        $data = substr($this->data, $this->position + $add, $num);
        
        $this->position += $add+$num;
        return $data;
    }

}
?>
