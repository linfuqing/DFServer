<?php
const NAME_SPACE_GAME = 'DFGame';

const FUNCTION_USER = 0;
const FUNCTION_FOOD_QUERY = 1;
const FUNCTION_FOOD_UPGRADE = 2;
const FUNCTION_UNIT_QUERY = 3;
const FUNCTION_UNIT_UPGRADE = 4;
const FUNCTION_EMPLOYEE_QUERY = 5;
const FUNCTION_EMPLOYEE_POSITION = 6;
const FUNCTION_EMPLOYEE_UPGRADE = 7;
//const  FUNCTION_UPGRADE_EMPLOYEES = 7;
//const  FUNCTION_EMPLOYEE_DRAW = 8;
const FUNCTION_ITEM_QUERY = 9;
const FUNCTION_CRAFT_QUERY = 10;
const FUNCTION_QUEST_QUERY = 11;
const FUNCTION_QUEST_DRAW = 12; 
const FUNCTION_CUSTOMERS_QUERY = 13;
const FUNCTION_GAME_START = 14;
const FUNCTION_GAME_END = 15;

/*class CommandService
{
	public $m_uUserFoodID;
	public $m_uUserCustomerID;
	public $m_uUserEmployeeID;
	
	public function CommandService($uUserFoodID, $uUserCustomerID, $uUserEmployeeID)
	{
		$this->m_uUserFoodID = $uUserFoodID;
		$this->m_uUserCustomerID = $uUserCustomerID;
		$this->m_uUserEmployeeID = $uUserEmployeeID;
	}
}*/

class CommandFood
{
	public $m_uItemID;
	public $m_uCount;
	
	public function __construct($uItemID, $uCount)
	{
		$this->m_uItemID = $uItemID;
		$this->m_uCount = $uCount;
	}
}

class Command
{
	public $m_aFoods;
	public $m_auCustomerIDs;
}

if(!isset($_POST['function']))
	exit(0);
	
include 'utils/RedisHelper.php';
$Redis = CreateRedis();

include 'utils/MysqliHelper.php';
$Mysqli = CreateMysqli();
	
