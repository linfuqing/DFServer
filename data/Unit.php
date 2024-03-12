<?php
class UnitCustomer
{
	public $m_uID;
	public $m_uExpectations;
	public $m_fChanceToMeet;
	public $m_aFoods;
	
	public function __construct(
			$uID,
			$uExpectations, 
			$fChanceToMeet)
	{
		$this->m_uID = $uID;
		$this->m_uExpectations = $uExpectations;
		$this->m_fChanceToMeet = $fChanceToMeet;
	}
}

class Unit
{
	public $m_uLevelItemID;
	public $m_uLevelCraftID;
	public $m_uMaxExp;
	public $m_uMaxTickTime;
	public $m_uTicksPerTime;
	public $m_uMinCustomerCount;
	public $m_uMaxCustomerCount;
	public $m_uComfort;
	public $m_fTip;
	public $m_aaFoods;
	public $m_aCustomers;
	
	const NAME_SPACE = 'DFUnit';
	const NAME_SPACE_ID = 'DFUnitID';
	
	public function __construct(
			$uLevelItemID, 
			$uLevelCraftID, 
			$uMaxExp,
			$uMaxTickTime,
			$uTicksPerTime, 
			$uMinCustomerCount,
			$uMaxCustomerCount,
			$uComfort, 
			$fTip)
	{
		$this->m_uLevelItemID = $uLevelItemID;
		$this->m_uLevelCraftID = $uLevelCraftID;
		$this->m_uMaxExp = $uMaxExp;
		$this->m_uMaxTickTime = $uMaxTickTime;
		$this->m_uTicksPerTime = $uTicksPerTime;
		$this->m_uMinCustomerCount = $uMinCustomerCount;
		$this->m_uMaxCustomerCount = $uMaxCustomerCount;
		$this->m_uComfort = $uComfort;
		$this->m_fTip = $fTip;
	}
	
