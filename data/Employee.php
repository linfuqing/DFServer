<?php

class EmployeeItem
{
	public $m_uLevelItemID;
	public $m_uLevelCraftID;
	public $m_uService;
	public $m_uCharm;
	public $m_uCook;
	public $m_uSkills;
	
	public function EmployeeItem(
			$uLevelItemID,
			$uLevelCraftID,
			$uService,
			$uCharm,
			$uCook,
			$uSkills)
	{
		$this->m_uLevelItemID = $uLevelItemID;
		$this->m_uLevelCraftID = $uLevelCraftID;
		$this->m_uService = $uService;
		$this->m_uCharm = $uCharm;
		$this->m_uCook = $uCook;
		$this->m_uSkills = $uSkills;
	}
}

class Employee
{
	public $m_uItemID;
	public $m_uCraftID;
	public $m_aItems;
	
	const SKILL_TIP = 0x01;
	
	const NAME_SPACE = 'DFEmployee';
	const NAME_SPACE_ID = 'DFEmployeeID';
	
	public function Employee($uItemID, $uCraftID)
	{
		$this->m_uItemID = $uItemID;
		$this->m_uCraftID = $uCraftID;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Employee::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, item_id, craft_id FROM employees");
			if($Result)
			{
				$aEmployees = [];
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$aEmployees[(int)$aResult['id']] = new Employee(
							(int)$aResult['item_id'],
							(int)$aResult['craft_id']);
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Result = mysqli_query($Mysqli, "SELECT id, employee_id, level_item_id, level_craft_id, service, charm, cook, skills FROM employee_items");
				if($Result)
				{
					$aResult = mysqli_fetch_array($Result);
					while($aResult)
					{
						$uID = (int)$aResult['id'];
						$uEmployeeID = (int)$aResult['employee_id'];
						$uItemID = (int)$aResult['level_item_id'];
						$aEmployees[$uEmployeeID]->m_aItems[$uItemID] = new EmployeeItem(
								$uItemID,
								(int)$aResult['level_craft_id'],
								(int)$aResult['service'],
								(int)$aResult['charm'],
								(int)$aResult['cook'],
								(int)$aResult['skills']);
						
						$Redis->set(Employee::NAME_SPACE_ID . $uItemID, $uEmployeeID);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
				
				foreach ($aEmployees as $uEmployeeID => $Employee)
					$Redis->set(Employee::NAME_SPACE . $uEmployeeID, serialize($Employee));
				
				$Redis->incr(Employee::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Employee::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Employee::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Employee::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):Employee
	{
		if(!Employee::Init($Redis, $Mysqli))
			return false;
			
		$Employee = $Redis->get(Employee::NAME_SPACE . $uID);
		return $Employee ? unserialize($Employee) : false;
	}
	
	public static function GetID($uItemID, $Redis, $Mysqli):int
	{
		if(!Employee::Init($Redis, $Mysqli))
			return false;
			
		return $Redis->get(Employee::NAME_SPACE_ID . $uItemID);
	}
}

class UserEmployee
{
	public $m_uUserItemID;
	public $m_uUserUnitID;
	public $m_uPosition;
	//public $m_uExp;
	
	const NAME_SPACE = 'DFEmployeeUnit';
	const NAME_SPACE_IDS = 'DFUserEmployeeIDs';
	
	public function UserEmployee($uUserItemID, $uUserUnitID, $uPosition/*, $uExp*/)
	{
		$this->m_uUserItemID = $uUserItemID;
		$this->m_uUserUnitID = $uUserUnitID;
		$this->m_uPosition = $uPosition;
		//$this->m_uExp = $uExp;
	}
	
	public function SavePosition($uID, $uPosition, $Redis, $Mysqli):bool
	{
		$Result = mysqli_query($Mysqli, "UPDATE user_employees SET position=$uPosition WHERE id=$uID");
		if($Result)
		{
			$this->m_uPosition = $uPosition;
			
			$Redis->set(UserEmployee::NAME_SPACE . $uID, serialize($this));
			
			return true;
		}
		
		trigger_error(mysqli_error($Mysqli));
		
		return false;
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
		
		$uCount = $Redis->incr(UserEmployee::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT user_employees.*, user_items.user_id, user_items.item_id FROM user_employees LEFT JOIN user_items ON user_employees.user_item_id=user_items.id");
			if($Result)
			{
				$aauUserEmployeeIDs = null;
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$Redis->set(UserEmployee::NAME_SPACE . $uID, serialize(new UserEmployee(
							(int)$aResult['user_item_id'],
							(int)$aResult['user_unit_id'],
							(int)$aResult['position'])));
					
					$aauUserEmployeeIDs[(int)$aResult['user_id']][(int)$aResult['item_id']] = $uID;
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				if(isset($aauUserEmployeeIDs))
				{
					foreach ($aauUserEmployeeIDs as $uUserID => $auUserEmployeeIDs)
						$Redis->set(UserEmployee::NAME_SPACE_IDS . $uUserID, serialize($auUserEmployeeIDs));
				}
				
				$Redis->incr(UserEmployee::NAME_SPACE);
			}
			else
			{
				$Redis->decr(UserEmployee::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(UserEmployee::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(UserEmployee::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Create(
			$uItemID, 
			$uUserID, 
			$uUserItemID,
			$uUserUnitID, 
			$uPosition, 
			$Redis, 
			$Mysqli):int
	{
		if(!UserEmployee::Init($Redis, $Mysqli))
			return false;
			
		$uID = false;
		$Result = mysqli_query($Mysqli, "INSERT INTO user_employees (user_item_id, user_unit_id, position) VALUES ($uUserID, $uUserItemID, $uUserUnitID, $uPosition)");
		if($Result)
		{
			$uID = (int)mysqli_insert_id($Mysqli);
			
			$Redis->set(UserEmployee::NAME_SPACE . $uID, serialize(new UserEmployee(
					$uUserItemID,
					$uUserUnitID,
					$uPosition)));
			
			$sKey = UserEmployee::NAME_SPACE_IDS . $uUserID;
			
			$auUserEmployeeIDs = $Redis->get($sKey);
			if($auUserEmployeeIDs)
				$auUserEmployeeIDs = unserialize($auUserEmployeeIDs);
			else
				$auUserEmployeeIDs = [];
				
			$auUserEmployeeIDs[$uItemID] = $uID;
					
			$Redis->set($sKey, serialize($auUserEmployeeIDs));
		}
		else
			trigger_error(mysqli_error($Mysqli));
			
		return $uID;
	}
	
	public static function Get($uID, $Redis, $Mysqli):UserEmployee
	{
		if(!UserEmployee::Init($Redis, $Mysqli))
			return false;
			
		$UserEmployee = $Redis->get(UserEmployee::NAME_SPACE . $uID);
		return $UserEmployee ? unserialize($UserEmployee) : false;
	}
	
	public static function GetIDs($uUserID, $Redis, $Mysqli)
	{
		if(!UserEmployee::Init($Redis, $Mysqli))
			return false;
			
		$auUserEmployeeIDs = $Redis->get(UserEmployee::NAME_SPACE_IDS . $uUserID);
		return $auUserEmployeeIDs ? unserialize($auUserEmployeeIDs) : false;
	}
}