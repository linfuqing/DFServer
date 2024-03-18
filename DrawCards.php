<?php

const FUNCTION_DRAWGETEMPLOYEE= 1;

/*if(isset($_POST['function']))
    exit(0);*/

include 'utils/RedisHelper.php';
$Redis = CreateRedis();
    
include 'utils/MysqliHelper.php';
$Mysqli = CreateMysqli();

$nFunction = filter_input(INPUT_POST,'function',FILTER_SANITIZE_NUMBER_INT);

switch ($nFunction)
{
    case  FUNCTION_DRAWGETEMPLOYEE:
        
        $random_index = random_int(1,100);
        
        echo pack('V', $random_index);
        break;
}