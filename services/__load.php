<?php 
$path = __DIR__ ;
$files = scandir($path);
$serviceClass = [];
foreach($files as $file){
    if ($file[0] == '.' || $file[0]=='_' || substr($file, -4) == 'json')
        continue;
    
    $cfile = $path . '/' . $file;
    if(is_dir($cfile)){
        $subFiles = scandir($cfile);
        foreach($subFiles as $subFile){
            if($subFile[0] == '.')
                continue;
                
            if(substr($subFile, -4) == '.php')
                $serviceClass[] = $cfile . '/' . $subFile;
        }
    }else{
        include_once(__DIR__ .'/' . $file);
    }
}

foreach($serviceClass as $fileToInclude){
    include_once($fileToInclude);   
}