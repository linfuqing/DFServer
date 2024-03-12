<?php

class CraftDestination
{
	public $m_uChanceItemID;
	public $m_uOutputItemID;
	public $m_uOutputItemCount;
	public $m_uGroupMask;
	public $m_fChance;
	
	public function __construct(
			$uChanceItemID, 
			$uOutputItemID, 
			$uOutputItemCount, 
			$uGroupMask, 
			$fChance)
	{
		$this->m_uChanceItemID = $uChanceItemID;
		$this->m_uOutputItemID = $uOutputItemID;
		$this->m_uOutputItemCount = $uOutputItemCount;
		$this->m_uGroupMask = $uGroupMask;
		$this->m_fChance = $fChance;
	}
}

class CraftSource
{
	public $m_uInputItemID;
	public $m_uInputItemCount;
	public $m_sLabel;
	
	public function __construct($uInputItemID, $uInputItemCount, $sLabel)
	{
		$this->m_uInputItemID = $uInputItemID;
		$this->m_uInputItemCount = $uInputItemCount;
		$this->m_sLabel = $sLabel;
	}
}

class Craft
{
	public $m_aaDestinations;
	public $m_aSouces;
	
	const NAME_SPACE = 'DFCraft';
	
	public function Input($uUserID, $Redis, $Mysqli):bool
	{
		$bResult = true;
		if(isset($this->m_aSouces))
		{
			include_once 'Item.php';
			
			$auUserItemIDs = UserItem::GetIDs($uUserID, $Redis, $Mysqli);
			if(!$auUserItemIDs)
				return false;
			
			foreach ($this->m_aSouces as $Source)
			{
				$uItemCount = 0;
				if($Source->m_uInputItemID != 0)
				{
					if(isset($auUserItemIDs[$Source->m_uInputItemID]))
					{
						$uUserItemID = $auUserItemIDs[$Source->m_uInputItemID];
						$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
						$uItemCount += $UserItem->m_uCount;
					}
				}
				
				if($uItemCount < $Source->m_uInputItemCount && strlen($Source->m_sLabel) > 0)
				{
					foreach ($auUserItemIDs as $uItemID => $uUserItemID)
					{
						$Item = Item::Get($uItemID, $Redis, $Mysqli);
						if($Item->m_sLabel != $Source->m_sLabel)
							continue;
							
						$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
						$uItemCount += $UserItem->m_uCount;
						
						if($uItemCount >= $Source->m_uInputItemCount)
							break;
					}
				}
				
				if($uItemCount < $Source->m_uInputItemCount)
				{
					$bResult = false;
					
					break;
				}
			}
			
			if($bResult)
			{
				foreach ($this->m_aSouces as $Source)
				{
					$uItemCount = $Source->m_uInputItemCount;
					if($Source->m_uInputItemID != 0)
					{
						if(isset($auUserItemIDs[$Source->m_uInputItemID]))
						{
							$uUserItemID = $auUserItemIDs[$Source->m_uInputItemID];
							$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
							if($UserItem->m_uCount > $uItemCount)
							{
								$UserItem->m_uCount -= $uItemCount;
								$UserItem->SaveCount($uUserItemID, $Redis, $Mysqli);
								
								$uItemCount = 0;
							}
							else
							{
								$uItemCount -= $UserItem->m_uCount;
								
								UserItem::Delete($uUserItemID, $Redis, $Mysqli);
							}
						}
					}
					
					if($uItemCount > 0 && strlen($Source->m_sLabel) > 0)
					{
						foreach ($auUserItemIDs as $uItemID => $uUserItemID)
						{
							$Item = Item::Get($uItemID, $Redis, $Mysqli);
							if($Item->m_sLabel != $Source->m_sLabel)
								continue;
								
								$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
								if($UserItem->m_uCount > $uItemCount)
								{
									$UserItem->m_uCount -= $uItemCount;
									$UserItem->SaveCount($uUserItemID, $Redis, $Mysqli);
									
									$uItemCount = 0;
								}
								else
								{
									$uItemCount -= $UserItem->m_uCount;
									
									UserItem::Delete($uUserItemID, $Redis, $Mysqli);
								}
								
								if($uItemCount < 1)
									break;
						}
					}
				}
			}
		}
		
		return $bResult;
	}
	
	
	public function Output($auUserItemCounts = null):array
	{
		if(isset($this->m_aDestinations))
		{
			$auItemCounts = [];
			
			$uGroupMask = 0;
			
			foreach ($this->m_aaDestinations as $uGroupValue => $aDestinations)
			{
				if(($uGroupValue & $uGroupMask) != 0)
					continue;
				
				$fChance = 0.0;
			
				$fRandom = mt_rand() * 1.0 / mt_getrandmax();
				foreach ($aDestinations as $Destination)
				{
					$uChanceItemCount = 0;
					if($Destination->m_uChanceItemID == 0)
						$uChanceItemCount = 1;
					else if(isset($auUserItemCounts) && isset($auUserItemCounts[$Destination->m_uChanceItemID]))
						$uChanceItemCount = $auUserItemCounts[$Destination->m_uChanceItemID];
					
					$fChance += max(1.0, $uChanceItemCount * $Destination->m_fChance);

					if($fChance > $fRandom)
					{
						$uGroupMask |= $Destination->m_uGroupMask;
						
						if($Destination->m_uOutputItemCount != 0)
						{
							if(isset($auItemCounts[$Destination->m_uOutputItemID]))
								$auItemCounts[$Destination->m_uOutputItemID] += $Destination->m_uOutputItemCount;
							else
								$auItemCounts[$Destination->m_uOutputItemID] = $Destination->m_uOutputItemCount;
						}
						
						if($uChanceItemCount != 0)
						{
							$uChanceItemCount = -$uChanceItemCount;
							
							if(isset($auItemCounts[$Destination->m_uChanceItemID]))
								$auItemCounts[$Destination->m_uChanceItemID] += $uChanceItemCount;
							else
								$auItemCounts[$Destination->m_uChanceItemID] = $uChanceItemCount;
						}
						
						break;
					}
				}
			}
			
			return $auItemCounts;
		}
		
		return false;
	}
	
