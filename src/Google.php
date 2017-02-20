<?php namespace IndexIO\Google;

/**
 * Interacting point with google calendar and gmail wrappers.
 * Create calendar and gmail instances using the factory methods here.
 *
 * Default is access through individual oauth2 tokens but domain-wide 
 * access can be used too if (passed in the constructor). Custom app name 
 * can be passed to.
 */
class Google
{
	/**
     *
     */
    protected $appName = 'Index-IO';

    /**
     * 
     */
    private $appCredentials = [];

    /**
     *
     */
    private $googleApiClient = null;

    /**
     *
     */
    private $domainWideClient = false;

    /**
     *
     */
    private $sslEnabled = false;

	/**
	 * 
	 */
	private function __construct($appCredentials = null, $useDomainAccess = false, $sslEnabled = true)
	{
		if($appCredentials) {
			$this->setAppCredentials($appCredentials);
		}

		if($useDomainAccess) {
			$this->useDomainAccess();
		}

		$this->sslEnabled = $sslEnabled;
	}

	public static function createForDomainWideAccess($appCredentials, $sslEnabled = true)
	{
		$google = new Google($appCredentials, true, $sslEnabled);
		return $google;
	}

	public static function createForIndividualAccess($appCredentials, $sslEnabled = true)
	{
		$google = new Google($appCredentials, false, $sslEnabled);
		return $google;
	}

	private function setAppCredentials($appCredentials)
    {
        $this->appCredentials = $appCredentials;
    }

    private function getAppCredentials()
    {
        return $this->appCredentials;
    }

	private function useDomainAccess()
	{
		$this->domainWideClient = true;
	}

	public function enableSsl()
	{
		$this->sslEnabled = true;
	}

	public function disableSsl()
	{
		$this->sslEnabled = false;
	}

	public function isSslEnabled()
	{
		return $this->sslEnabled;
	}

	public function createCalendar($userToken, $permissions = [])
	{	
		return new Calendar($this->getAppCredentials(), $userToken, $this->domainWideClient, 
			$permissions, $this->isSslEnabled());
	}

	public function createGmail($userToken, $permissions = [], $sslEnabled = false)
	{
		return new Gmail($this->getAppCredentials(), $userToken, $this->domainWideClient, 
			$permissions, $this->isSslEnabled());
	}
}