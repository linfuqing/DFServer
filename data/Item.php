<?php

class Item
{
	//public $m_uValue;
	public $m_sLabel;
	
	const NAME_SPACE = 'DFItem';
	
	public function __construct(/*$uValue, */$sLabel)
	{
		//$this->m_uValue = $uValue;
		$this->m_sLabel = $sLabel;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Item::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, label FROM items");
			if($Result)
			{
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$Redis->set(Item::NAME_SPACE . $uID, serialize(new Item(
							//(int)$aResult['value'], 
							$aResult['label'])));
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Redis->incr(Item::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Item::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Item::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Item::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):Item
	{
		if(!Item::Init($Redis, $Mysqli))
			return false;
			
		$Item = $Redis->get(Item::NAME_SPACE . $uID);
		return $Item ? unserialize($Item) : false;
	}
}

class UserItem
{
	public $m_uUserID;
	public $m_uItemID;
	public $m_uCount;
	
	const NAME_SPACE = 'DFUserItem';
	const NAME_SPACE_IDS = 'DFUserItemIDs';
	
	public function __construct($uUserID, $uItemID, $uCount)
	{
		$this->m_uUserID = $uUserID;
		$this->m_uItemID = $uItemID;
		$this->m_uCount = $uCount;
	}
	
	public function SaveCount($uID, $Redis, $Mysqli):bool
	{
		$Result = mysqli_query($Mysqli, "UPDATE user_items SET count=$this->m_uCount WHERE id=$uID");
		if($Result)
		{
			$Redis->set(UserItem::NAME_SPACE . $uID, serialize($this));
			
			return true;
		}
		
		trigger_error(mysqli_error($Mysqli));
	
		return false;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(UserItem::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, user_id, item_id, count FROM user_items");
			if($Result)
			{
				$aauUserItemIDs = null;
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$uUserID = (int)$aResult['user_id'];
					$uItemID = (int)$aResult['item_id'];
					$Redis->set(UserItem::NAME_SPACE . $uID, serialize(new UserItem(
							$uUserID, 
							$uItemID, 
							(int)$aResult['count'])));
					
					$aauUserItemIDs[$uUserID][$uItemID] = $uID;
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				if(isset($aauUserItemIDs))
				{
					foreach ($aauUserItemIDs as $uUserID => $auUserItemIDs)
						$Redis->set(UserItem::NAME_SPACE_IDS . $uUserID, serialize($auUserItemIDs));
				}
				
				$Redis->incr(UserItem::NAME_SPACE);
			}
			else
			{
				$Redis->decr(UserItem::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(UserItem::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(UserItem::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli) : UserItem
	{
		if(!UserItem::Init($Redis, $Mysqli))
			return false;
			
		$UserItem = $Redis->get(UserItem::NAME_SPACE . $uID);
		return $UserItem ? unserialize($UserItem) : false;
	}
	
	public static function GetIDs($uUserID, $Redis, $Mysqli)
	{
		if(!UserItem::Init($Redis, $Mysqli))
			return false;
			
		$auUserItemIDs = $Redis->get(UserItem::NAME_SPACE_IDS . $uUserID);
		return $auUserItemIDs ? unserialize($auUserItemIDs) : false;
	}
	
	public static function Apply($uUserID, $uItemID, $uCount, $Redis, $Mysqli):int
	{
		$auUserItemIDs = UserItem::GetIDs($uUserID, $Redis, $Mysqli);
		if(isset($auUserItemIDs[$uItemID]))
		{
			$uUserItemID = $auUserItemIDs[$uItemID];
			$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
			$UserItem->m_uCount += $uCount;
			
			if($UserItem->m_uCount > 0)
				return $UserItem->SaveCount($uUserItemID, $Redis, $Mysqli) ? $uUserItemID : false;
			
			return UserItem::Delete($uUserItemID, $Redis, $Mysqli);
		}
		else 
		{
			$Result = mysqli_query($Mysqli, "INSERT INTO user_items (user_id, item_id, count) VALUES ($uUserID, $uItemID, $uCount)");
			if($Result)
			{
				$uID = mysqli_insert_id($Mysqli);
				$auUserItemIDs[$uItemID] = $uID;
				$Redis->set(UserItem::NAME_SPACE_IDS . $uUserID, serialize($auUserItemIDs));
				
				$Redis->set(UserItem::NAME_SPACE . $uID, serialize(new UserItem(
						$uUserID,
						$uItemID,
						$uCount)));
				
				return $uID;
			}
			
			trigger_error(mysqli_error($Mysqli));
		}
		
		return false;
	}
	
	public static function Delete($uID, $Redis, $Mysqli):bool
	{
		$Result = mysqli_query($Mysqli, "DELETE FROM user_items WHERE id=$uID");
		if($Result)
		{
			$UserItem = UserItem::Get($uID, $Redis, $Mysqli);
			if($UserItem)
			{
				$auUserItemIDs = UserItem::GetIDs($UserItem->m_uUserID, $Redis, $Mysqli);
				
				unset($auUserItemIDs[$UserItem->m_uItemID]);
				
				$Redis->set(UserItem::NAME_SPACE_IDS . $UserItem->m_uUserID, serialize($auUserItemIDs));
				
				$Redis->delete(UserItem::NAME_SPACE . $uID);
			}
			
			return true;
		}
		
		trigger_error(mysqli_error($Mysqli));
		
		return false;
	}
}