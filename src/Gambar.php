<?php
namespace Gambar;
use Gambar\GambarException;

class Gambar {

    private $resource   = array();
    private $destination= array();
    private $temporary  = false;
    private $font       = null;

    public static function set($resource, $temporary = false){
        return new Gambar($resource, $temporary);
    }

    public function __construct($resource, $temporary = false) {
        $init = $this->is_supported($resource);
        $this->temporary= $temporary;
        if($init){
            $this->clear_temporary($resource);
        }
    }

    public function load_font($fontpath) {
        if(!file_exists($fontpath)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $pinfo = pathinfo($fontpath);
        if($pinfo['extension'] != 'ttf'){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        echo __DIR__;
    }

    public function frame($weights, $color = '#FFFFFF') {
        $res_stream = $this->get_resource('resource', 'stream');
        if(is_null($res_stream)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $res_sizes  = $this->get_resource('resource', 'sizes');
        $des_stream = $this->get_resource('destination', 'stream');
        if(is_null($des_stream)){
            $this->set_resource('destination', 'sizes', $res_sizes);
        }
        $weights= $this->css_size($weights);
        $color  = $this->hex_rgb($color);
        $adj_w  = $res_sizes['width'] - $weights['x1'] - $weights['x2'];
        $adj_h  = $res_sizes['height'] - $weights['y1'] - $weights['y2'];
        $this->set_resource('destination', 'coordinates', array('x' => $weights['x1'], 'y' => $weights['y1']));
        $this->set_resource('resource', 'sizes', array('width' => $adj_w, 'height' => $adj_h));
        $des_stream = $this->get_resource('destination', 'stream');
        imagefill($des_stream, 0, 0, $color);
        $this->copy();
        $temp = $this->create();
        return self::set($temp, true);
    }

    public function watermark($watermark, $position, $height, $alpha = 100) {
        // unimplemented
        /*
         * switch main image into destination
         * create image from $watermark and set to resource
         * copy with transparency and `create` follows main image extension
         */
    }

    public function resize($sizes) {
        $destination = $this->get_resource('destination');
        if(!is_null($destination)){
            $this->set_resource('destination');
        }
        $this->set_resource('destination', 'sizes', $this->generate_sizes($sizes));
        $this->resampled();
        $temp = $this->create();
        return self::set($temp, true);
    }

    public function crop ($x, $y, $sizes) {
        $destination = $this->get_resource('destination');
        if(!is_null($destination)){
            $this->set_resource('destination');
        }
        $this->set_resource('resource', 'coordinates', array('x' => $x, 'y' => $y));
        $this->set_resource('destination', 'sizes', $this->generate_sizes($sizes));
        $this->copy();
        $temp = $this->create();
        return self::set($temp, true);
    }

    public function save($directory, $newname, $quality = 100) {
        $destination = $this->get_resource('destination');
        if(!is_null($destination)){
            $this->set_resource('destination');
        }
        $this->resampled();
        return $this->create($directory, $newname, $quality);
    }

    public function show() {
        $this->resampled();
        $this->create($directory = null, $newname = null, $quality = 100, true);
        exit;
    }

    private function generate_sizes($value) {
        $is_array   = is_array($value);
        $is_integer = is_int($value);
        $is_percent = (!$is_array) ? preg_match('/%/', $value) : false;
        $width      = 0;
        $height     = 0;
        $res_size   = $this->get_resource('resource', 'sizes');
        if(is_null($res_size)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $res_w      = $res_size['width'];
        $res_h      = $res_size['height'];
        if($is_array){
            if(count($value) >= 2){
                list($width, $height) = $value;
            }
        }
        if($is_integer) {
            if($res_w <= $res_h){ //portrait
                $height = $value;
                $ratio  = round(($value / $res_h), 2);
                $width  = floor($res_w * $ratio);
            }else{ // landscape or square
                $width  = $value;
                $ratio  = round(($value / $res_w), 2);
                $height = floor($res_h * $ratio);
            }
        }
        if($is_percent){
            $value = preg_replace('/%/', '', $value);
            $disc_w= floor($res_w * ($value / 100));
            $disc_h= floor($res_h * ($value / 100));
            $width = $disc_w;
            $height= $disc_h;
        }
        return array(
            'width'     => $width,
            'height'    => $height
        );
    }

    private function css_size($value) {
        if(is_int($value)){
            $x1 = $y1 = $x2 = $y2 = $value;
        }else{
            $parts = explode(' ', $value);
            if(count($parts) == 2){
                $y1 = $y2 = $parts[0];
                $x1 = $x2 = $parts[1];
            }else if(count($parts) == 3){
                $y1 = $parts[0];
                $x2 = $parts[1];
                $y2 = $parts[2];
                $x1 = $parts[1];
            }else if(count($parts) == 4){
                $y1 = $parts[0];
                $x2 = $parts[1];
                $y2 = $parts[2];
                $x1 = $parts[3];
            }
        }
        return array(
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2
        );
    }

    private function hex_rgb($value, $type = 'hex') {
        $color = str_replace('#', '', strtoupper($value));
        if(strlen($color) == 3) {
            $color = $color{0} . $color{0} . $color{1} . $color{1} . $color{2} . $color{2};
        }
        if($type == 'decimals'){
            $returned = array(
                'r' => hexdec($color{0}.$color{1}),
                'g' => hexdec($color{2}.$color{3}),
                'b' => hexdec($color{4}.$color{5})
            );
        }else{
            $returned = '0x' . $color;
        }
        return $returned;
    }

    private function convert_to_compression($value) {
        return ($value / 10) - 10;
    }

    private function copy($transparency = 100) {
        $resource = $this->get_resource('resource', 'stream');
        $destination = $this->get_resource('destination', 'stream');
        if(is_null($resource) || is_null($destination)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $res_coords = $this->get_resource('resource', 'coordinates');
        $res_x = $res_coords['x'];
        $res_y = $res_coords['y'];
        $res_sizes  = $this->get_resource('resource', 'sizes');
        $res_w = $res_sizes['width'];
        $res_h = $res_sizes['height'];
        $des_coords = $this->get_resource('destination', 'coordinates');
        $des_x = $des_coords['x'];
        $des_y = $des_coords['y'];
        if($transparency < 100){
            /*
                Other operations
            */
            $copy = imagecopymerge($destination, $resource, $des_x, $des_y, $res_x, $res_y, $res_w, $res_h, $transparency);
        }else{
            $copy = imagecopy($destination, $resource, $des_x, $des_y, $res_x, $res_y, $res_w, $res_h);
        }
        if(!$copy){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        return $copy;
    }

    private function resampled() {
        $resource = $this->get_resource('resource', 'stream');
        $destination = $this->get_resource('destination', 'stream');
        if(is_null($resource) || is_null($destination)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $res_coords = $this->get_resource('resource', 'coordinates');
        $res_x = $res_coords['x'];
        $res_y = $res_coords['y'];
        $res_sizes  = $this->get_resource('resource', 'sizes');
        $res_w = $res_sizes['width'];
        $res_h = $res_sizes['height'];
        $des_coords = $this->get_resource('destination', 'coordinates');
        $des_x = $des_coords['x'];
        $des_y = $des_coords['y'];
        $des_sizes  = $this->get_resource('destination', 'sizes');
        $des_w = $des_sizes['width'];
        $des_h = $des_sizes['height'];
        $resampled = imagecopyresampled($destination, $resource, $des_x, $des_y, $res_x, $res_y, $des_w, $des_h, $res_w, $res_h);
        if(!$resampled){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        return $resampled;
    }

    private function create($directory = null, $newname = null, $quality = 100, $show = false) {
        $extension  = $this->get_resource('resource', 'extension');
        $stream     = $this->get_resource('destination', 'stream');
        if(is_null($extension)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        if(is_null($stream)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        if(is_null($directory) && is_null($newname)){
            if($show === true){
                throw new GambarException("Error Processing Request", 1);
                exit;
            }
            // $directory  = './tmp';
            $directory  = sys_get_temp_dir();
            $newname    = 'tmp_' . md5(microtime()) . $extension;
        }
        if(!is_dir($directory)){
            if(!mkdir($directory, 777)){
                throw new GambarException("Error Processing Request", 1);
                exit;
            }
        }
        if($show === true){
            header('Conten-Type: image/' . $extension);
        }
        $target = rtrim($directory, '/') . '/' . $newname . '.' .$extension;
        $created= false;
        switch($extension) {
            case 'jpeg':
                if($show === true){
                    $created= imagejpeg($stream);
                }else{
                    $created= imagejpeg($stream, $target, $quality);
                }
            break;
            case 'png':
                if($show === true){
                    $created= imagepng($stream);
                }else{
                    $quality = $this->convert_to_compression($quality);
                    $created= imagepng($stream, $target, $quality);
                }
            break;
            case 'bmp':
                if($show === true){
                    $created= imagewbmp($stream);
                }else{
                    $created= imagewbmp($stream, $target);
                }
            break;
            case 'gif':
                if($show === true){
                    $created= imagegif($stream);
                }else{
                    $created= imagegif($stream, $target);
                }
            break;
        }
        if($created){
            return $target;
        }
    }

    private function clear() {
        $resource = $this->get_resource('resource', 'stream');
        $destination = $this->get_resource('destination', 'stream');
        if(!is_null($resource) && !is_null($destination)){
            imagedestroy($resource);
            imagedestroy($destination);
            $this->resource     = array();
            $this->destination  = array();
        }
    }

    private function is_temporary() {
        return $this->temporary;
    }

    private function clear_temporary($resource) {
        if($this->is_temporary()){
            @unlink($resource);
            $this->temporary = false;
        }
    }

    private function is_supported($resource) {
        if(!file_exists($resource)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $attributes = $this->get_attributes($resource);
        if(is_null($attributes)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $mimes  = array('image/jpeg','image/png','image/bmp','image/gif');
        $mime   = $attributes['mime'];
        if(!in_array($mime, $mimes)){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $extension  = str_replace('image/','',$mime);
        $this->set_resource('resource', 'mime', $mime);
        $this->set_resource('resource', 'extension', $extension);
        $stream     = call_user_func('imagecreatefrom' . $extension, $resource);
        if(!$stream){
            throw new GambarException("Error Processing Request", 1);
            exit;
        }
        $this->set_resource('resource', 'stream', $stream);
        $this->set_resource('resource', 'sizes', $attributes['sizes']);
        $this->set_resource('resource', 'coordinates', $attributes['coordinates']);
        $pathinfo = pathinfo($resource);
        $this->set_resource('resource', 'filename', $pathinfo['filename']);
        return true;
    }

    private function get_attributes($resource) {
        $image  = getimagesize($resource);
        if(!$image){
            return null;
        }
        list($width, $height) = $image;
        $mime   = $image['mime'];
        return array(
            'sizes' => array('width' => $width, 'height' => $height),
            'coordinates' => array('x' => 0, 'y' => 0),
            'mime'  => $mime
        );
    }

    private function set_resource($type, $key = null, $value = null) {
        if($type === 'resource'){
            $this->resource[$key] = $value;
        }else{
            $res_sizes = $this->get_resource('resource', 'sizes');
            if(is_null($key) && is_null($value)){
                if(is_null($res_sizes)){
                    throw new GambarException("Error Processing Request", 1);
                    exit;
                }
                $width  = $res_sizes['width'];
                $height = $res_sizes['height'];
                $this->destination['sizes']         = array('width' => $width, 'height' => $height);
                $this->destination['coordinates']   = array('x' => 0, 'y' => 0);
            }else{
                $this->destination[$key] = $value;
                $des_sizes  = $this->get_resource('destination', 'sizes');
                if(!is_null($des_sizes)){
                    $width  = $des_sizes['width'];
                    $height = $des_sizes['height'];
                }else{
                    $width  = $res_sizes['width'];
                    $height = $res_sizes['height'];
                }
            }
            $this->destination['stream'] = imagecreatetruecolor($width, $height);
        }
    }

    private function get_resource($type, $key = null) {
        $returned = null;
        if(!is_null($key)){
            if($type === 'resource'){
                if(isset($this->resource[$key])){
                    $returned = $this->resource[$key];
                }
            }else{
                if(isset($this->destination[$key])){
                    $returned = $this->destination[$key];
                }
            }
        }else{
            if($type === 'resource'){
                $returned = $this->resource;
            }else{
                $returned = $this->destination;
            }
        }
        return $returned;
    }

}

?>
