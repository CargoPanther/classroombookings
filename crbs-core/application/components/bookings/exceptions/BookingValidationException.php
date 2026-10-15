<?php

namespace app\components\bookings\exceptions;


class BookingValidationException extends \RuntimeException
{


	public static function forExistingBooking()
	{
		$line = lang('exception.BookingValidationException.forExistingBooking');
		return new static($line);
	}


	public static function forCapacityExceeded()
	{
		$line = lang('booking.capacity.invalid');
		return new static($line);
	}


	public static function forDuplicateUserBooking()
	{
		$line = lang('booking.capacity.duplicate');
		return new static($line);
	}


	public static function forHoliday()
	{
		$line = lang('exception.BookingValidationException.forHoliday');
		return new static($line);
	}


}
