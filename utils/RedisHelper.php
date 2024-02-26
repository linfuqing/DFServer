<?php

const REDIS_HOST = 'localhost';
const REDIS_PORT = 6379;

function LockRedisEx(
    $Instance, 
    $sKey, 
    $sToken, 
    $uTimeout = 1000, 
    $uRetryDelay = 200, 
    $uRetryCount = 3, 
    $fClockDriftFactor = 0.01)
{
    do 
    {
        $uStartTime = microtime(true);
        
        $bResult = LockRedis($Instance, $sKey, $sToken, $uTimeout);
        # Add 2 milliseconds to the drift to account for Redis expires
        # precision, which is 1 millisecond, plus 1 millisecond min drift
        # for small TTLs.
        if ($bResult && $uTimeout - (microtime(true) - $uStartTime) > (int)round($uTimeout * $fClockDriftFactor) + 2)
            return true;

        // Wait a random delay before to retry
        usleep($uRetryDelay);
        
    } while (--$uRetryCount > 0);
    
    return false;
}

function LockRedis($Instance, $sKey, $sToken, $uTimeout = 1000)
{
    return $Instance->set($sKey, $sToken, ['NX', 'PX' => $uTimeout]);
}

function UnlockRedis($Instance, $sKey, $sToken)
{
    $sScript = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';
    return $Instance->eval($sScript, [$sKey, $sToken], 1);
}

function CreateRedis()
{
    $Result = new Redis();
    $Result->connect(REDIS_HOST, REDIS_PORT);
    
    return $Result;
}