	public static function Init($Redis, $Mysqli)
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Unit::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, level_item_id, level_craft_id, max_exp, max_tick_time, ticks_per_time, min_customer_count, max_customer_count, comfort, tip FROM units");
			if($Result)
			{
				$aUnits = [];
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$uItemID= (int)$aResult['level_item_id'];
					$aUnits[$uID] = new Unit(
							(int)$uItemID,
							(int)$aResult['level_craft_id'],
							(int)$aResult['max_exp'],
							(int)$aResult['max_tick_time'],
							(int)$aResult['ticks_per_time'],
							(int)$aResult['min_customer_count'],
							(int)$aResult['max_customer_count'],
							(int)$aResult['comfort'],
							(float)$aResult['tip']);
					
					$Redis->set(Unit::NAME_SPACE_ID . $uItemID, $uID);
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Result = mysqli_query($Mysqli, "SELECT id, unit_id, customer_id, expectations, chance_to_meet FROM unit_customers");
				if($Result)
				{
					$aResult = mysqli_fetch_array($Result);
					while($aResult)
					{
						$uID = (int)$aResult['id'];
						$uUnitID = (int)$aResult['unit_id'];
						$uCustomerID = (int)$aResult['customer_id'];
						$aUnits[$uUnitID]->m_aCustomers[$uCustomerID] = new UnitCustomer(
								$uID, 
								(int)$aResult['expectations'], 
								(float)$aResult['chance_to_meet']);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
				
				foreach ($aUnits as $uID => $Unit)
					$Redis->set(Unit::NAME_SPACE . $uID, serialize($Unit));
					
				$Redis->incr(Unit::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Unit::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Unit::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Unit::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):Unit
	{
		if(!Unit::Init($Redis, $Mysqli))
			return false;
			
		$Unit = $Redis->get(Unit::NAME_SPACE . $uID);
		return $Unit ? unserialize($Unit) : false;
	}
	
	
	public static function GetID($uItemID, $Redis, $Mysqli):int
	{
		if(!Unit::Init($Redis, $Mysqli))
			return false;
			
		return $Redis->get(Unit::NAME_SPACE_ID . $uItemID);
	}
}

class UserUnit
{
	public $m_uUserID;
	public $m_uUnitID;
	public $m_uComfort;
	public $m_uExp;
	public $m_uTickTime;
	
	const NAME_SPACE = 'DFUserUnit';
	const NAME_SPACE_IDS = 'DFUserUnitIDs';
	
	public function __construct($uUserID, $uUnitID, $uComfort, $uExp, $uTickTime)
	{
		$this->m_uUserID = $uUserID;
		$this->m_uUnitID = $uUnitID;
		$this->m_uComfort = $uComfort;
		$this->m_uExp = $uExp;
		$this->m_uTickTime = $uTickTime;
	}
	
	public function SaveExp($uID, $Redis, $Mysqli)
	{
		$Result = mysqli_query($Mysqli, "UPDATE user_units SET exp=$this->m_uExp, tick_time=$this->m_uTickTime WHERE id=$uID");
		if($Result)
		{
			$Redis->set(UserUnit::NAME_SPACE . $uID, serialize($this));
			
			return true;
		}
		
		trigger_error(mysqli_error($Mysqli));
		
		return false;
	}
	
	
	public function Save($uID, $Redis, $Mysqli)
	{
		$Result = mysqli_query($Mysqli, "UPDATE user_units SET unit_id=$this->m_uUnitID, SET exp=$this->m_uExp, SET tick_time=$this->m_uTickTime WHERE id=$uID");
		if($Result)
		{
			$Redis->set(UserUnit::NAME_SPACE . $uID, serialize($this));
			
			return true;
		}
		
		trigger_error(mysqli_error($Mysqli));
		
		return false;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(UserUnit::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, user_id, unit_id, exp, tick_time FROM user_units");
			if($Result)
			{
				$aauUserUnitIDs = null;
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$uUserID = (int)$aResult['user_id'];
					$uUnitID = (int)$aResult['unit_id'];
					$Unit = Unit::Get($uUnitID, $Redis, $Mysqli);
					$Redis->set(UserUnit::NAME_SPACE . $uID, serialize(new UserUnit(
							$uUserID,
							$uUnitID, 
							$Unit->m_uComfort, 
							(int)$aResult['exp'], 
							(int)$aResult['tick_time'])));
					
					$aauUserUnitIDs[$uUserID][$uUnitID] = $uID;
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				if(isset($aauUserUnitIDs))
				{
					foreach ($aauUserUnitIDs as $uUserID => $auUserUnitIDs)
						$Redis->set(UserUnit::NAME_SPACE_IDS . $uUserID, serialize($auUserUnitIDs));
				}
				
				$Redis->incr(UserUnit::NAME_SPACE);
			}
			else
			{
				$Redis->decr(UserUnit::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(UserUnit::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(UserUnit::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Create($uUserID, $uUnitID, $Redis, $Mysqli):int
	{
		if(!UserUnit::Init($Redis, $Mysqli))
			return false;
		
		$uID = false;
		$Result = mysqli_query($Mysqli, "INSERT INTO user_units (user_id, unit_id, exp, tick_time) VALUES ($uUserID, $uUnitID, 0, 0)");
		if($Result)
		{
			$uID = (int)mysqli_insert_id($Mysqli);
			
			$Unit = Unit::Get($uUnitID, $Redis, $Mysqli);
			
			$Redis->set(UserUnit::NAME_SPACE . $uID, serialize(new UserUnit(
					$uUserID,
					$uUnitID,
					$Unit->m_uComfort,
					0,
					0)));
			
			$sKey = UserUnit::NAME_SPACE_IDS . $uUserID;
			
			$auUserUnitIDs = $Redis->get($sKey);
			if($auUserUnitIDs)
				$auUserUnitIDs = unserialize($auUserUnitIDs);
			else
				$auUserUnitIDs = [];
			
			$auUserUnitIDs[$uUnitID] = $uID;
			
			$Redis->set($sKey, serialize($auUserUnitIDs));
		}
		else
			trigger_error(mysqli_error($Mysqli));
			
		return $uID;
	}
	
	public static function Get($uID, $Redis, $Mysqli):UserUnit
	{
		if(!UserUnit::Init($Redis, $Mysqli))
			return false;
			
		$UserUnit = $Redis->get(UserUnit::NAME_SPACE . $uID);
		return $UserUnit ? unserialize($UserUnit) : false;
	}
	
	public static function GetIDs($uUserID, $Redis, $Mysqli):array
	{
		if(!UserUnit::Init($Redis, $Mysqli))
			return false;
			
		$auUserUnitIDs = $Redis->get(UserUnit::NAME_SPACE_IDS . $uUserID);
		return $auUserUnitIDs ? unserialize($auUserUnitIDs) : false;
	}
}