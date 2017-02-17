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
	private function __construct($appCredentials = null, $useDomainAccess = false)
	{
		if($appCredentials) {
			$this->setAppCredentials($appCredentials);
		}

		if($useDomainAccess) {
			$this->useDomainAccess();
		}
	}

	public static function createForDomainWideAccess($appCredentials)
	{
		$google = new Google($appCredentials, true);
		return $google;
	}

	public static function createForIndividualAccess($appCredentials)
	{
		$google = new Google($appCredentials);
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

	public function createCalendar($userToken, $permissions = [])
	{	
		return new Calendar($this->getAppCredentials(), $userToken, $this->domainWideClient, $permissions);
	}

	public function createGmail($userToken, $permissions = [])
	{
		return new Gmail($this->getAppCredentials(), $userToken, $this->domainWideClient, $permissions);
	}
}