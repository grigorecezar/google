<?php namespace IndexIO\Google;

// ENUM: should extend a enum class
class Permissions
{
	const __default = self::GMAIL_READ_ONLY;

	const GMAIL = 'https://mail.google.com/';
	const GMAIL_READ_ONLY = 'https://www.googleapis.com/auth/gmail.readonly';
	const GMAIL_SEND = 'https://www.googleapis.com/auth/gmail.send';
	
	const CONTACTS_READ_WRITE = 'https://www.google.com/m8/feeds/';
	const CONTACTS_READ_ONLY = 'https://www.googleapis.com/auth/contacts.readonly';
	
	const CALENDAR = 'https://www.googleapis.com/auth/calendar';

	/*
	'https://www.googleapis.com/auth/gmail.readonly',
	'https://www.googleapis.com/auth/contacts.readonly',
	'https://www.googleapis.com/auth/gmail.modify',
	'https://www.googleapis.com/auth/gmail.compose',
	'https://www.googleapis.com/auth/gmail.send',
	'https://www.googleapis.com/auth/gmail.insert'
	*/

}