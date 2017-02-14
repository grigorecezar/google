<?php namespace Index\Google;

use Google_Service_Gmail;

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
}