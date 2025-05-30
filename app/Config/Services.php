<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
	// public static function example($getShared = true)
	// {
	//     if ($getShared)
	//     {
	//         return static::getSharedInstance('example');
	//     }
	//
	//     return new \CodeIgniter\Example();
	// }

	public static function acquirer($getShared = true,$type=null){
	    // if ($getShared)
	    // {
		// 	$getShared=0;
	    //     return static::getSharedInstance('acquirer',$getShared,$type);
	    // }
		if( getenv('test.acquirerMock')==1 ){
			return new \App\Libraries\AcquirerUnitellerMock();
		}
		if( $type=='AcquirerUniteller' ){
			return new \App\Libraries\AcquirerUniteller();
		}
		if( $type=='AcquirerUnitellerSBP' ){
			return new \App\Libraries\AcquirerUnitellerSBP();
		}
		return new \App\Libraries\AcquirerRncb();
	}

	public static function cashier($getShared = true){
	    if ($getShared)
	    {
	        return static::getSharedInstance('cashier');
	    }
		if( getenv('test.cashierMock')==1 ){
			return new \App\Libraries\CashierKitOnlineMock();
		}
	    return new \App\Libraries\CashierKitOnline();
	}

	public static function sms($getShared = true){
	    if ($getShared)
	    {
	        return static::getSharedInstance('sms');
	    }
	
	    return new \App\Libraries\Sms4B();
	}

	public static function voice($getShared = true){
	    if ($getShared)
	    {
	        return static::getSharedInstance('voice');
	    }
	
	    return new \App\Libraries\SmsCVoice();
	}
}
