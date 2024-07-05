<?php

class Debugger{
    static function debugToFile($erro){
        $file = PRJ_PLUGIN_DIR."debug.txt";
        $text =  $erro."\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }
}