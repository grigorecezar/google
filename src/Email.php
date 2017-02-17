<?php namespace IndexIO\Google;

use Google_Service_Gmail_Message as GoogleServiceGmailMessage;
use Carbon\Carbon;

class Email
{
	/**
	 *
	 */
	private $format = null;

	/**
	 *
	 */
	public $from = null;

	/**
     *
     */
	public $to = [];

	/**
	 *
	 */
	public $cc = [];

	/**
	 *
	 */
	public $bcc = [];

	/**
	 *
	 */ 
	public $gmessageId = null;

	/**
	 *
	 */
	public $subject = null;

	/**
	 *
	 */
	public $snippet = '';

	/**
	 *
	 */
	public $body = null;

	/**
	 *
	 */
	private $date = '';

	public function __construct(GoogleServiceGmailMessage $message, $format = EmailFormatEnum::META_RAW)
	{
		$this->format = $format;

		$email = $this->formatGmailMessageToEmail($message);
		
		$this->from = $email['from'];
		$this->to = $email['to'];
		$this->cc = $email['cc'];
		$this->bcc = $email['bcc'];
		$this->gmessageId = $email['gmessage_id'];
		$this->subject = $email['subject'];
		$this->snippet = $email['snippet'];
		$this->body = $email['body'];

		$this->setDate(new Carbon($email['date']));
	}

	public function getDate() : Carbon 
	{
		return $this->date;
	}

	public function setDate(Carbon $date)
	{	
		$this->date = $date;
		return $this;
	}

	public function __toArray()
	{
		return [
			'from' => $this->from,
			'to' => $this->to,
			'cc' => $this->cc,
			'bcc' => $this->bcc,
			'gmessage_id' => $this->gmessageId,
			'subject' => $this->subject,
			'snippet' => $this->snippet,
			'body' => $this->body,
			'date' => $this->date
		];
	}

	private function formatGmailMessageToEmail($message)
	{
		// the format we expect back
		$email = [
			'from' => null,
			'to' => [],
			'cc' => [],
			'bcc' => [],
			'gmessage_id' => null,
			'subject' => null,
			'snippet' => null,
			'body' => null,
			'date' => null
		];

		if( !isset($message['modelData']) || 
			!isset($message['modelData']['payload']) || 
			!isset($message['modelData']['payload']['headers']) ) {
			throw new GoogleException('Model data from google does not contain headers');
		}

		// transform headers to array
		$email = $this->decodeHeaders($message['modelData']['payload']['headers']);
		$email['gmessage_id'] = $message->getId();

		// if email from is null or no to messages then continue
    	if(!$email['from']['email'] || !$email['to'] || !$email['date'] || $email['date'] === '00:00:00 00:00:00') {
    		return ;
    	}

    	// saving the snippet
		$email['snippet'] = $message->getSnippet();

		// saving the body
		$email['body'] = $this->parseBodyEmail($message);

		return $email;
	}

	// getting the body, decoding it and saving it
	// we try both ways of pulling the body,
	// observations and testing have seen that one way might not work but the other does
	private function parseBodyEmail($message)
	{
		$firstBodyPullTry = false;

		$parts = $message->getPayload()->getParts();
		if(isset($parts[0]) && isset($parts[0]['body'])) {
			$body = $parts[0]['body'];

			if(isset($body->data) && $body->data) {
				$rawData = $body->data;
				$firstBodyPullTry = true;
			}
		}

		if(!$firstBodyPullTry) {
			if(!$message->getPayload()->getBody()) {
				return null;
			} 

			$rawData = $message->getPayload()->getBody()->getData();
		}

        $sanitizedData = strtr($rawData,'-_', '+/');
        $decodedMessage = base64_decode($sanitizedData);

        return $decodedMessage;
	}

	private function parseHeader($header)
	{
		$name = '';
		if(strpos($header, '<')) {
			$name = explode('<', $header);
			$name = trim($name[0]);
		}
		preg_match_all('/\b[A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,6}\b/i', $header, $result, PREG_PATTERN_ORDER);
		
		if(! isset($result[0][0])) return null;

		return ['name' => $name, 'email' => $result[0][0]];
	}

	private function decodeHeaders($headers)
	{
		$row['from']['name'] = '';
		$row['from']['email'] = '';
		$row['to'] = [];
		$row['cc'] = [];
		$row['bcc'] = [];
		$row['date'] = '';
		$row['subject'] = '';

		$headersRow = [];

		foreach ($headers as $value) {
			$headerName = $value['name'];
			$headerValue = $value['value'];
			
			$headersRow[][$headerName] = $headerValue;
					
			switch ($headerName) {
				case 'From':
					$headerResult = $this->parseHeader($headerValue);
					$row['from'] = $headerResult;
					break;
				
				case 'Date':
					$row['date'] = date('Y-m-d H:i:s', strtotime($headerValue));
					break;

				case 'To':
					$headerValues = explode(',', $headerValue);
					$headerRow = [];
					foreach ($headerValues as $headerValue) {
						$headerResult = $this->parseHeader($headerValue);
						if($headerResult) $headerRow[] = $headerResult;
					}
					$row['to'] = $headerRow;
					break;
				
				case 'Bcc':
					$headerValues = explode(',', $headerValue);
					$headerRow = [];
					foreach ($headerValues as $headerValue) {
						$headerResult = $this->parseHeader($headerValue);
						if($headerResult) $headerRow[] = $headerResult;
					}
					$row['bcc'] = $headerRow;
					break;

				case 'Cc':
					$headerValues = explode(',', $headerValue);
					$headerRow = [];
					foreach ($headerValues as $headerValue) {
						$headerResult = $this->parseHeader($headerValue);
						if($headerResult) $headerRow[] = $headerResult;
					}
					$row['cc'] = $headerRow;
					break;

				case 'Subject': 
					$row['subject'] = $headerValue;
					break;

				default:
					break;
			}		
		}

		return $row;
	}
}