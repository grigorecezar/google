<?php namespace IndexIO\Google;

use Google_Service_Gmail;
use Google_Service_Exception;

use Google_Service_Gmail_WatchRequest;

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\Exception\NotFoundException;

use Carbon\Carbon;

class Gmail extends AbstractGoogle
{
	const PERMISSIONS = [
		Permissions::GMAIL
	];

	protected function setClient()
	{
		$this->client = new Google_Service_Gmail($this->getGoogleClient());
	}

	/**
	 * Returns an email from google by id in a specific format
	 *
	 * @param gmessage_id retrieved from google
	 * @param EMAIL_FORMAT found as constants in this class - e types: meta-raw, meta-full, full
	 */
	public function getEmailById($id, $format = EmailFormatEnum::META_RAW) : Email
	{
		switch ($format) {
			case EmailFormatEnum::META_RAW:
				$options = [
					'format' => 'metadata',
					'metadataHeaders' => ['From', 'To', 'Cc', 'Bcc', 'Date']
				];
				break;

			case EmailFormatEnum::META_FULL:
				$options = [
					'format' => 'metadata'
				];
				break;

			case EmailFormatEnum::FULL:
				$options = [
					'format' => 'full'
				];
				break;

			default:
				throw new GoogleException('The format provided does not exist.');
		}

		return new Email($this->getClient()->users_messages->get('me', $id, $options), $format);
	}

	/**
	 * Gets last $results emails associated with this account. Specific format can be requested
	 * 
	 * @param int $results
	 * @param IndexIO\Google\EmailFormatEnum $format
	 */
	public function getLastEmails($results = 100, $format = EmailFormatEnum::META_RAW)
	{
		return $this->getEmails(null, null, $results, $format);
	}

	/**
	 * Gets emails associated with this account in a specific time interval. 
	 * The dates passed will be considered but only as days, eg. if startDate is
	 * 2017/02/17 18:24:00 then the function will consider all the emails that have been
	 * sent / received on the 2017/02/17 onwards, time is not being taken into consideration.
	 *
	 * @param int $results
	 * @param IndexIO\Google\EmailFormatEnum $format
	 */
	public function getEmailsInInterval(Carbon $startDate, Carbon $endDate = null, $format = EmailFormatEnum::META_RAW)
	{
		return $this->getEmails($startDate, $endDate, null, $format);
	}

	/**
	 * Ability to pull emails from the gmail account associated in a specific format.
	 * The method will return an array of Email object. You can pass an interval (start date, end date)
	 * for which to pull the emails with a max results. 
	 * Eg. if you want to extract emails from my account between January and March 2017
	 * pass start date as 1 January, end date 30 March
	 *
	 * @param Carbon\Carbon $startDate
	 * @param Carbon\Carbon $endDate
	 * @param int $maxResults
	 * @param IndexIO\Google\EmailFormatEnum $format
	 * @return array(IndexIO\Google\Email) 
	 */
	protected function getEmails(Carbon $startDate = null, Carbon $endDate = null, 
		$maxResults = null, $format = EmailFormatEnum::META_RAW)
	{
		$params = $this->formatParamsForEmailPulling($startDate, $endDate, $maxResults);

		$pageToken = null;
		$emails = [];
		$countProcessedEmails = 0;
		do {
			try {
				if ($pageToken) {
					$params['pageToken'] = $pageToken;
				}

				list($newEmailsSet, $pageToken) = $this->getEmailsAndNextToken($params, $countProcessedEmails, $format);
				$emails = array_merge($emails, $newEmailsSet);

			} catch(Google_Auth_Exception $e){
				// TODO
			} catch (Exception $e) {
				// TODO
			}
		} while ($pageToken && $countProcessedEmails > 0 && $countProcessedEmails < $maxResults);

		return $emails;
	}

	private function formatParamsForEmailPulling(Carbon $startDate = null, Carbon $endDate = null, $maxResults = null)
	{
		// if max results is not set pull all emails
		// be aware of memory restraints of your machine if using
		if($maxResults === null) {
			$maxResults = 100000000;
		}

		$params = [
			'q' => ' -in:chats ',
			'maxResults' => $maxResults
		];

		// set start date; this has to be epoch; ie. after:2015/9/15; how to: date('Y/m/d', strtotime($startDate))
		if($startDate) {
			$params['q'] .= ' after:' . date('Y/m/d', strtotime($startDate)) . ' ';
		}

		// set end date; this has to be epoch; ie. after:2015/9/15; how to: date('Y/m/d', strtotime($endDate))
		if($endDate) {
			$params['q'] .= ' before:' . date('Y/m/d', strtotime($endDate)) . ' ';
		}

		return $params;
	}