$nFunction = filter_input(INPUT_POST, 'function', FILTER_SANITIZE_NUMBER_INT);
switch ($nFunction)
{
	case FUNCTION_USER:
		$sChannel = filter_input(INPUT_POST, 'channel');
		
		$uUserID = false;
		
		include_once 'data/Channel.php';
		$Channel = Channel::Get($sChannel, $Redis, $Mysqli);
		if($Channel)
		{
			$sChannelUser= filter_input(INPUT_POST, 'channel_user');
			include_once 'data/User.php';
			$uUserID = User::GetByChannel($Channel->m_uID, $sChannelUser, $Redis, $Mysqli);
			if(!$uUserID)
			{
				$uUserID = User::Create($Channel->m_uID, $sChannelUser, $Redis, $Mysqli);
				if($uUserID)
				{
					include_once 'data/Item.php';
					
					include_once 'data/Unit.php';
					$Unit = Unit::Get(1, $Redis, $Mysqli);
					
					UserItem::Create($uUserID, $Unit->m_uLevelItemID, 1, $Redis, $Mysqli);
					
					$uUserUnitID = UserUnit::Create($uUserID, 1, $Redis, $Mysqli);
					
					$UserUnit = UserUnit::Get($uUserUnitID, $Redis, $Mysqli);
					
					include_once 'data/Employee.php';
					$LeadEmployee = Employee::Get(1, $Redis, $Mysqli);
					$ChefEmployee = Employee::Get(2, $Redis, $Mysqli);
					
					$uUserLeadEmployeeItemID = UserItem::Create($uUserID, $LeadEmployee->m_uLevelItemID, 1, $Redis, $Mysqli);
					$uUserChefEmployeeItemID = UserItem::Create($uUserID, $ChefEmployee->m_uLevelItemID, 1, $Redis, $Mysqli);
					
					$uUserLeadEmployeeID = UserEmployee::Create(
							$LeadEmployee->m_uLevelItemID, 
							$uUserLeadEmployeeItemID, 
							$uUserUnitID, 
							2, 
							$Redis, 
							$Mysqli);
					$uUserChefEmployeeID = UserEmployee::Create(
							$ChefEmployee->m_uLevelItemID, 
							$uUserChefEmployeeItemID, 
							$uUserUnitID, 
							1, 
							$Redis, 
							$Mysqli);
				}
			}
			
			echo pack('V', $uUserID ? $uUserID : 0);
		}
		
		break;
		
	case FUNCTION_FOOD_QUERY:
		$uUserID = (int)filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserID != 0)
		{
			include_once 'data/Item.php';
			$auUserItemIDs = UserItem::GetIDs($uUserID, $Redis, $Mysqli);
			if($auUserItemIDs)
			{
				$auFoodIDs = [];
				
				include_once 'data/Food.php';
				foreach ($auUserItemIDs as $uItemID => $uUserItemID)
				{
					$uFoodID = Food::GetID($uItemID, $Redis, $Mysqli);
					if(!$uFoodID)
						continue;
					$auFoodIDs[$uItemID] = $uFoodID;
				}
				
				include_once 'data/Craft.php';
				$sResult = pack('V', count($auFoodIDs));
				foreach ($auFoodIDs as $uItemID => $uFoodID)
				{
					$Food = Food::Get($uFoodID, $Redis, $Mysqli);
					$FoodItem = $Food->m_aItems[$uItemID];
					
					$sResult .= pack('V8', 
							$uFoodID, 
							$FoodItem->m_uLevelCraftID, 
							$Food->m_uExpectations, 
							$uItemID, 
							$FoodItem->m_uFoodItemID,
							$FoodItem->m_uFoodCraftID, 
							$FoodItem->m_uCalories, 
							$FoodItem->m_uDelicious);
					
					if($FoodItem->m_uLevelCraftID == 0)
						$sResult .= pack('V', 0);
					else
					{
						$Craft = Craft::Get($FoodItem->m_uLevelCraftID, $Redis, $Mysqli);
						
						$FoodItem = null;
						foreach ($Craft->m_aDestinations as $Destination)
						{
							if(isset($Food->m_aItems[$Destination->m_uOutputItemID]))
							{
								$FoodItem = $Food->m_aItems[$Destination->m_uOutputItemID];
								
								$sResult .= pack('V5', 
										$Destination->m_uOutputItemID, 
										$FoodItem->m_uFoodItemID,
										$FoodItem->m_uFoodCraftID, 
										$FoodItem->m_uCalories, 
										$FoodItem->m_uDelicious);
								
								break;
							}
						}
						
						if(!isset($FoodItem))
							$sResult .= pack('V', 0);
					}
				}
				
				echo $sResult;
			}
		}
		
		break;
	case FUNCTION_FOOD_UPGRADE:
		$uUserItemID = (int)filter_input(INPUT_POST, 'user_item_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserItemID != 0)
		{
			include_once 'data/Item.php';
			
			$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
			if($UserItem)
			{
				include_once 'data/Food.php';
				
				$uFoodID = Food::GetID($UserItem->m_uItemID, $Redis, $Mysqli);
				if($uFoodID)
				{
					$Food = Food::Get($uFoodID, $Redis, $Mysqli);
					
					include_once 'data/Craft.php';
					
					$Craft = Craft::Get($Food->m_aItems[$UserItem->m_uItemID]->m_uLevelCraftID, $Redis, $Mysqli);
					if($Craft && $Craft->Input($UserItem->m_uUserID, $Redis, $Mysqli))
					{
						$auItemCounts = $Craft->Output();
						
						$sItems = pack('V', count($auItemCounts));
						
						foreach ($auItemCounts as $uItemID => $uItemCount)
						{
							$uUserItemID = UserItem::Create($UserItem->m_uUserID, $uItemID, $uItemCount, $Redis, $Mysqli);
							
							//$Item = Item::Get($uItemID, $Redis, $Mysqli);
							
							$sItems .= pack('V3', $uUserItemID, $uItemID, $uItemCount);
							
							if(isset($Food->m_aItems[$uItemID]))
							{
								$FoodItem = $Food->m_aItems[$uItemID];
								
								$sResult = pack('V8', 
										$uFoodID, 
										$FoodItem->m_uLevelCraftID,
										$Food->m_uExpectations, 
										$uItemID, 
										$FoodItem->m_uFoodItemID,
										$FoodItem->m_uFoodCraftID, 
										$FoodItem->m_uCalories, 
										$FoodItem->m_uDelicious);
								
								if($FoodItem->m_uLevelCraftID == 0)
									$sResult .= pack('V', 0);
								else
								{
									$Craft = Craft::Get($FoodItem->m_uLevelCraftID, $Redis, $Mysqli);
									
									$FoodItem = null;
									foreach ($Craft->m_aDestinations as $Destination)
									{
										if(isset($Food->m_aItems[$Destination->m_uOutputItemID]))
										{
											$FoodItem = $Food->m_aItems[$Destination->m_uOutputItemID];
											
											$sResult .= pack('V5', 
													$Destination->m_uOutputItemID, 
													$FoodItem->m_uFoodItemID,
													$FoodItem->m_uFoodCraftID, 
													$FoodItem->m_uCalories, 
													$FoodItem->m_uDelicious);
											
											break;
										}
									}
									
									if(!isset($FoodItem))
										$sResult .= pack('V', 0);
								}
							}
						}
						
						echo $sResult . $sItems;
					}
				}
			}
		}
		
		break;
	case FUNCTION_UNIT_QUERY:
		$uUserID = (int)filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserID != 0)
		{
			include_once 'data/Unit.php';
			
			$auUserUnitIDs = UserUnit::GetIDs($uUserID, $Redis, $Mysqli);
			if($auUserUnitIDs)
			{
				$sResult = pack('V', count($auUserUnitIDs));
				foreach ($auUserUnitIDs as $uUnitID => $uUserUnitID)
				{
					$Unit = Unit::Get($uUnitID, $Redis, $Mysqli);
					
					$UserUnit = UserUnit::Get($uUserUnitID, $Redis, $Mysqli);
					
					$sResult .= pack('V9g', 
							$uUserUnitID, 
							$Unit->m_uLevelItemID,
							$Unit->m_uLevelCraftID, 
							$UserUnit->m_uTickTime,
							$UserUnit->m_uComfort, 
							$UserUnit->m_uExp, 
							$Unit->m_uMaxExp, 
							$Unit->m_uMaxTickTime, 
							$Unit->m_uTicksPerTime, 
							$Unit->m_fTip);
				}
				
				echo $sResult;
			}
		}
		break;
	case FUNCTION_UNIT_UPGRADE:
		$uUserUnitID = (int)filter_input(INPUT_POST, 'user_unit_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserUnitID != 0)
		{
			include_once 'data/Unit.php';
			
			$UserUnit = UserUnit::Get($uUserUnitID, $Redis, $Mysqli);
			if($UserUnit)
			{
				$Unit = Unit::Get($UserUnit->m_uUnitID, $Redis, $Mysqli);
				if($Unit->m_uMaxExp == $UserUnit->m_uExp)
				{
					include_once 'data/Craft.php';
					
					$Craft = Craft::Get($Unit->m_uLevelCraftID, $Redis, $Mysqli);
					if($Craft->Input($UserUnit->m_uUserID, $Redis, $Mysqli))
					{
						$auItemCounts = $Craft->Output();
						if($auItemCounts)
						{
							include_once 'data/Item.php';
							
							$sResult = '';
							$sItems = pack('V', count($auItemCounts));
							
							foreach ($auItemCounts as $uItemID => $uItemCount)
							{
								$uUserItemID = UserItem::Create($UserUnit->m_uUserID, $uItemID, $uItemCount, $Redis, $Mysqli);

								$uUnitID = Unit::GetID($uItemID, $Redis, $Mysqli);
								if($uUnitID)
								{
									$UserUnit->m_uUnitID = $uUnitID;
									$UserUnit->m_uComfort -= $Unit->m_uComfort;
									$UserUnit->m_uComfort += Unit::Get($uUnitID, $Redis, $Mysqli)->m_uComfort;
									$UserUnit->m_uExp = 0;
									$UserUnit->m_uTickTime = 0;
									$UserUnit->Save($uUserUnitID, $Redis, $Mysqli);
									
									$sResult = pack('V', $UserUnit->m_uComfort);
								}
								else
								{
									//$Item = Item::Get($uItemID, $Redis, $Mysqli);
									
									$sItems .= pack('V3', $uUserItemID, $uItemID, $uItemCount);
								}
							}
							
							echo $sResult . $sItems;
						}
					}
				}
			}
		}
		break;
	case FUNCTION_EMPLOYEE_QUERY:
		$uUserID = (int)filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserID != 0)
		{
			include_once 'data/Employee.php';
			
			$auUserEmpoyeeIDs = UserEmployee::GetIDs($uUserID, $Redis, $Mysqli);
			if($auUserEmpoyeeIDs)
			{
				$sResult = pack('V', count($auUserEmpoyeeIDs));
				
				foreach ($auUserEmpoyeeIDs as $uItemID => $uUserEmpoyeeID)
				{
					$UserEmpoyee = UserEmployee::Get($uUserEmpoyeeID, $Redis, $Mysqli);
					
					$uEmpoyeeID = Employee::GetID($uItemID, $Redis, $Mysqli);
					
					$Empoyee = Employee::Get($uEmpoyeeID, $Redis, $Mysqli);
					
					$sResult .= pack('V9', 
							$uUserEmpoyeeID,
							$UserEmpoyee->m_uUserUnitID, 
							$UserEmpoyee->m_uUserItemID, 
							$Empoyee->m_uLevelCraftID, 
							$Empoyee->m_uService, 
							$Empoyee->m_uCharm, 
							$Empoyee->m_uCook, 
							$Empoyee->m_uSkills, 
							$UserEmpoyee->m_uPosition);
				}
				
				echo $sResult;
			}
		}
		break;
	case FUNCTION_EMPLOYEE_POSITION:
		$uUserEmployeeID = (int)filter_input(INPUT_POST, 'user_employee_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserEmployeeID != 0 && isset($_POST['position']))
		{
			$UserEmployee = UserEmployee::Get($uUserEmployeeID, $Redis, $Mysqli);
			echo $UserEmployee->SavePosition(
					$uUserEmployeeID, 
					(int)filter_input(INPUT_POST, 'position', FILTER_SANITIZE_NUMBER_INT), 
					$Redis, 
					$Mysqli) ? 1 : 0;
		}
		break;
	case FUNCTION_EMPLOYEE_UPGRADE:
		$uUserEmployeeID = (int)filter_input(INPUT_POST, 'user_employee_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserEmployeeID != 0)
		{
			$UserEmployee = UserEmployee::Get($uUserEmployeeID, $Redis, $Mysqli);
			if($UserEmployee)
			{
				$Employee = Employee::Get($UserEmployee->m_uEmployeeID, $Redis, $Mysqli);
				
				if($Employee->m_uMaxExp == $UserEmployee->m_uExp)
				{
					
				}
			}
		}
		break;
	case FUNCTION_ITEM_QUERY:
		$uUserID = (int)filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserID != 0)
		{
			include_once 'data/Item.php';
			$auUserItemIDs = UserItem::GetIDs($uUserID, $Redis, $Mysqli);
			if($auUserItemIDs)
			{
				$sResult = pack('V', count($auUserItemIDs));
				foreach ($auUserItemIDs as $uItemID => $uUserItemID)
				{
					$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);

					//$Item = Item::Get($uItemID, $Redis, $Mysqli);
						
					$sResult .= pack('V3', $uUserItemID, $uItemID, $UserItem->m_uCount);
				}
				
				echo $sResult;
			}
		}
		
		break;
	case FUNCTION_CRAFT_QUERY:
		$uUserID = (int)filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
		if($uUserID != 0)
		{
			include_once 'data/Item.php';
			$auUserItemIDs = UserItem::GetIDs($uUserID, $Redis, $Mysqli);
			if($auUserItemIDs)
			{
				$auCraftIDs = [];
				
				include_once 'data/Food.php';
				include_once 'data/Unit.php';
				include_once 'data/Employee.php';
				foreach ($auUserItemIDs as $uItemID => $uUserItemID)
				{
					$uFoodID = Food::GetID($uItemID, $Redis, $Mysqli);
					if($uFoodID)
					{
						$Food = Food::Get($uFoodID, $Redis, $Mysqli);
						$FoodItem = $Food->m_aItems[$uItemID];
						//trigger_error(var_export($Food, true));
						if($FoodItem->m_uFoodCraftID != 0)
							$auCraftIDs[$FoodItem->m_uFoodItemID] = $FoodItem->m_uFoodCraftID;
						
						if($FoodItem->m_uLevelCraftID != 0)
							$auCraftIDs[$uItemID] = $FoodItem->m_uLevelCraftID;
					}
					
					$uUnitID = Unit::GetID($uItemID, $Redis, $Mysqli);
					if($uUnitID)
					{
						$Unit = Unit::Get($uUnitID, $Redis, $Mysqli);
						if( $Unit->m_uLevelCraftID != 0)
							$auCraftIDs[$uItemID] =  $Unit->m_uLevelCraftID;
					}
					
					$uEmployeeID = Employee::GetID($uItemID, $Redis, $Mysqli);
					if($uEmployeeID)
					{
						$Employee = Employee::Get($uEmployeeID, $Redis, $Mysqli);
						$EmployeeItem = $Employee->m_aItems[$uItemID];
						if($EmployeeItem->m_uLevelCraftID != 0)
							$auCraftIDs[$uItemID] = $EmployeeItem->m_uLevelCraftID;
					}
					
					//customers
					//quests
				}
				
				include_once 'data/Craft.php';
				$sResult = pack('V', count($auCraftIDs));
				foreach ($auCraftIDs as $uCraftID)
				{
					$Craft = Craft::Get($uCraftID, $Redis, $Mysqli);
					
					$sResult .= pack('V', $uCraftID);
					
					if(isset($Craft->m_aaDestinations))
					{
						$uCount = 0;
						foreach ($Craft->m_aaDestinations as $aCraftDestinations)
							$uCount += count($aCraftDestinations);
						
						$sResult .= pack('V', $uCount);
						foreach ($Craft->m_aaDestinations as $aCraftDestinations)
						{
							foreach ($aCraftDestinations as $CraftDestination)
								$sResult .= pack('V2g', 
										$CraftDestination->m_uOutputItemID, 
										$CraftDestination->m_uOutputItemCount, 
										$CraftDestination->m_fChance);
						}
					}
					else
						$sResult .= pack('V', 0);
					
					if(isset($Craft->m_aSouces))
					{
						$sResult .= pack('V', count($Craft->m_aSouces));
						foreach ($Craft->m_aSouces as $CraftSource)
						{
							$uLabelLength = strlen($CraftSource->m_sLabel);
							
							$sResult .= pack("V2Ca$uLabelLength", 
									$CraftSource->m_uInputItemID, 
									$CraftSource->m_uInputItemCount, 
									$uLabelLength, 
									$CraftSource->m_sLabel);
						}
					}
					else
						$sResult .= pack('V', 0);
				}
				
				echo $sResult;
			}
		}
		
		break;
	case FUNCTION_GAME_START:
		$uUserUnitID = (int)filter_input(INPUT_POST, 'user_unit_id', FILTER_SANITIZE_NUMBER_INT);
		
		$sKey = NAME_SPACE_GAME . $uUserUnitID;
		//if($Redis->exists($sKey))
		//	break;
			
		include_once 'data/Unit.php';
		$UserUnit = UserUnit::Get($uUserUnitID, $Redis, $Mysqli);
		if($UserUnit)
		{
			include_once 'data/Item.php';
			
			$Command = new Command();
			
			$Command->m_auFoodCounts = [];
			
			include_once 'data/Food.php';
			$auFoodIDs = isset($_POST['food_ids']) && is_array($_POST['food_ids']) ? $_POST['food_ids'] : false;
			if($auFoodIDs)
			{
				$auUserItemIDs = UserItem::GetIDs($UserUnit->m_uUserID, $Redis, $Mysqli);
				if($auUserItemIDs)
				{
					$auFoodCounts = isset($_POST['food_counts']) && is_array($_POST['food_counts']) ? $_POST['food_counts'] : false;
					$uNumFoodCounts = $auFoodCounts ? count($auFoodCounts) : 0;
					
					include_once 'data/Craft.php';
					$uNumFoodIDs = count($auFoodIDs);
					for($i = 0; $i < $uNumFoodIDs; ++$i)
					{
						$uFoodID = $auFoodIDs[$i];
						$Food = Food::Get($uFoodID, $Redis, $Mysqli);
						if($Food && isset($Food->m_aItems))
						{
							$uFoodCount = $i < $uNumFoodCounts ? $auFoodCounts[$i] : 0;

							foreach ($Food->m_aItems as $uItemID => $FoodItem)
							{
								if(isset($auUserItemIDs[$uItemID]))
								{
									$Craft = Craft::Get($FoodItem->m_uFoodCraftID, $Redis, $Mysqli);
									if($Craft)
									{
										for($j = 0; $j < $uFoodCount; ++$j)
										{
											if(!$Craft->Input($UserUnit->m_uUserID, $Redis, $Mysqli))
											{
												$uFoodCount = $j;
												
												break;
											}
										}
										
										$Command->m_aFoods[$uFoodID] = new CommandFood($uItemID, $uFoodCount);
									}
									
									break;
								}
							}
						}
					}
				}
			}
			
			include_once 'data/Customer.php';
			
			$Unit = Unit::Get($UserUnit->m_uUnitID, $Redis, $Mysqli);
			$afCustomerChances = [];
			$fTotalChance = 0.0;
			foreach ($Unit->m_aCustomers as $uCustomerID => $UnitCustomer)
			{
				$fChance = $UnitCustomer->m_fChanceToMeet;
				$Customer = Customer::Get($uCustomerID, $Redis, $Mysqli);
				if(isset($Customer->m_aFoods))
				{
					foreach ($Customer->m_aFoods as $uFoodID => $CommandFood)
					{
						if(isset($Customer->m_aFoods[$uFoodID]))
							$fChance += $Customer->m_aFoods[$uFoodID]->m_fChanceToMeet;
					}
				}
				
				$afCustomerChances[$uCustomerID] = $fChance;
				
				$fTotalChance += $fChance;
			}
			
			//$auUserItemIDs = UserItem::GetIDs($UserUnit->m_uUnitID, $Redis, $Mysqli);
			$auUserCustomerIDs = UserCustomer::GetIDs($UserUnit->m_uUserID, $Redis, $Mysqli);
			$uCustomerCount = mt_rand($Unit->m_uMinCustomerCount, $Unit->m_uMaxCustomerCount);
			for($i = 0; $i < $uCustomerCount; ++$i)
			{
				$fRandom = mt_rand() * 1.0 / mt_getrandmax();
				foreach ($afCustomerChances as $uCustomerID => $fChance)
				{
					$fChance /= $fTotalChance;
					if($fRandom > $fChance)
					{
						$fRandom -= $fChance;
						
						continue;
					}
					
					$uUserCustomerID = 0;
					$Customer = Customer::Get($uCustomerID, $Redis, $Mysqli);
					foreach ($Customer->m_aItems as $uItemID => $CustomerItem)
					{
						if(isset($auUserCustomerIDs[$uItemID]))
						{
							$uUserCustomerID = $auUserCustomerIDs[$uItemID];
							
							break;
						}
					}
					
					if($uUserCustomerID == 0)
					{
						$uCustomerItemID = key($Customer->m_aItems);
						
						$uUserCustomerItemID = UserItem::Create($UserUnit->m_uUserID, $uCustomerItemID, 1, $Redis, $Mysqli);
						$uUserCustomerID = UserCustomer::Create($uCustomerItemID, $UserUnit->m_uUserID, $uUserCustomerItemID, $Redis, $Mysqli);
					}
					
					$Command->m_auCustomerIDs[$uUserCustomerID] = $uCustomerID;
					
					break;
				}
			}
			
			$Redis->set($sKey, serialize($Command));
			
			$sResult = pack('V', count($Command->m_auCustomerIDs));
			foreach ($Command->m_auCustomerIDs as $uUserCustomerID => $uCustomerID)
			{
				$sResult .= pack('V2', $uUserCustomerID, $uCustomerID);
				
				$UserCustomer = UserCustomer::Get($uUserCustomerID, $Redis, $Mysqli);
				
				$UserItem = UserItem::Get($UserCustomer->m_uUserItemID, $Redis, $Mysqli);
				
				$Customer = Customer::Get($uCustomerID, $Redis, $Mysqli);
				
				$CustomerItem = $Customer->m_aItems[$UserItem->m_uItemID];
				
				$sResult .= pack('V3', $CustomerItem->m_uMaxCalories, $CustomerItem->m_uMaxExpectations, $Unit->m_aCustomers[$uCustomerID]->m_uExpectations);
			}
			
			echo $sResult;
		}
		break;
	case FUNCTION_GAME_END:
		$uUserUnitID = (int)filter_input(INPUT_POST, 'user_unit_id', FILTER_SANITIZE_NUMBER_INT);
		
		$sKey = NAME_SPACE_GAME . $uUserUnitID;
		$Command = $Redis->get($sKey);
		if($Command)
		{
			$Command = unserialize($Command);
			//trigger_error(var_export($_POST, true));
			
			if(isset($_POST['food_ids']) && is_array($_POST['food_ids']) && 
					isset($_POST['customer_ids']) && is_array($_POST['customer_ids']) && 
					isset($_POST['kitchen_employee_ids']) && is_array($_POST['kitchen_employee_ids']) &&
					isset($_POST['lobby_employee_ids']) && is_array($_POST['lobby_employee_ids']))
			{
				$anUserCustomerExpectations = [];
				$anUserCustomerCalories = [];
				
				include_once 'data/Customer.php';
				foreach ($Command->m_auCustomerIDs as $uUserCustomerID => $uCustomerID)
				{
					$Customer = Customer::Get($uCustomerID, $Redis, $Mysqli);
					$anUserCustomerExpectations[$uUserCustomerID] = $Customer->m_uMaxExpectations;
					$anUserCustomerCalories[$uUserCustomerID] = $Customer->m_uMaxCalories;
				}
				
				include_once 'data/Unit.php';
				$UserUnit = UserUnit::Get($uUserUnitID, $Redis, $Mysqli);
				$Unit = Unit::Get($UserUnit->m_uUnitID, $Redis, $Mysqli);
				
				$auUserFoodIDs = $_POST['food_ids'];
				$auUserCustomerIDs = $_POST['customer_ids'];
				$auUserKitchenEmployeeIDs = $_POST['kitchen_employee_ids'];
				$auUserLobbyEmployeeIDs = $_POST['lobby_employee_ids'];
				
				$uCount = min(count($auUserFoodIDs), count($auUserCustomerIDs), count($auUserKitchenEmployeeIDs), count($auUserLobbyEmployeeIDs));
				
				$auOutputFoodItemCounts = [];
				
				$auUserItemCounts = [];
				
				//$uMoney = 0;
				
				include_once 'data/Craft.php';
				include_once 'data/Food.php';
				include_once 'data/Employee.php';
				for($i = 0; $i < $uCount; ++$i)
				{
					$uFoodID = $auUserFoodIDs[$i];
					
					$Food = Food::Get($uFoodID, $Redis, $Mysqli);
					if(!$Food)
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$uUserCustomerID = $auUserCustomerIDs[$i];
					if(!isset($Command->m_auCustomerIDs[$uUserCustomerID]))
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					if($anUserCustomerCalories[$uUserCustomerID] < 0)
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$nUserCustomerExpectations = $anUserCustomerExpectations[$uUserCustomerID];
					if($nUserCustomerExpectations <= 0)
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$uCustomerID = $Command->m_auCustomerIDs[$uUserCustomerID];
					
					$nExpectations = $Unit->m_aCustomers[$uCustomerID]->m_uExpectations;
					
					$Customer = Customer::Get($uCustomerID, $Redis, $Mysqli);
					if(isset($Customer->m_aFoods[$uFoodID]))
						$nExpectations += $Customer->m_aFoods[$uFoodID]->m_uExpectations;
					
					$nExpectations *= $Food->m_uExpectations;
					$nUserCustomerExpectations -= $nExpectations;
					$anUserCustomerExpectations[$uCustomerID] = $nUserCustomerExpectations;
					
					$uUserKitchenEmployeeID = $auUserKitchenEmployeeIDs[$i];
					if($uUserKitchenEmployeeID == 0)
						continue;
						
					if(!isset($Command->m_aFoods[$uFoodID]))
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$CommandFood = $Command->m_aFoods[$uFoodID];
						
					$uOutputLevelItemID = 0;
					//trigger_error(var_export($Food, true));
					$FoodItem = $Food->m_aItems[$CommandFood->m_uItemID];
					if(isset($auOutputFoodItemCounts[$FoodItem->m_uFoodItemID])/* && $auOutputFoodItemCounts[$FoodItem->m_uItemID] > 0*/)
						$uOutputLevelItemID = $CommandFood->m_uItemID;
					
					if($uOutputLevelItemID == 0)
					{
						$uCommandFoodCount = $CommandFood->m_uCount;
						
						$Craft = Craft::Get($FoodItem->m_uFoodCraftID, $Redis, $Mysqli);
						if($uCommandFoodCount == 0)
						{
							if(!$Craft->Input($UserUnit->m_uUserID, $Redis, $Mysqli))
							{
								trigger_error("wtf??");
								
								continue;
							}
							
							$uCommandFoodCount = 1;
						}
						
						for($j = 0; $j < $uCommandFoodCount; ++$j)
						{
							$auOutputItemCounts = $Craft->Output();
							if(!$auOutputItemCounts)
							{
								trigger_error("wtf??");
								
								continue;
							}
							
							foreach ($Food->m_aItems as $uItemID => $FoodItem)
							{
								if(isset($auOutputItemCounts[$FoodItem->m_uFoodItemID]))
								{
									$uOutputLevelItemID = $uItemID;
									
									$uOutputFoodItemCount = isset($auOutputFoodItemCounts[$FoodItem->m_uFoodItemID]) ? $auOutputFoodItemCounts[$FoodItem->m_uFoodItemID] : 0;
									$auOutputFoodItemCounts[$FoodItem->m_uFoodItemID] = $uOutputFoodItemCount + $auOutputItemCounts[$FoodItem->m_uFoodItemID];
									
									unset($auOutputItemCounts[$FoodItem->m_uFoodItemID]);
								}
							}
						}
					}
					
					if($uOutputLevelItemID == 0)
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$uOutputFoodItemID = $Food->m_aItems[$CommandFood->m_uItemID]->m_uFoodItemID;
					
					if(--$auOutputFoodItemCounts[$uOutputFoodItemID] == 0)
						unset($auOutputFoodItemCounts[$uOutputFoodItemID]);
						
					$uUserLobbyEmployeeID = $auUserLobbyEmployeeIDs[$i];
					if($uUserLobbyEmployeeID == 0 || !isset($Food->m_aItems[$uOutputLevelItemID]))
						continue;
					
					$UserKitchenEmployee = UserEmployee::Get($uUserKitchenEmployeeID, $Redis, $Mysqli);
					if(!$UserKitchenEmployee)
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$UserLobbyEmployee = UserEmployee::Get($uUserLobbyEmployeeID, $Redis, $Mysqli);
					if(!$UserLobbyEmployee)
					{
						trigger_error("wtf??");
						
						continue;
					}
					
					$OutputFoodItem = $Food->m_aItems[$uOutputLevelItemID];
					
					$nExperience = 0;
					
					$UserKitchenEmployeeItem = UserItem::Get($UserKitchenEmployee->m_uUserItemID, $Redis, $Mysqli);
					$uKitchenEmployeeID = Employee::GetID($UserKitchenEmployeeItem->m_uItemID, $Redis, $Mysqli);
					$KitchenEmployee = Employee::Get($uKitchenEmployeeID, $Redis, $Mysqli);
					$KitchenEmployeeItem = $KitchenEmployee->m_aItems[$UserKitchenEmployeeItem->m_uItemID];
					$nExperience += $KitchenEmployeeItem->m_uCook * $OutputFoodItem->m_uDelicious;
					
					$UserLobbyEmployeeItem = UserItem::Get($UserLobbyEmployee->m_uUserItemID, $Redis, $Mysqli);
					$uLobbyEmployeeID = Employee::GetID($UserLobbyEmployeeItem->m_uItemID, $Redis, $Mysqli);
					$LobbyEmployee = Employee::Get($uLobbyEmployeeID, $Redis, $Mysqli);
					$LobbyEmployeeItem = $LobbyEmployee->m_aItems[$UserKitchenEmployeeItem->m_uItemID];
					$nExperience += $LobbyEmployeeItem->m_uService * $Unit->m_uComfort;
					
					$nUserCustomerExpectations += $nExperience;
					$anUserCustomerExpectations[$uUserCustomerID] = $nUserCustomerExpectations;
					
					//$OutputItem = Item::Get($uOutputFoodItemID, $Redis, $Mysqli);
					//$uValue = $OutputItem->m_uValue;
					
					$UserCustomer = UserCustomer::Get($uUserCustomerID, $Redis, $Mysqli);
					$UserCustomerItem = UserItem::Get($UserCustomer->m_uUserItemID, $Redis, $Mysqli);
					$CustomerItem = $Customer->m_aItems[$UserCustomerItem->m_uItemID];
					if($nUserCustomerExpectations > $CustomerItem->m_uMaxExpectations/* && $nExperience > $nExpectations*/ || 
							(($LobbyEmployeeItem->m_uSkills & Employee::SKILL_TIP) == Employee::SKILL_TIP))
					{
						foreach($auOutputItemCounts as $uOutputItemID => $uOutputItemCount)
						{
							$uOutputItemCount += round($uOutputItemCount * $LobbyEmployee->m_uCharm * $Unit->m_fTip);
							
							$auOutputItemCounts[$uOutputItemID] = $uOutputItemCount;
						}
					}	
						
					foreach($auOutputItemCounts as $uOutputItemID => $uOutputItemCount)
					{
						$uUserItemID = UserItem::Apply($UserUnit->m_uUserID, $uOutputItemID, $uOutputItemCount, $Redis, $Mysqli);
						if($uUserItemID)
							$auUserItemCounts[$uUserItemID] = $uOutputItemCount;
					}
					//$uMoney += $uValue;
					
					$anUserCustomerCalories[$uUserCustomerID] -= $OutputFoodItem->m_uCalories;
				}
				
				/*if($uMoney != 0)
				{
					include_once 'data/User.php';
					$User = User::Get($UserUnit->m_uUserID, $Redis, $Mysqli);
					$User->m_uMoney += $uMoney;
					$User->SaveMoney($UserUnit->m_uUserID, $Redis, $Mysqli);
				}*/
				
				$uUnitExp = 0;
				foreach ($anUserCustomerExpectations as $uUserCustomerID => $nUserCustomerExpectations)
				{
					$UserCustomer = UserCustomer::Get($uUserCustomerID, $Redis, $Mysqli);
					$UserCustomerItem = UserItem::Get($UserCustomer->m_uUserItemID, $Redis, $Mysqli);
					$CustomerID = Customer::GetID($UserCustomerItem->m_uItemID, $Redis, $Mysqli);
					$Customer = Customer::Get($CustomerID, $Redis, $Mysqli);
					$CustomerItem = $Customer->m_aItems[$UserCustomerItem->m_uItemID];
					
					$fScale = max(min($CustomerItem->m_uMaxExpectations, $nUserCustomerExpectations), 0) * 1.0 / $CustomerItem->m_uMaxExpectations;
					
					$uCustomerExp = min((int)round($UserCustomer->m_uExp + $fScale * $CustomerItem->m_uMaxExpPerTime), $CustomerItem->m_uMaxExp);
					if($uCustomerExp != $UserCustomer->m_uExp)
					{
						$UserCustomer->m_uExp = $uCustomerExp;
						$UserCustomer->SaveExp($uUserCustomerID, $Redis, $Mysqli);
					}
					
					$uUnitExp += (int)round($fScale * $CustomerItem->m_uMaxExpToUnit);
				}
				
				$uUnitExp = min($UserUnit->m_uExp + $uUnitExp, $Unit->m_uMaxExp);
				if($uUnitExp != $UserUnit->m_uExp)
				{
					$UserUnit->m_uExp = $uUnitExp;
					$UserUnit->m_uTickTime = max($UserUnit->m_uTickTime, time() - $Unit->m_uMaxTickTime) + $Unit->m_uTicksPerTime;
					
					$UserUnit->SaveExp($uUserUnitID, $Redis, $Mysqli);
				}
				
				$sResult = pack('V2', $uUnitExp, count($auUserItemCounts));
				foreach ($auUserItemCounts as $uUserItemID => $uUserItemCount)
				{
					$UserItem = UserItem::Get($uUserItemID, $Redis, $Mysqli);
					//$Item = Item::Get($UserItem->m_uItemID, $Redis, $Mysqli);
					
					$sResult .= pack('V3', $uUserItemID, $UserItem->m_uItemID, $UserItem->m_uCount);
				}
				
				echo $sResult;
			}
			
			$Redis->delete($sKey);
		}
		break;
}