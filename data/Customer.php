<?php

class CustomerItem
{
	public $m_uID;
	public $m_uLevelCraftID;
	public $m_uMaxExp;
	public $m_uMaxExpToUnit;
	public $m_uMaxExpPerTime;
	public $m_uMaxCalories;
	public $m_uMaxExpectations;
	
	public function __construct(
			$uID, 
			$uLevelCraftID, 
			$uMaxExp, 
			$uMaxExpToUnit, 
			$uMaxExpPerTime, 
			$uMaxCalories, 
			$uMaxExpectations)
	{
		$this->m_uID = $uID;
		$this->m_uLevelCraftID = $uLevelCraftID;
		$this->m_uMaxExp = $uMaxExp;
		$this->m_uMaxExpToUnit = $uMaxExpToUnit;
		$this->m_uMaxExpPerTime = $uMaxExpPerTime;
		$this->m_uMaxCalories = $uMaxCalories;
		$this->m_uMaxExpectations = $uMaxExpectations;
	}
}

class CustomerFood
{
	public $m_uID;
	public $m_uExpectations;
	public $m_fChanceToMeet;
	
	public function __construct($uID, $uExpectations, $fChanceToMeet)
	{
		$this->m_uID = $uID;
		$this->m_uExpectations = $uExpectations;
		$this->m_fChanceToMeet = $fChanceToMeet;
	}
}

class Customer
{
	/*public $m_uMaxExp;
	public $m_uMaxExpToUnit;
	public $m_uMaxExpPerTime;
	public $m_uMaxCalories;
	public $m_uMaxExpectations;*/
	PUBLIC $m_aItems;
	public $m_aFoods;
	
	const NAME_SPACE = 'DFCustomer';
	const NAME_SPACE_ID = 'DFCustomerID';
	
