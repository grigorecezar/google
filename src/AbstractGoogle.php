<?php namespace IndexIO\Google;

use Google_Client;
use Google_Auth_AssertionCredentials;

abstract class AbstractGoogle
{
    /**
     *
     */
    protected $appName = 'Index-IO';

    /**
     *
     */
    private $permissions = [];

    /**
     *
     */
    private $googleClient = null;

    /**
     *
     */
    protected $client = null;

    /**
     * @param $appCredentials are the google crendetials
     * Format 
     *
     * @param $userToken is the oauth2 token that google sent back when the user synced. 
     * Format ['access_token' => '', 'token_type' => 'Bearer', 'refresh_token' => '', 'expiry_date' => 1452651712765]
     *
     * @param $permissions are the scopes requested when the authorization takes place
     */
    public function __construct($appCredentials, $userToken, $domainWide = false, $permissions = [], $sslEnabled = false)
    {
        // if a specific set of permissions (scopes in google terms) are being passed
        // then using those otherwise the default child class scope
        if($permissions) {
            $this->permissions = $permissions;
        } else {
            $this->permissions = static::PERMISSIONS;
        }

        if($domainWide) {
            $this->setDomainWideClient($userToken, $appCredentials, $sslEnabled);
        } else {
            $this->setGoogleClientIndividualAccess($appCredentials, $userToken, $sslEnabled);
        }

        $this->setClient();
    }

    abstract protected function setClient();

    /**
    * Creates an authorized Gooogle API client using the $userToken passed in constructor
    */
    private function setGoogleClientIndividualAccess($appCredentials, $userAccessToken, $sslEnabled)
    {
        $client = new Google_Client();
        $client->setAccessType('offline');
        $client->setScopes($this->permissions);

        $client->setAuthConfig($appCredentials);

        $userAccessToken['expires_in'] = $userAccessToken['expiry_date'] - time() * 1000;
        $client->setAccessToken($userAccessToken);
        
        if ($client->isAccessTokenExpired()) {
            $client->refreshToken($client->getRefreshToken());
        }

        $client->setHttpClient(new \GuzzleHttp\Client(array(
            'verify' => false,
        )));
        $this->googleClient = $client;
    }

    /**
     *
     */
    public function setDomainWideClient($userToImpersonate, $googleCredentialsFilePath, $sslEnabled)
    {
        $client = new Google_Client();
        $client->setScopes($this->permissions);
        $client->setAuthConfig($googleCredentialsFilePath);
        $client->setSubject($userToImpersonate);

        $client->setHttpClient(new \GuzzleHttp\Client(array(
            'verify' => false,
        )));
        $this->googleClient = $client;
    }

    /**
     * @return Google_Client the authorized client object
     */
    protected function getGoogleClient()
    {
        return $this->googleClient;
    }

    protected function getClient()
    {
        return $this->client;
    }
}
