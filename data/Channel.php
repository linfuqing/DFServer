<?php
class Channel
{
	public $m_uID;
	
	const NAME_SPACE = 'DFChannel';
	
	public function Channel($uID)
	{
		$this->m_uID = $uID;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Channel::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, name FROM channels");
			if($Result)
			{
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$Redis->set(Channel::NAME_SPACE . $aResult['name'], serialize(new Channel((int)$aResult['id'])));
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Redis->incr(Channel::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Channel::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Channel::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Channel::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($sName, $Redis, $Mysqli):Channel
	{
		if(!Channel::Init($Redis, $Mysqli))
			return false;
			
		$Channel = $Redis->get(Channel::NAME_SPACE . $sName);
		return $Channel ? unserialize($Channel) : false;
	}
}
