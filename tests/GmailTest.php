<?php namespace IndexIO\Google\Test;

use PHPUnit_Framework_TestCase;

use IndexIO\Google\Google;
use Carbon\Carbon;

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

        $emails = $gmail->getLastEmails(5);
        foreach ($emails as $email) {
            $this->assertInstanceOf('IndexIO\\Google\\Email', $email);
        }
    }

    public function testGetEmailsForSpecificInterval()
    {
        $appCredentials = TestEnv::APP_CREDENTIALS;
        $userAccessToken = json_decode(TestEnv::USER_ACCESS_TOKEN, true);

        $google = Google::createForIndividualAccess($appCredentials);
        $gmail = $google->createGmail($userAccessToken);

        $startDate = new Carbon('yesterday');
        $endDate = Carbon::now();

        $emails = $gmail->getEmailsInInterval($startDate, $endDate);
        foreach ($emails as $email) {
            $this->assertGreaterThan($startDate, $email->getDate());
            $this->assertGreaterThan($email->getDate(), $endDate);
        }
    }
}
