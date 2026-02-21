<?php

namespace Picqer\Barcode;

class BarcodeGeneratorPNG
{
    const TYPE_CODE_128 = 'C128';
    
    private $imageType = 'png';
    private $dpi = 72;
    private $scale = 1.5;
    private $padding = 8;
    
    private $code128_characters = [
        ' ' => [0, '11011001100'],
        '!' => [1, '11001101100'],
        '"' => [2, '11001100110'],
        '#' => [3, '10010011000'],
        '$' => [4, '10010001100'],
        '%' => [5, '10001001100'],
        '&' => [6, '10011001000'],
        "'" => [7, '10011000100'],
        '(' => [8, '10001100100'],
        ')' => [9, '11001001000'],
        '*' => [10, '11001000100'],
        '+' => [11, '11000100100'],
        ',' => [12, '10110011100'],
        '-' => [13, '10011011100'],
        '.' => [14, '10011001110'],
        '/' => [15, '10111001100'],
        '0' => [16, '10011101100'],
        '1' => [17, '10011100110'],
        '2' => [18, '11001110010'],
        '3' => [19, '11001011100'],
        '4' => [20, '11001001110'],
        '5' => [21, '11011100100'],
        '6' => [22, '11001110100'],
        '7' => [23, '11101101110'],
        '8' => [24, '11101001100'],
        '9' => [25, '11100101100'],
        ':' => [26, '11100100110'],
        ';' => [27, '11101100100'],
        '<' => [28, '11100110100'],
        '=' => [29, '11100110010'],
        '>' => [30, '11011011000'],
        '?' => [31, '11011000110'],
        '@' => [32, '11000110110'],
        'A' => [33, '10100011000'],
        'B' => [34, '10001011000'],
        'C' => [35, '10001000110'],
        'D' => [36, '10110001000'],
        'E' => [37, '10001101000'],
        'F' => [38, '10001100010'],
        'G' => [39, '11010001000'],
        'H' => [40, '11000101000'],
        'I' => [41, '11000100010'],
        'J' => [42, '10110111000'],
        'K' => [43, '10110001110'],
        'L' => [44, '10001101110'],
        'M' => [45, '10111011000'],
        'N' => [46, '10111000110'],
        'O' => [47, '10001110110'],
        'P' => [48, '11101110110'],
        'Q' => [49, '11010001110'],
        'R' => [50, '11000101110'],
        'S' => [51, '11011101000'],
        'T' => [52, '11011100010'],
        'U' => [53, '11011101110'],
        'V' => [54, '11101011000'],
        'W' => [55, '11101000110'],
        'X' => [56, '11100010110'],
        'Y' => [57, '11101101000'],
        'Z' => [58, '11101100010']
    ];

    private $special_chars = [
        'START_B' => [104, '11010010000'],
        'STOP' => [106, '1100011101011']
    ];
    
    public function getBarcode($code, $type)
    {
        $barcodeData = $this->getBarcodeData($code);
        return $this->generateImage($barcodeData);
    }
    
    private function getBarcodeData($code)
    {
        // Start with Code 128B
        $binary = $this->special_chars['START_B'][1];
        
        // Calculate checksum
        $checksum = $this->special_chars['START_B'][0]; // Start with value for START B
        $weight = 1;
        
        // Add data
        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            if (isset($this->code128_characters[$char])) {
                $binary .= $this->code128_characters[$char][1];
                $checksum += ($this->code128_characters[$char][0] * $weight);
                $weight++;
            }
        }
        
        // Add checksum
        $checksum = $checksum % 103;
        
        // Find the character that represents this checksum value
        foreach ($this->code128_characters as $char => $data) {
            if ($data[0] === $checksum) {
                $binary .= $data[1];
                break;
            }
        }
        
        // Add stop character
        $binary .= $this->special_chars['STOP'][1];
        
        // Add quiet zone
        $binary = str_repeat('0', 10) . $binary . str_repeat('0', 10);
        
        return $binary;
    }
    
    private function generateImage($barcodeData)
    {
        $width = strlen($barcodeData) * $this->scale + (2 * $this->padding);
        $height = 40 * $this->scale;
        
        $im = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        
        imagefilledrectangle($im, 0, 0, $width, $height, $white);
        
        for ($i = 0; $i < strlen($barcodeData); $i++) {
            if ($barcodeData[$i] == '1') {
                imagefilledrectangle(
                    $im,
                    $i * $this->scale + $this->padding,
                    0,
                    ($i + 1) * $this->scale + $this->padding - 1,
                    $height,
                    $black
                );
            }
        }
        
        ob_start();
        imagepng($im);
        $imageData = ob_get_clean();
        imagedestroy($im);
        
        return $imageData;
    }
} 