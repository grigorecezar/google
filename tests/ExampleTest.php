<?php namespace Index\Google\Test;

class ExampleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that true does in fact equal true
     */
    public function testTrueIsTrue()
    {
    	$google = new \Index\Google\AbstractGoogle();
    	$google->echoPhrase('holaaa');
        $this->assertTrue(true);
    }
}
