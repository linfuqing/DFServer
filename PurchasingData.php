<?php

const FUNCTION_DRAW = 1;

if(!isset($_POST['function']))
	exit(0);
	
include 'utils/RedisHelper.php';
$Redis = CreateRedis();

include 'utils/MysqliHelper.php';
$Mysqli = CreateMysqli();

$nFunction = filter_input(INPUT_POST, 'function', FILTER_SANITIZE_NUMBER_INT);
switch ($nFunction)
{
	case FUNCTION_DRAW:
		$uUserID = (int)filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
		$uCraftID = (int)filter_input(INPUT_POST, 'craft_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserID != 0 && $uCraftID != 0)
		{
			$Craft = Craft::Get($uCraftID, $Redis, $Mysqli);
			if($Craft)
			{
				include_once 'data/Craft.php';
				
				$uCount = (int)filter_input(INPUT_POST, 'count', FILTER_SANITIZE_NUMBER_INT);
				$uCount = max($uCount, 1);
				
				$aResultUserItems = [];
				for($i = 0; $i < $uCount; ++$i)
				{
					$aUserItems = $Craft->Apply($uUserID, $Redis, $Mysqli);
					if($aUserItems)
					{
						$aResultUserItems = array_merge($aResultUserItems, $aUserItems);
					}
				}
			}
		}
		break;
}