	public function OutputToSave($uUserID, $Redis, $Mysqli):array
	{
		include_once 'Item.php';
		
		$auUserItemIDs = UserItem::GetIDs($uUserID, $Redis, $Mysqli);
		$auUserItemCounts = [];
		foreach ($auUserItemIDs as $uItemID => $uUserItemID)
		{
			$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
			
			$auUserItemCounts[$uItemID] = $UserItem->m_uCount;
		}
		
		$aUserItems = [];
		
		$auItemCounts = $this->Output($auUserItemCounts);
		foreach ($auItemCounts as $uItemID => $uItemCount)
		{
			$uUserItemID = UserItem::Apply($uUserID, $uItemID, $uItemCount, $Redis, $Mysqli);
			if($uUserItemID)
				$aUserItems[$uUserItemID] = new UserItem($uUserID, $uItemID, $uItemCount);
		}
		
		return $aUserItems;
	}
	
	public static function Apply($uUserID, $Redis, $Mysqli)
	{
		if($this->Input($uUserID, $Redis, $Mysqli))
			return false;
		
		return $this->OutputToSave($uUserID, $Redis, $Mysqli);
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Craft::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id FROM crafts");
			if($Result)
			{
				$aCrafts = [];
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$aCrafts[(int)$aResult['id']] = new Craft();
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Result = mysqli_query($Mysqli, "SELECT id, craft_id, chance_item_id, output_item_id, output_item_count, group_value, group_mask, chance FROM craft_destinations");
				if($Result)
				{
					$aResult = mysqli_fetch_array($Result);
					while($aResult)
					{
						$uCraftID = (int)$aResult['craft_id'];
						
						$Craft = $aCrafts[$uCraftID];
						$Craft->m_aaDestinations[(int)$aResult['id']][(int)$aResult['group_value']] = new CraftDestination(
								(int)$aResult['chance_item_id'], 
								(int)$aResult['output_item_id'], 
								(int)$aResult['output_item_count'],
								(int)$aResult['group_mask'], 
								(float)$aResult['chance']);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
				
				$Result = mysqli_query($Mysqli, "SELECT id, craft_id, input_item_id, input_item_count, label FROM craft_sources");
				if($Result)
				{
					$aResult = mysqli_fetch_array($Result);
					while($aResult)
					{
						$Craft = $aCrafts[(int)$aResult['craft_id']];
						$Craft->m_aSouces[(int)$aResult['id']] = new CraftSource(
								(int)$aResult['input_item_id'],
								(int)$aResult['input_item_count'],
								$aResult['label']);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
				
				foreach ($aCrafts as $uID => $Craft)
					$Redis->set(Craft::NAME_SPACE . $uID, serialize($Craft));
				
				$Redis->incr(Craft::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Craft::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Craft::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Craft::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):Craft
	{
		if(!Craft::Init($Redis, $Mysqli))
			return false;
			
		$Craft = $Redis->get(Craft::NAME_SPACE . $uID);
		return $Craft ? unserialize($Craft) : false;
	}
}