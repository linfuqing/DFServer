<?php

include 'utils/RedisHelper.php';
$Redis = CreateRedis();

include 'utils/MysqliHelper.php';
$Mysqli = CreateMysqli();

$Redis->flushAll();

echo "Clear Success!";

/*include_once 'data/Channel.php';
$Channel = Channel::Get('None', $Redis, $Mysqli);
echo var_export($Channel, true);

echo "    ";

include_once 'data/Food.php';
$Food = Food::GetID(100, $Redis, $Mysqli);
echo var_export($Food, true);*/