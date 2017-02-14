<?php namespace Index\Google;

use Google_Service_Calendar;

class Calendar extends AbstractGoogle
{
	const PERMISSIONS = [
		Permissions::CALENDAR
	];

	protected function setClient()
	{
		$this->client = new Google_Service_Calendar($this->getGoogleClient());
	}

	public function getCalendarEvents()
	{
		$client = $this->getClient();
		$events = $client->events;

		try {
			$params = [
//				'minAttendees' => 1,
//				'maxResults' => 3,
//				'orderBy' => 'startTime',
//				'timeMin' => (new \DateTime('-2 weeks'))->format(\DateTime::ATOM),
				'timeMax' => (new \DateTime('now'))->format(\DateTime::ATOM)
			];

			$eventsList = $events->listEvents('primary', $params);
		} catch(Google_Service_Exception $e){
			return null;
		}

		$allEvents = [];
		foreach ($eventsList as $value) {
			$allEvents[] = $this->formatEvent($value);
		}

		return $allEvents;
	}

	/**
	 * Dates are coming from google in ISO 8601 format and we transform them into UTC date time
	 */
	private function formatEvent($event)
	{
		$id = $event->getId();
		$summary = $event->getSummary();
		$description = $event->getDescription();

		$createdAt = strtotime($event->getCreated());
		$createdAt = date('Y-m-d H:i:s', $createdAt);

		if($event->getStart() === null) {
			throw new InfrastructureException('No start for the event.');
		}

		$when = strtotime($event->getStart()->getDateTime());
		$when = date('Y-m-d H:i:s', $when);

		$attendees = $event->getAttendees();
		$attendeesEmails = [];
		if($attendees) {
		    foreach ($attendees as $attendee) {
		        $attendeesEmails[] = $attendee->getEmail();
		    }
		}

		$recurring = false;
		if($event->getRecurrence()) {
			$recurring = true;
		}

		return [
			'google_id' => $id,
			'recurring'=> $recurring,
			'summary' => $summary,
			'description' => $description,
			'created_at' => $createdAt,
			'when' => $when,
			'original_google_date' => $event->getStart()->getDateTime(),
			'attendees' => $attendeesEmails
		];
	}
}