	private function getEmailsAndNextToken($params, &$countProcessedEmails)
	{
		$messagesResponse = $this->getClient()->users_messages->listUsersMessages('me', $params);
		if(! $messagesResponse->getMessages()) {
			return [[], null];
		}

		foreach ($messagesResponse->getMessages() as $message) {
			try {
				$emails[] = $this->getEmailById($message->getId(), EmailFormatEnum::META_RAW);
			} catch (GoogleException $e) {
				// ignore for now
				continue;
			}

			$countProcessedEmails ++;
			if($countProcessedEmails === $params['maxResults']) {
				return [$emails, null];
			}
		}

		$pageToken = $messagesResponse->getNextPageToken();
		return [$emails, $pageToken];
	}

	public function createTopic($projectId, $topicName)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId
		]);

		$topic = $pubsub->createTopic($topicName);

		return $topic;
	}

	public function createSubscription($projectId, $topicName, $subscriptionName, $pushUrl = null)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		$topic = $pubsub->topic($topicName);
		if(!$topic){
			return null;
		}
		try {
			$topicInfo = $topic->info();
		} catch(NotFoundException $e){
			return null;
		}

		$subscription = $topic->subscription($subscriptionName);

		if($pushUrl){
			// create push notification
			$subscription->create([
				'endpoint' => $pushUrl
			]);
		} else {
			// create pull notification
			$subscription->create();
		}

		return $subscription;
	}

	public function getTopic($projectId, $topicName)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		$topic = $pubsub->topic($topicName);
		if(!$topic){
			return null;
		}
		try {
			$topicInfo = $topic->info();
		} catch(NotFoundException $e){
			return null;
		}

		return $topic;
	}

	public function getSubscription($projectId, $topicName, $subscriptionName)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		$topic = $pubsub->topic($topicName);
		if(!$topic){
			return null;
		}
		try {
			$topicInfo = $topic->info();
		} catch(NotFoundException $e){
			return null;
		}

		$subscription = $topic->subscription($subscriptionName);
		if(!$subscription){
			return null;
		}

		try {
			$subscriptionInfo = $subscription->info();
		} catch(NotFoundException $e){
			return null;
		}

		return $subscription;
	}

	public function listTopics($projectId)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		try {
			$topics = [];
			foreach ($pubsub->topics() as $topic) {
				$topics[] = $topic;
			}
		} catch(\Exception $e){
			return [];
		}

		return $topics;
	}

	public function listSubscriptions($projectId)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		try {
			$subscriptions = [];
			foreach ($pubsub->subscriptions() as $subscription) {
				$subscriptions[] = $subscription;
			}
		} catch(\Exception $e){
			return [];
		}

		return $subscriptions;
	}

	public function deleteTopic($projectId, $topicName)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		$topic = $pubsub->topic($topicName);
		if(!$topic){
			return;
		}

		try {
			$info = $topic->info();
		} catch(NotFoundException $e){
			return;
		}

		$topic->delete();
	}

	public function deleteSubscription($projectId, $subscriptionName)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId,
		]);

		$subscription = $pubsub->subscription($subscriptionName);
		if(!$subscription){
			return;
		}

		try {
			$info = $subscription->info();
		} catch(NotFoundException $e){
			return;
		}

		$subscription->delete();
	}

	public function modifyPushConfig($projectId, $topicName, $subscriptionName, $pushUrl = '')
	{
		$subscription = $this->getSubscription($projectId, $topicName, $subscriptionName);
		if(!$subscription){
			return;
		}

		$subscription->modifyPushConfig([
			'pushEndpoint' => $pushUrl
		]);
	}

	public function watch($projectId, $topicName)
	{
		$watchRequest = new Google_Service_Gmail_WatchRequest();
		$watchRequest->setTopicName('projects/' . $projectId . '/topics/' . $topicName);
		$watchRequest->setLabelIds(['UNREAD']);

		$watchEvent = $this->getClient()->users->watch('me', $watchRequest);

		return $watchEvent;
	}

	public function stopWatch()
	{
		$this->getClient()->users->stop('me');
	}

	public function getLastMessage($historyId)
	{
		try {
			$realMessage = null;

			// we only look at the UNREAD labels
			$params = [
				'startHistoryId' => $historyId,
				'labelId' => 'UNREAD'
			];

			$gmailHistory = $this->getClient()->users_history->listUsersHistory('me', $params);
			$history = $gmailHistory->getHistory();

			$historyCount = count($history);
			if($historyCount < 1){
				return null;
			}

			// take the last history item, the last history change
			$lastHistoryItem = $history[$historyCount - 1];

			$messagesCount = count($lastHistoryItem->getMessages());
			if($messagesCount < 1){
				return null;
			}

			// take the last message from the last history change
			$lastMessage = $lastHistoryItem->getMessages()[$messagesCount - 1];

			$realMessage = $this->getClient()->users_messages->get('me', $lastMessage->getId(), [
				'format' => 'FULL',
				'metadataHeaders' => ['From', 'To', 'Cc', 'Bcc', 'Date']
			]);

			return $realMessage;

		} catch(Google_Service_Exception $e){
			return null;
		}
	}
}