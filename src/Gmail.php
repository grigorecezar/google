<?php namespace Index\Google;

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
}