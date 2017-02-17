<?php namespace IndexIO\Google;

use Google_Service_Gmail;
use Google_Service_Exception;

use Google_Service_Gmail_WatchRequest;

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\Exception\NotFoundException;

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
	public function getEmailById($id, $format = EmailFormatEnum::META_RAW)
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
	public function getEmails($maxResults, $format = EmailFormatEnum::META_RAW)
	{
		$params = $this->formatParamsForEmailPulling(null, null, $maxResults);

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
				throw $e;
			} catch (Exception $e) {
				$errors ++;
			}
		} while ($pageToken && $countProcessedEmails > 0 && $countProcessedEmails < $maxResults);

		return $emails;
	}

	private function formatParamsForEmailPulling($startDate = null, $endDate = null, $maxResults = null)
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

		return $params;
	}

	private function getEmailsAndNextToken($params, &$countProcessedEmails)
	{
		$messagesResponse = $this->getClient()->users_messages->listUsersMessages('me', $params);
		if(! $messagesResponse->getMessages()) {
			return [[], null];
		}

		foreach ($messagesResponse->getMessages() as $message) {
			$emails[] = $this->getEmailById($message->getId(), EmailFormatEnum::META_RAW);

			$countProcessedEmails ++;
			if($countProcessedEmails === $params['maxResults']) {
				return [$emails, null];
			}
		}

		$pageToken = $messagesResponse->getNextPageToken();
		return [$emails, $pageToken];
	}

	public function getMessagesIds($startDate = null, $endDate = null, $to = [], $from = [], $maxResults = 2500) 
	{
		// TO label unread use:
		/*
		 $params['q'] .= 'label:Unread';
		 or is:unread
		 // if the labels contain a label called 'SENT' that means the email was sent from the user
			if(is_numeric(array_search('SENT', $message->getLabelIds()))){
				$data['direction'] = 'FROM';
			}
		*/
		$params = [];
		$params['q'] = '';
		
		// remove chat from messages
		$params ['q'] .= ' -in:chats ';

		// set start date; this has to be epoch; ie. after:2015/9/15; how to: date('Y/m/d', strtotime($startDate))
		if($startDate) {
			$params['q'] .= ' after:' . $startDate . ' ';
		}

		// set end date; this has to be epoch; ie. after:2015/9/15; how to: date('Y/m/d', strtotime($endDate))
		if($endDate) {
			$params['q'] .= ' before:' . $endDate . ' ';
		}

		
		// the {} indicate that is to:liviu@index.io OR to:tom@index.io
		// we add all the to OR the from
		$params['q'] .= ' {';
		foreach ($to as $value) {
			$params['q'] .= ' to: ' . $value . ' ';
		}
		foreach ($from as $value) {
			$params['q'] .= ' from:' . $value . ' ';
		}
		$params['q'] .= '} ';

		if($maxResults && is_numeric($maxResults)){
			$params['maxResults'] = $maxResults;
		}

		$pageToken = null;
		$countMessages = 1;
		$errors = 0;
		$ids = [];
		$countTotalMessages = 0;
		do {
			try {
				if ($pageToken) {
					$params['pageToken'] = $pageToken;
				}
				$messagesResponse = $this->getClient()->users_messages->listUsersMessages('me', $params);
				
				$countMessages = count($messagesResponse->getMessages());

				if ($messagesResponse->getMessages()) {
					foreach ($messagesResponse->getMessages() as $message) {
						echo $countTotalMessages . ') ' . 'Message id: ' . $message->getId() . "\n";
						$ids[] = $message->getId();
						$countTotalMessages ++;
						if($countTotalMessages === $maxResults) {
							break;
						}
					}
					$pageToken = $messagesResponse->getNextPageToken();
				}
			} catch(Google_Auth_Exception $e){
				throw $e;
			} catch (Exception $e) {
				// echo 'An error occurred: ' . $e->getMessage() . "\n";
				$errors ++;
			}
		} while ($pageToken && $countMessages > 0 && $countTotalMessages < $maxResults);
		
		$total = count($ids);
		if($total === 0 && $errors === 0){
			return $ids;
		}elseif($total === 0){
			throw new Exception('Too many errors, re-try later.', 500);
		}

		// we can afford an error rate lower than 1%
		// if the total number of errors is bigger than 1 percent, re-try the task later
		if( ($errors / $total) * 100 > 1) {
			throw new Exception('Too many errors, re-try later.', 500);
		}

		return $ids;
	}

	public function createTopic($projectId, $topicName)
	{
		$pubsub = new PubSubClient([
			'projectId' => $projectId
		]);

		$topic = $pubsub->createTopic($topicName);

		return $topic;
	}

	function createSubscription($projectId, $topicName, $subscriptionName, $pushUrl = null)
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

	function getTopic($projectId, $topicName)
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

	function getSubscription($projectId, $topicName, $subscriptionName)
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

	function listTopics($projectId)
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

	function listSubscriptions($projectId)
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

	function deleteTopic($projectId, $topicName)
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

	function deleteSubscription($projectId, $subscriptionName)
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

	function modifyPushConfig($projectId, $topicName, $subscriptionName, $pushUrl = '')
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

	/**
	 * @param [] the gmail messages coming from google
	 * @return Expected format back:
	 * [
	 *		'from' => null,
	 *		'to' => [],
	 *		'cc' => [],
	 *		'bcc' => [],
	 *		'gmessage_id' => null,
	 *		'subject' => null,
	 *		'snippet' => null,
	 *		'body' => null,
	 *		'date' => null
	 *	];
	 */
	private function formatGmailMessagesToEmails($messages)
	{
		$emails = [];

		// lets start looping through the messages and format them
		foreach ($messages as $message) {
			if($value instanceof Google_Service_Exception) {
				continue;
			}

			$emails[] = new Email($message);
		}

		return $emails;
	}
}