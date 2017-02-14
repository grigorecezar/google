<?php namespace Index\Google\Test;

use PHPUnit_Framework_TestCase;

use Index\Google\Google;

class GoogleTest extends PHPUnit_Framework_TestCase
{
    public function testCalendarCreationForIndividualAccess()
    {
    	$appCredentials = TestEnv::APP_CREDENTIALS;
        $userAccessToken = json_decode(TestEnv::USER_ACCESS_TOKEN, true);

    	$google = Google::createForIndividualAccess($appCredentials);
        $calendar = $google->createCalendar($userAccessToken);

        $this->assertInstanceOf('Index\\Google\\Calendar', $calendar);
    }

    public function testCalendarCreationForDomainAccess()
    {
        $appCredentials = TestEnv::APP_CREDENTIALS_DOMAIN_ACCESS_FILE_PATH;
        $userEmailToken = TestEnv::USER_EMAIL_TOKEN;

        $google = Google::createForDomainWideAccess($appCredentials);
        $calendar = $google->createCalendar($userEmailToken);

        $this->assertInstanceOf('Index\\Google\\Calendar', $calendar);
    }

    public function testGmailCreationForIndividualAccess()
    {
        $appCredentials = TestEnv::APP_CREDENTIALS;
        $userAccessToken = json_decode(TestEnv::USER_ACCESS_TOKEN, true);

        $google = Google::createForIndividualAccess($appCredentials);
        $gmail = $google->createGmail($userAccessToken);

        $this->assertInstanceOf('Index\\Google\\Gmail', $gmail);
    }

    public function testGmailCreationForDomainAccess()
    {
        $appCredentials = TestEnv::APP_CREDENTIALS_DOMAIN_ACCESS_FILE_PATH;
        $userEmailToken = TestEnv::USER_EMAIL_TOKEN;

        $google = Google::createForDomainWideAccess($appCredentials);
        $gmail = $google->createGmail($userEmailToken);

        $this->assertInstanceOf('Index\\Google\\Gmail', $gmail);
    }
}
