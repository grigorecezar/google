<?php namespace IndexIO\Google\Test;

use PHPUnit_Framework_TestCase;

use IndexIO\Google\Google;

class GmailTest extends PHPUnit_Framework_TestCase
{
    public function testGetEmailById()
    {
        // TODO;
    }

    public function testGetEmails()
    {
    	$appCredentials = TestEnv::APP_CREDENTIALS;
        $userAccessToken = json_decode(TestEnv::USER_ACCESS_TOKEN, true);

    	$google = Google::createForIndividualAccess($appCredentials);
        $gmail = $google->createGmail($userAccessToken);

        $emails = $gmail->getEmails(5);
        foreach ($emails as $email) {
            $this->assertInstanceOf('IndexIO\\Google\\Email', $email);
        }
    }
}
