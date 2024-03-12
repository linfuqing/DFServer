<?php

class User
{
	//public $m_sName;
	public $m_sChannelUser;
	
	const NAME_SPACE = 'DFUser';
	const NAME_SPACE_ID = 'DFUserID';
	
	public function __construct(/*$sName, */$sChannelUser)
	{
		//$this->m_sName = $sName;
		$this->m_sChannelUser = $sChannelUser;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(User::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, channel_id, channel_user FROM users");
			if($Result)
			{
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$sChannelUser = $aResult['channel_user'];
					$Redis->set(User::NAME_SPACE . $uID, serialize(new User(
							$sChannelUser)));
					
					$Redis->set(User::NAME_SPACE_ID . (int)$aResult['channel_id'] . '_' . $sChannelUser, $uID);
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Redis->incr(User::NAME_SPACE);
			}
			else
			{
				$Redis->decr(User::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(User::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(User::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function GetByChannel($uChannelID, $sChannelUser, $Redis, $Mysqli):int
	{
		if(!User::Init($Redis, $Mysqli))
			return false;
			
		return $Redis->get(User::NAME_SPACE_ID . $uChannelID . '_' . $sChannelUser);
	}
	
	public static function Create($uChannelID, $sChannelUser, $Redis, $Mysqli):int
	{
		if(!User::Init($Redis, $Mysqli))
			return false;
			
		$uID = false;
		$Result = mysqli_query($Mysqli, "INSERT INTO users (channel_id, channel_user, register_time) VALUES ($uChannelID, '$sChannelUser', NOW())");
		if($Result)
		{
			$uID = (int)mysqli_insert_id($Mysqli);
			$Redis->set(User::NAME_SPACE . $uID, serialize(new User(
					$sChannelUser)));
			
			$Redis->set(User::NAME_SPACE_ID . $uChannelID . '_' . $sChannelUser, $uID);
		}
		else
			trigger_error(mysqli_error($Mysqli));
		
		return $uID;
	}
	
	public static function Get($uID, $Redis, $Mysqli):User
	{
		if(!User::Init($Redis, $Mysqli))
			return false;
			
		$User = $Redis->get(User::NAME_SPACE . $uID);
		return $User ? unserialize($User) : false;
	}
}