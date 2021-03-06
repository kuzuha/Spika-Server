<?php

/*
 * This file is part of the Silex framework.
 *
 * Copyright (c) 2013 clover studio official account
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Silex\Application;

function abortManually($errMessage){
    $arr = array('message' => $errMessage, 'error' => 'logout');
    header("HTTP/1.0 403 Forbidden");
    echo json_encode($arr);
    die();
}

function makeBeforeTokenChecker()
{
    return function (Request $request, Application $app) {
	    
	    // pass token check when unit testing
	    if(!function_exists("getallheaders"))
	    	return;
	    	
        $headers = getallheaders();
        $tokenReceived = $headers['token'];
        $useridReceived = $headers['user_id'];
        $isCreateUserRequest = false;

        $app['monolog']->addDebug("token : {$tokenReceived}");
        $app['monolog']->addDebug("medhod : " . $request->getMethod());
        $app['monolog']->addDebug("user id : {$useridReceived}");
        $app['monolog']->addDebug(print_r($_SERVER,true));

        if($request->getMethod() == "POST" && $useridReceived == "create_user"){
            $isCreateUserRequest = true;
            return;
        }

        if(empty($tokenReceived) || empty($useridReceived)){
            abortManually("No token sent");
        }

        $query = "?key=" . urlencode('"' . $useridReceived . '"');
        $result = $app['spikadb']->doGetRequest("/_design/app/_view/find_user_by_id{$query}",false);
        $userData = json_decode($result, true);

        if(!isset($userData['rows'][0]['value']['_id']) || $userData['rows'][0]['value']['_id'] != $useridReceived){
            abortManually("No token sent");
        }

        if($tokenReceived != $userData['rows'][0]['value']['token']){
            abortManually("Invalid token");
        }

        $tokenTimestamp = $userData['rows'][0]['value']['token_timestamp'];
        $currentTimestamp = time();
        $tokenTime = $tokenTimestamp + TokenValidTime;

        if ($tokenTime < $currentTimestamp) {
            abortManually("Token expired");
        }


        //$app['monolog']->addDebug("check token user id : " . $userid);
        //$app['monolog']->addDebug("check token user : " . print_r($userData,true));

    };
}
