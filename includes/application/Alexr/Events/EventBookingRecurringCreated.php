<?php

namespace Alexr\Events;

use Alexr\Models\Booking;
use Alexr\Models\BookingRecurring;
use Alexr\Models\User;

class EventBookingRecurringCreated
{
	public $user;
	public $booking;
	public $data;

	public function __construct(Booking $booking, $data, User $user = null)
	{
		$this->user = $user;
		$this->booking = $booking;
		$this->data = $data;
	}
}