	/*public function Customer(
			$uMaxExp,
			$uMaxExpToUnit, 
			$uMaxExpPerTime, 
			$uMaxCalories,
			$uMaxExpectations)
	{
		$this->m_uMaxExp = $uMaxExp;
		$this->m_uMaxExpToUnit = $uMaxExpToUnit;
		$this->m_uMaxExpPerTime = $uMaxExpPerTime;
		$this->m_uMaxCalories = $uMaxCalories;
		$this->m_uMaxExpectations = $uMaxExpectations;
	}*/
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Customer::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id FROM customers");
			if($Result)
			{
				$aCustomers = [];
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$aCustomers[$uID] = new Customer();
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Result = mysqli_query($Mysqli, "SELECT id, customer_id, level_item_id, level_craft_id, max_exp, max_exp_to_unit, max_exp_per_time, max_calories, max_expectations FROM customer_items");
				$aResult = mysqli_fetch_array($Result);
				if($Result)
				{
					while($aResult)
					{
						$uCustomerID = (int)$aResult['customer_id'];
						$uLevelItemID = (int)$aResult['level_item_id'];
						$aCustomers[$uCustomerID]->m_aItems[$uLevelItemID] = new CustomerItem(
								(int)$aResult['id'],
								(int)$aResult['level_craft_id'],
								(int)$aResult['max_exp'],
								(int)$aResult['max_exp_to_unit'],
								(int)$aResult['max_exp_per_time'],
								(int)$aResult['max_calories'],
								(int)$aResult['max_expectations']);
						
						$Redis->set(Customer::NAME_SPACE_ID . $uLevelItemID, $uCustomerID);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
				
				$Result = mysqli_query($Mysqli, "SELECT id, customer_id, food_id, expectations, chance_to_meet FROM customer_foods");
				if($Result)
				{
					$aResult = mysqli_fetch_array($Result);
					while($aResult)
					{
						$uCustomerID = (int)$aResult['customer_id'];
						$uFoodID = (int)$aResult['food_id'];
						$aCustomers[$uCustomerID]->m_aFoods[$uFoodID] = new CustomerFood(
								(int)(int)$aResult['id'],
								(int)$aResult['expectations'], 
								(float)$aResult['chance_to_meet']);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
					
				foreach ($aCustomers as $uID => $Customer)
					$Redis->set(Customer::NAME_SPACE . $uID, serialize($Customer));
				
				$Redis->incr(Customer::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Customer::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Customer::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Customer::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):Customer
	{
		if(!Customer::Init($Redis, $Mysqli))
			return false;
			
		$Customer = $Redis->get(Customer::NAME_SPACE . $uID);
		return $Customer ? unserialize($Customer) : false;
	}
	
	public static function GetID($uItemID, $Redis, $Mysqli):int
	{
		if(!Customer::Init($Redis, $Mysqli))
			return false;
			
		return $Redis->get(Customer::NAME_SPACE_ID . $uItemID);
	}
}

class UserCustomer
{
	public $m_uUserItemID;
	public $m_uExp;
	
	const NAME_SPACE = 'DFCustomerUnit';
	const NAME_SPACE_IDS = 'DFUserCustomerIDs';
	
	public function __construct($uUserItemID, $uExp)
	{
		$this->m_uUserItemID = $uUserItemID;
		$this->m_uExp = $uExp;
	}
	
	public function SaveExp($uID, $Redis, $Mysqli)
	{
		$Result = mysqli_query($Mysqli, "UPDATE user_customers SET exp=$this->m_uExp WHERE id=$uID");
		if($Result)
			$Redis->set(UserCustomer::NAME_SPACE . $uID, serialize($this));
		else
			trigger_error(mysqli_error($Mysqli));
	}
	
	public static function Init($Redis, $Mysqli):bool
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(UserCustomer::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT user_customers.*, user_items.user_id, user_items.item_id FROM user_customers LEFT JOIN user_items ON user_customers.user_item_id=user_items.id");
			if($Result)
			{
				$aauUserCustomerIDs = null;
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					$Redis->set(UserCustomer::NAME_SPACE . $uID, serialize(new UserCustomer(
							(int)$aResult['user_item_id'],
							(int)$aResult['exp'])));
					
					$aauUserCustomerIDs[(int)$aResult['user_id']][(int)$aResult['item_id']] = $uID;
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				if(isset($aauUserCustomerIDs))
				{
					foreach ($aauUserCustomerIDs as $uUserID => $auUserCustomerIDs)
						$Redis->set(UserCustomer::NAME_SPACE_IDS . $uUserID, serialize($auUserCustomerIDs));
				}
				
				$Redis->incr(UserCustomer::NAME_SPACE);
			}
			else
			{
				$Redis->decr(UserCustomer::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(UserCustomer::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(UserCustomer::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):UserCustomer
	{
		if(!UserCustomer::Init($Redis, $Mysqli))
			return false;
			
		$UserCustomer = $Redis->get(UserCustomer::NAME_SPACE . $uID);
		return $UserCustomer ? unserialize($UserCustomer) : false;
	}
	
	public static function GetIDs($uUserID, $Redis, $Mysqli)
	{
		if(!UserCustomer::Init($Redis, $Mysqli))
			return false;
			
		$auUserCustomerIDs = $Redis->get(UserCustomer::NAME_SPACE_IDS . $uUserID);
		return $auUserCustomerIDs ? unserialize($auUserCustomerIDs) : false;
	}
	
	public static function Create($uItemID, $uUserID, $uUserItemID, $Redis, $Mysqli)
	{
		if(!UserCustomer::Init($Redis, $Mysqli))
			return false;
		
			$Result = mysqli_query($Mysqli, "INSERT INTO user_customers (user_item_id, exp) VALUES($uUserItemID, 0)");
		if($Result)
		{
			$uID = (int)mysqli_insert_id($Mysqli);
			
			$Redis->set(UserCustomer::NAME_SPACE . $uID, serialize(new UserCustomer(
					$uUserItemID,
					0)));
			
			$auUserCustomerIDs = UserCustomer::GetIDs($uUserID, $Redis, $Mysqli);
			if(!$auUserCustomerIDs)
				$auUserCustomerIDs = [];
			
			$auUserCustomerIDs[$uItemID] = $uID;
			
			$Redis->set(UserCustomer::NAME_SPACE_IDS . $uUserID, serialize($auUserCustomerIDs));
			
			return $uID;
		}
		
		trigger_error(mysqli_error($Mysqli));
		
		return false;
	}
}