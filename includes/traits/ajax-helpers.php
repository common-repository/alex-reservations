<?php

trait Alexr_ajax_helpers {

	protected function getBookingsYearMonth($tenantId, $year_month)
	{
		$bookings_year_month = \Alexr\Models\Booking::where('restaurant_id', $tenantId)
		                              ->where('date', 'like', $year_month)
		                              ->where('status', '!=', \Alexr\Enums\BookingStatus::SELECTED)
		                              ->where('status', '!=', \Alexr\Enums\BookingStatus::DELETED)
		                              ->orderBy('date', 'asc')
		                              ->get()
		                              ->map(function($booking){ return intval($booking->id); })
		                              ->toArray();
		return $bookings_year_month;
	}

	protected function calculateResultsSlotsAvailable($date = null)
	{
		$date = $date ?? $this->date;

		//ray('Calculando slots para el dia: ' . $date);
		// Search for 1 service or for all services
		if ($this->service_id != 'all') {
			$result = $this->get_service_slots_OneService($date);
		}
		else {
			$result = $this->get_service_slots_AllServices($date);
		}

		// If date is closed has no slots available
		if ($this->restaurant->isDateClosed($date)){
			$result['resultDate']['slots'] = [];
		}

		$is_available = false;
		foreach ($result['resultDate']['slots'] as $slot) {
			if (isset($slot['available']) && $slot['available'] === true) {
				$is_available = true;
			}
		}
		$result['is_available'] = $is_available;

		return $result;
	}

	protected function get_service_slots_OneService($date = null)
	{
		$date = $date ?? $this->date;

		// Slots available
		$list_slots = [];
		$available_slots = $this->service->getAllSlotsAvailable($this->guests, $date);
		$all_slots = $this->service->getAllBookableSlots($date);

		// Additional data to slot
		$duration = $this->service->getDuration($this->guests);

		foreach($all_slots as $slot)
		{
			$list_slots[] = [
				'available' => in_array($slot, $available_slots),
				'name' => $this->service->name,
				'translations' => $this->service->translations,
				'id' => $this->service->id,
				'date' => $date,
				'time' => $slot,
				'duration' => $duration,
				'backcolor' => $this->service->color,
				'textcolor' => $this->service->color_text
			];
		}

		// Check service has not been blocked
		if (!$this->restaurant->isOnlineBookingsEnabled($this->service->id, $date)){
			$list_slots = [];
		}

		// Days available
		$daysAvailable = $this->service->availableDates($this->restaurant->timezone, $this->days_in_advance);

		$result = [
			'guests_min' => $this->service->min_covers_reservation,
			'guests_max' => $this->service->max_covers_reservation,
			'daysAvailable' => $daysAvailable,
			'resultDate' => [
				'date' => $date,
				'slots' => $list_slots
			],
		];

		return $result;
	}

	protected function get_service_slots_AllServices($date = null)
	{
		$date = $date ?? $this->date;

		$services = $this->restaurant->getShiftsAndEvents($this->widget_id);
		$result = [];

		$list_results = [];
		foreach($services as $service) {
			if ($service instanceof \Alexr\Settings\Shift || $service instanceof \Alexr\Settings\Event) {
				$this->service = $service;  // Temporalmente asignamos el servicio actual
				$results = $this->get_service_slots_OneService($date);
				$list_results[] = $results;
			}
		}

		// Merge all results: guests_min, guests_max, daysAvailable, resultDate[slots]
		$result['guests_min'] = 1000;
		$result['guests_max'] = 1;
		$result['daysAvailable'] = [];
		$result['resultDate'] = [
			'date' => $date,
			'slots' => []
		];

		foreach($list_results as $item)
		{
			if ($item['guests_min'] < $result['guests_min']) {
				$result['guests_min'] = $item['guests_min'];
			}

			if ($item['guests_max'] > $result['guests_max']) {
				$result['guests_max'] = $item['guests_max'];
			}

			foreach($item['daysAvailable'] as $dayAvailable) {
				if (!in_array($dayAvailable, $result['daysAvailable'])) {
					$result['daysAvailable'][] = $dayAvailable;
				}
			}

			foreach($item['resultDate']['slots'] as $slot) {
				$result['resultDate']['slots'][] = $slot;
			}
		}

		return $result;
	}

	protected function findAlternativeDates($requested_date, $date_today, $days_before, $days_after, $search_interval_before, $search_interval_after)
	{
		$requested_date = new DateTime($requested_date);
		$date_today = new DateTime($date_today);

		$before_dates_result = [];
		$after_dates_result = [];

		// Buscar fechas antes de la solicitada
		for ($i = 1; $i <= $search_interval_before; $i++) {
			$new_date = clone $requested_date;
			$new_date->modify("-$i day");

			if ($new_date >= $date_today) {
				//ray('ALTERNATIVE DATE BEFORE: ' . $new_date->format('Y-m-d'));
				$result_new_date = $this->calculateResultsSlotsAvailable($new_date->format('Y-m-d'));

				if ($result_new_date['is_available'] === true) {
					$before_dates_result[] = $result_new_date['resultDate'];
					if (count($before_dates_result) == $days_before) break;
				}
			}
		}

		// Buscar fechas después de la solicitada
		for ($i = 1; $i <= $search_interval_after; $i++) {
			$new_date = clone $requested_date;
			$new_date->modify("+$i day");

			//ray('ALTERNATIVE DATE AFTER: ' . $new_date->format('Y-m-d'));
			$result_new_date = $this->calculateResultsSlotsAvailable($new_date->format('Y-m-d'));

			if ($result_new_date['is_available'] === true) {
				$after_dates_result[] = $result_new_date['resultDate'];
				if (count($after_dates_result) == $days_after) break;
			}
		}

		return array_merge($before_dates_result, $after_dates_result);
	}
}
