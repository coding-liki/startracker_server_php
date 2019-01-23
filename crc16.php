<?php
function crc16 ($data) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $x = (($crc >> 8) ^ ord($data[$i])) & 0xFF;
        $x ^= $x >> 4;
        $crc = (($crc << 8) ^ ($x << 12) ^ ($x << 5) ^ $x) & 0xFFFF;
    }
    return $crc;
}
function crc16_1($string) { 
$crc = 0xFFFF; 
	for ($x = 0; $x < strlen ($string); $x++) { 
		$crc = $crc ^ ord($string[$x]); 
		for ($y = 0; $y < 8; $y++) { 
			if (($crc & 0x0001) == 0x0001) { 
				$crc = (($crc >> 1) ^ 0xA001); 
			} else { 
                $crc = $crc >> 1; 
            } 
		} 
    } 
    return $crc; 
} 
// CCITT, X24
//define('CRC16POLYN',0x1021);
//define('CRC16POLYI',0x8408);

define('CRC16POLYI',0x8005);
define('CRC16POLYN',0xA001);
// for "STANDARD" use 0x8005 and 0xA001

function CRC16Normal($buffer) {
	$result = 0xFFFF;
	if ( ($length = strlen($buffer)) > 0) {
		for ($offset = 0; $offset < $length; $offset++) {
			$result ^= (ord($buffer[$offset]) << 8);
			for ($bitwise = 0; $bitwise < 8; $bitwise++) {
				if (($result <<= 1) & 0x10000) $result ^= CRC16POLYN;
				$result &= 0xFFFF; /* gut the overflow as php has no 16 bit types */
			}
		}
	}
	return $result;
}

function CRC16Inverse($buffer) {
	$result = 0xFFFF;
	if ( ($length = strlen($buffer)) > 0) {
		for ($offset = 0; $offset < $length; $offset++) {
			$result ^= ord($buffer[$offset]);
			for ($bitwise = 0; $bitwise < 8; $bitwise++) {
				$lowBit = $result & 0x0001;
				$result >>= 1;
				if ($lowBit) $result ^= CRC16POLYI;
			}
		}
	}
	return $result;
}
?>
