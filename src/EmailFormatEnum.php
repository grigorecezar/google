<?php namespace IndexIO\Google;

class EmailFormatEnum
{
	/**
	 * meta raw return from, to, cc, bcc, date and snippet
	 */
	const META_RAW = 'meta-raw';

	/**
	 * meta full also returns subject in addition to meta raw
	 */
	const META_FULL = 'meta-full';

	/**
	 * full also returns body in addition to meta full
	 */
	const FULL = 'full';
}