<?php
class FoodItem
{
	public $m_uID;
	public $m_uFoodItemID;
	public $m_uFoodCraftID;
	public $m_uLevelCraftID;
	public $m_uCalories;
	public $m_uDelicious;
	
	public function __construct(
			$uID,
			$uFoodItemID, 
			$uFoodCraftID, 
			$uLevelCraftID, 
			$uCalories, 
			$uDelicious)
	{
		$this->m_uID = $uID;
		$this->m_uFoodItemID = $uFoodItemID;
		$this->m_uFoodCraftID = $uFoodCraftID;
		$this->m_uLevelCraftID = $uLevelCraftID;
		$this->m_uCalories = $uCalories;
		$this->m_uDelicious = $uDelicious;
	}
}

class Food
{
	//public $m_uCraftID;
	public $m_aItems;
	public $m_uExpectations;
	
	const NAME_SPACE = 'DFFood';
	const NAME_SPACE_ID = 'DFFoodID';
	
	public function __construct($uExpectations)
	{
		//$this->m_uCraftID = $uCraftID;
		$this->m_uExpectations = $uExpectations;
	}
	
	public static function Init($Redis, $Mysqli)
	{
		if(!isset($Redis))
			return false;
			
		$uCount = $Redis->incr(Food::NAME_SPACE);
		if($uCount < 2)
		{
			$Result = mysqli_query($Mysqli, "SELECT id, expectations FROM foods");
			if($Result)
			{
				$aFoods = [];
				
				$aResult = mysqli_fetch_array($Result);
				while($aResult)
				{
					$uID = (int)$aResult['id'];
					//$uCraftID = (int)$aResult['craft_id'];
					$aFoods[$uID] = new Food((int)$aResult['expectations']);
					
					//$Redis->set(Food::NAME_SPACE_ID . $uCraftID, $uID);
					
					$aResult = mysqli_fetch_array($Result);
				}
				
				$Result = mysqli_query($Mysqli, "SELECT id, food_id, food_item_id, level_item_id, level_craft_id, food_craft_id, calories, delicious FROM food_items");
				if($Result)
				{
					$aResult = mysqli_fetch_array($Result);
					while($aResult)
					{
						$uID = (int)$aResult['id'];
						$uFoodID = (int)$aResult['food_id'];
						$uLevelItemID = (int)$aResult['level_item_id'];
						$aFoods[$uFoodID]->m_aItems[$uLevelItemID] = new FoodItem(
								$uID,
								(int)$aResult['food_item_id'],
								(int)$aResult['food_craft_id'], 
								(int)$aResult['level_craft_id'],
								(int)$aResult['calories'],
								(int)$aResult['delicious']);
						
						$Redis->set(Food::NAME_SPACE_ID . $uLevelItemID, $uFoodID);
						
						$aResult = mysqli_fetch_array($Result);
					}
				}
				else
					trigger_error(mysqli_error($Mysqli));
				
				foreach ($aFoods as $uFoodID => $Food)
					$Redis->set(Food::NAME_SPACE . $uFoodID, serialize($Food));
						
				$Redis->incr(Food::NAME_SPACE);
			}
			else
			{
				$Redis->decr(Food::NAME_SPACE);
				
				trigger_error(mysqli_error($Mysqli));
			}
		}
		else
		{
			$uCount = $Redis->decr(Food::NAME_SPACE);
			while($uCount < 2)
				$uCount = $Redis->get(Food::NAME_SPACE);
		}
		
		return true;
	}
	
	public static function Get($uID, $Redis, $Mysqli):Food
	{
		if(!Food::Init($Redis, $Mysqli))
			return false;
			
		$Food = $Redis->get(Food::NAME_SPACE . $uID);
		return $Food ? unserialize($Food) : false;
	}
	
	public static function GetID($uItemID, $Redis, $Mysqli):int
	{
		if(!Food::Init($Redis, $Mysqli))
			return false;
			
		return $Redis->get(Food::NAME_SPACE_ID . $uItemID);
	}
}