<?php namespace Index\Google\Test;

use PHPUnit_Framework_TestCase;

use Index\Google\Calendar;
use Index\Google\Google;

class CalendarTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test that true does in fact equal true
     */
    public function testTrueIsTrue()
    {
    	$userToken = json_decode($userToken);
    	
    	$google = 
    	$calendar = new Calendar($appCredentials, $userToken);
    	$text = $calendar->getCalendarEvents();
        
        dd($text);
        // $this->assertTrue(true);


    }
}
