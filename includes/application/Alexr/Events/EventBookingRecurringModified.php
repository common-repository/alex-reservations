<?php

namespace Alexr\Events;

use Alexr\Models\Booking;
use Alexr\Models\BookingRecurring;
use Alexr\Models\User;

class EventBookingRecurringModified
{
	public $user;
	public $booking;
	public $original_data;
	public $data;

	public function __construct(Booking $booking, $data, $original_data, User $user = null)
	{
		$this->user = $user;
		$this->booking = $booking;
		$this->data = $data;
		$this->original_data = $original_data;
	}
}
