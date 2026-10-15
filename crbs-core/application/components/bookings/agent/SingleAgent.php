<?php

namespace app\components\bookings\agent;

defined('BASEPATH') OR exit('No direct script access allowed');

use app\components\bookings\exceptions\AgentException;
use app\components\bookings\Slot;
use \Bookings_model;
use Permission;


/**
 * Agent handles the creation/editing/cancellation of bookings.
 *
 */
class SingleAgent extends BaseAgent
{


	// Agent type
	const AGENT_MODE = 'single';

	// For single
	protected $period;
	protected $room;
	protected $department;
	protected $booking_user;
	protected $date_info;
	protected $datetime;
	protected $week;
	protected $recurring_dates;

	protected bool $permit_booking = false;

	private bool $constraint_applied = false;
	private ?string $constraint_message = null;



	/**
	 * Initialise the Agent with some values.
	 *
	 * Depending on the type of booking, these will be retrieved from different places.
	 *
	 */
	public function load()
	{
		$this->view = 'bookings/create/single';

		$period_id = $this->CI->input->post_get('period_id');
		if (!empty($period_id)) $this->period = $this->CI->periods_model->get($period_id);
		if ( ! $this->period) throw AgentException::forNoPeriod();

		$room_id = $this->CI->input->post_get('room_id');
		if (!empty($room_id)) {
			$this->room = $this->CI->rooms_model->get_bookable_rooms([
				'user_id' => $this->user->user_id,
				'room_id' => $room_id,
			]);
		}
		if ( ! $this->room) throw AgentException::forNoRoom();

		$date = $this->CI->input->post_get('date');
		$this->date_info = $this->CI->dates_model->get_by_date($date);
		if ( ! $this->date_info) throw AgentException::forInvalidDate();

		$this->week = $this->CI->weeks_model->get($this->date_info->week_id);
		if ( ! $this->week) throw AgentException::forNoWeek();

		$this->datetime = datetime_from_string($this->date_info->date);

		$this->session = $this->CI->sessions_model->get_by_date($this->datetime);
		if ( ! $this->session) throw AgentException::forNoSession();

		$existing_bookings = $this->CI->bookings_model->find_all_for_slot(
			$this->date_info->date,
			$this->period->period_id,
			$this->room->room_id
		);
		$room_capacity = max(1, (int) ($this->room->capacity ?? 1));
		$booked_capacity = array_sum(array_map(
			fn($booking) => max(1, (int) ($booking->capacity ?? 1)),
			$existing_bookings
		));
		$remaining_capacity = max(0, $room_capacity - $booked_capacity);
		$this->view_data['existing_bookings'] = $existing_bookings;
		$this->view_data['room_details'] = $this->CI->rooms_model->room_info($this->room);
		$this->view_data['room_photo_url'] = image_url($this->room->photo ?? null);
		$owned_bookings = array_values(array_filter(
			$existing_bookings,
			fn($booking) => (int) ($booking->user_id ?? 0) === (int) $this->user->user_id
		));
		$this->view_data['owned_bookings'] = $owned_bookings;
		$this->view_data['has_current_user_booking'] = !empty($owned_bookings);
		$this->view_data['room_capacity'] = $room_capacity;
		$this->view_data['booked_capacity'] = $booked_capacity;
		$this->view_data['remaining_capacity'] = $remaining_capacity;
		$this->view_data['default_booking_capacity'] = $existing_bookings
			? max(1, $remaining_capacity)
			: $room_capacity;
		$this->view_data['recurring_default_capacity'] = 1;
		$this->view_data['single_create_unavailable'] = $remaining_capacity < 1;
		$this->view_data['booking_slot_notice'] = !empty($owned_bookings)
			? lang('booking.capacity.owned_slot')
			: ($remaining_capacity < 1 ? lang('booking.capacity.full') : '');

		// Load schedule for the applicable room group
		//
		$schedule = $this->CI->schedules_model->get_applied_schedule($this->session->session_id, $this->room->room_group_id);
		if (!empty($schedule)) {
			$this->all_periods = $this->CI->periods_model->filtered([
				'schedule_id' => $schedule->schedule_id,
				'bookable' => 1,
			]);
		}

		// Load all rooms in the group
		//
		if ( ! empty($this->room->room_group_id)) {
			$this->all_rooms = $this->CI->rooms_model->get_bookable_rooms([
				'user_id' => $this->user->user_id,
				'room_group_id' => $this->room->room_group_id,
			]);
		}

		// Permission checks to determine other things to load and prepare
		//
		$can_book_single = has_permission(Permission::BK_SGL_CREATE, $this->room->room_id);
		$can_book_recur = has_permission(Permission::BK_RECUR_CREATE, $this->room->room_id);
		// Determine booking type automatically based one permissions, if not already set
		// Fallback to single.
		if (is_null($this->booking_type)) {
			$booking_type = ($can_book_recur && ! $can_book_single)
				? 'recurring'
				: 'single'
				;
			$this->set_booking_type($booking_type);
		}
		if (!empty($owned_bookings) || $remaining_capacity < 1) {
			$this->booking_type = self::BOOK_SINGLE;
			$this->title = !empty($owned_bookings)
				? lang('booking.capacity.owned_title')
				: lang('booking.capacity.full_title');
		}


		$this->view_data['can_book_single'] = $can_book_single;
		$this->view_data['can_book_recur'] = $can_book_recur;

		$this->view_data['can_set_department'] = false;
		$this->view_data['can_set_user'] = false;

		switch ($this->booking_type) {

			case self::BOOK_SINGLE:

				// The constraints that are checked here are only applicable to single bookings
				$this->check_constraints();

				$this->permit_booking = $can_book_single && $this->constraint_applied === false && $remaining_capacity > 0 && empty($owned_bookings);
				$this->subview = 'bookings/create/single/single_form';
				// Check permissions to load other lists
				if ($this->permit_booking) {
					if (has_permission(Permission::BK_SGL_SET_DEPT, $this->room->room_id)) {
						$this->populate_departments();
						$post_department_id = $this->CI->input->post('department_id');
						$this->view_data['can_set_department'] = true;
					}
					if (has_permission(Permission::BK_SGL_SET_USER, $this->room->room_id)) {
						$this->populate_users();
						$post_user_id = $this->CI->input->post('user_id');
						$this->view_data['can_set_user'] = true;
					}
				}
				break;

			case self::BOOK_RECUR:
				$this->permit_booking = $can_book_recur;
				$this->subview = 'bookings/create/single/recurring_defaults';
				// List of dates that a recurring booking can begin/end on.
				$this->recurring_dates = $this->CI->dates_model->get_recurring_dates($this->session->session_id, $this->date_info->week_id, $this->date_info->weekday);
				if ($this->recurring_dates === false) {
					$this->message = lang('booking.error.no_recurring_dates');
					$this->subview = 'bookings/create/single/denied';
				}

				// Check permissions to load other lists
				if ($can_book_recur) {
					if (has_permission(Permission::BK_RECUR_SET_DEPT, $this->room->room_id)) {
						$this->populate_departments();
						$post_department_id = $this->CI->input->post('department_id');
						$this->view_data['can_set_department'] = true;
					}
					if (has_permission(Permission::BK_RECUR_SET_USER, $this->room->room_id)) {
						$this->populate_users();
						$post_user_id = $this->CI->input->post('user_id');
						$this->view_data['can_set_user'] = true;
					}
				}
				break;

			default:
				$this->permit_booking = false;
				break;

		}

		if ($this->permit_booking === false && empty($owned_bookings) && $remaining_capacity > 0) {
			$this->message = $this->constraint_message;
			if ( ! is_string($this->message)) {
				$this->message = lang('booking.error.no_permission_room');
			}
			$this->subview = 'bookings/create/single/denied';
		}

		// Final check for department
		$user_department_id = $this->user->department_id;
		$department_id = $post_department_id ?? $user_department_id;
		if ( ! empty($department_id)) {
			$this->department = $this->CI->departments_model->Get($department_id);
		}

		$this->booking_user = $this->user;
		if (isset($post_user_id) && ! empty($post_user_id)) {
			$this->booking_user = $this->CI->users_model->get_by_id($post_user_id);
			unset($this->booking_user->password);
		}
	}


	private function check_constraints()
	{
		$this->constraint_applied = false;
		$this->constraint_message = null;
		$user_constraints = $this->CI->users_model->get_constraints($this->user->user_id);

		// Check for range if they can create single bookings
		$min_days = $user_constraints['range_min'];
		$max_days = $user_constraints['range_max'];

		if (is_null($min_days) && is_null($max_days)) {
			return;
		}

		$today = (new \DateTime())->setTime(0, 0, 0);

		$min_date = clone $today;
		if (!is_null($min_days)) {
			$min_date = $min_date->modify("+{$min_days} days");
			if ($this->datetime < $min_date) {
				$date_fmt = date_output_long($min_date);
				$this->constraint_applied = true;
				$line = lang('booking.error.constraint.range_min');
				$this->constraint_message = sprintf($line, $user_constraints['range_min'], $date_fmt);
				return;
			}
		}

		$max_date = clone $this->session->date_end;
		if (!is_null($max_days)) {
			$max_date = clone $today;
			$max_date->modify("+{$max_days} days");
		}

		if ($this->datetime > $max_date) {
			$date_fmt = date_output_long($max_date);
			$this->constraint_applied = true;
			$line = lang('booking.error.constraint.range_max');
			$this->constraint_message = sprintf($line, $user_constraints['range_max'], $date_fmt);
			return;
		}
	}


	/**
	 * Main vars to ensure are in the view.
	 *
	 */
	public function get_view_data()
	{
		$vars = [

			'recurring_dates' => $this->recurring_dates,
			'permit_booking' => $this->permit_booking,

			'room' => $this->room,
			'period' => $this->period,
			'department' => $this->department,
			'booking_user' => $this->booking_user,
			'date_info' => $this->date_info,
			'datetime' => $this->datetime,
			'week' => $this->week,

		];

		return $vars;
	}


	/**
	 * Process the input and state.
	 *
	 * This should return bool TRUE for success of whole process.
	 * Returning FALSE keeps the process active.
	 *
	 */
	public function process()
	{
		$action = $this->CI->input->post('action');
		if ( ! $action) return;

		switch ($action) {

			// Single booking: validate / create.
			case 'create':
				return $this->create_single_booking();
				break;

			case 'preview_recurring':
				return $this->preview_single_recurring();
				break;

			case 'create_recurring':
				return $this->create_single_recurring();
				break;
		}
	}


	/**
	 * Create a single booking.
	 *
	 */
	private function create_single_booking()
	{
		foreach ($this->view_data['existing_bookings'] as $existing_booking) {
			if ((int) ($existing_booking->user_id ?? 0) === (int) $this->user->user_id) {
				$this->message = lang('booking.capacity.owned_slot');
				return FALSE;
			}
		}

		$rules = [
			['field' => 'date', 'label' => 'lang:app.date', 'rules' => 'required|valid_date'],
			['field' => 'period_id', 'label' => 'lang:period.period', 'rules' => 'required|integer'],
			['field' => 'room_id', 'label' => 'lang:room.room', 'rules' => 'required|integer'],
			['field' => 'capacity', 'label' => 'lang:booking.capacity', 'rules' => 'required|integer|greater_than[0]'],
			['field' => 'notes', 'label' => 'lang:booking.notes', 'rules' => 'max_length[255]'],
		];

		$this->CI->load->library('form_validation');
		$this->CI->form_validation->set_rules($rules);

		if ($this->CI->form_validation->run() == FALSE) {
			$this->message = lang('app.form_error');
			return FALSE;
		}

		$booking_data = [
			'date' => $this->datetime->format('Y-m-d'),
			'notes' => $this->CI->input->post('notes'),
			'period_id' => $this->period->period_id,
			'room_id' => $this->room->room_id,
			'capacity' => (int) $this->CI->input->post('capacity'),
			'department_id' => $this->department ? $this->department->department_id : null,
			'session_id' => $this->session->session_id,
			'user_id' => $this->user->user_id,
		];

		if (has_permission(Permission::BK_SGL_SET_USER, $this->room->room_id)) {
			// Allow user to be changed
			$post_user_id = $this->CI->input->post('user_id');
			$booking_data['user_id'] = !empty($post_user_id) ? $post_user_id : null;
		}

		$booking_id = $this->CI->bookings_model->create($booking_data);

		if ($booking_id) {
			$this->success = TRUE;
			$this->message = lang('booking.success.created');
			return TRUE;
		}

		$err = $this->CI->bookings_model->get_error();

		$this->message = $err ?: lang('booking.error.not_created')
			;

		return FALSE;
	}


	/**
	 * Single recurring booking marked as recurring: show preview of generated bookings.
	 *
	 */
	private function preview_single_recurring()
	{
		$rules = [
			['field' => 'capacity', 'label' => 'lang:booking.capacity', 'rules' => 'required|integer|greater_than[0]'],
		];

		if ($this->CI->input->post('recurring_start') == 'session') {
			$rules[] = ['field' => 'recurring_start', 'label' => 'lang:booking.date_start', 'rules' => 'required'];
			$recurring_start = false;
			foreach ($this->recurring_dates as $row) {
				$dt = datetime_from_string($row->date);
				if ($dt >= $this->session->date_start) {
					$recurring_start = clone $dt;
					break;
				}
			}
		} else {
			$rules[] = ['field' => 'recurring_start', 'label' => 'lang:booking.date_start', 'rules' => 'required|valid_date'];
			$recurring_start = $this->CI->input->post('recurring_start');
		}

		if ($this->CI->input->post('recurring_end') == 'session') {
			$rules[] = ['field' => 'recurring_end', 'label' => 'lang:booking.date_end', 'rules' => 'required'];
			$recurring_end = FALSE;
			foreach (array_reverse($this->recurring_dates) as $row) {
				$dt = datetime_from_string($row->date);
				if ($dt <= $this->session->date_end) {
					$recurring_end = clone $dt;
					break;
				}
			}
		} else {
			$rules[] = ['field' => 'recurring_end', 'label' => 'lang:booking.date_end', 'rules' => 'required|valid_date'];
			$recurring_end = $this->CI->input->post('recurring_end');
		}

		// $this->data['recurring_start'] = $recurring_start;
		// $this->data['recurring_end'] = $recurring_end;

		$this->CI->load->library('form_validation');
		$this->CI->form_validation->set_rules($rules);

		if ($this->CI->form_validation->run() == FALSE) {
			$this->message = lang('app.form_error');
			return FALSE;
		}
		$booking_capacity = (int) $this->CI->input->post('capacity');
		$room_capacity = max(1, (int) ($this->room->capacity ?? 1));
		if ($booking_capacity > $room_capacity) {
			$this->message = lang('booking.capacity.invalid');
			return FALSE;
		}

		$recurring_start = datetime_from_string($recurring_start);
		$recurring_end = datetime_from_string($recurring_end);

		if ($recurring_end < $recurring_start) {
			$line = lang('booking.error.invalid_recurring_dates');
			$this->message = sprintf($line,
				date_output_long($recurring_end),
				date_output_long($recurring_start)
			);
			return FALSE;
		}


		$dates = [];
		$slots = [];

		// Generate a list of all recurring dates that the user
		// has requested to be filled, starting with the list of *all* possible
		// recurring dates.
		//
		if (is_array($this->recurring_dates)) {
			foreach ($this->recurring_dates as $row) {
				if ($row->date < $recurring_start) continue;
				if ($row->date > $recurring_end) continue;
				$date_ymd = $row->date->format('Y-m-d');
				// List of dates for booking checking
				$dates[] = $date_ymd;
				// Slot for references
				$key = Slot::generate_key($date_ymd, $this->period->period_id, $this->room->room_id);
				$slots[$key]['datetime'] = $row->date;
			}
		}

		// Get every existing reservation for each recurring date. A slot may
		// still have enough room for another reservation.
		$existing_bookings = $this->CI->bookings_model->find_all_for_dates($dates, $this->period->period_id, $this->room->room_id);

		$num_bookable = 0;
		$conflict_count = 0;
		$target_user_id = (int) ($this->booking_user->user_id ?? $this->user->user_id);

		foreach ($slots as $key => $slot) {
			$bookings = $existing_bookings[$key] ?? [];
			$booked_capacity = array_sum(array_map(
				fn($booking) => max(1, (int) ($booking->capacity ?? 1)),
				$bookings
			));
			$remaining_capacity = max(0, $room_capacity - $booked_capacity);
			$duplicate_user = false;
			foreach ($bookings as $booking) {
				$existing_user_id = (int) ($booking->user_id ?? $booking->created_by ?? 0);
				if ($existing_user_id === $target_user_id) {
					$duplicate_user = true;
					break;
				}
			}

			$can_book = !$duplicate_user && $remaining_capacity >= $booking_capacity;
			$actions = [];
			if ($can_book) {
				$actions['book'] = lang('booking.book');
				$actions['do_not_book'] = lang('booking.do_not_book');
				$num_bookable++;
			} else {
				$conflict_count++;
				if (count($bookings) === 1) {
					$actions = $this->get_actions($bookings[0]);
				} else {
					$actions['do_not_book'] = lang('booking.action.keep');
				}
			}

			$slots[$key]['bookings'] = $bookings;
			$slots[$key]['booked_capacity'] = $booked_capacity;
			$slots[$key]['remaining_capacity'] = $remaining_capacity;
			$slots[$key]['can_book'] = $can_book;
			$slots[$key]['duplicate_user'] = $duplicate_user;
			$slots[$key]['actions'] = $actions;
		}

		// Determine if the number of bookable slots is greater than constraint.
		$user_constraints = $this->CI->users_model->get_constraints($this->user->user_id);
		$max_instances = $user_constraints['recur_max_instances'];
		if ( ! is_null($max_instances) && $num_bookable > $max_instances) {
			$diff = $num_bookable - $max_instances;
			$line = lang('booking.error.too_many_instances');
			$msg = sprintf($line, $max_instances, $diff);
			$this->view_data['instances_msg'] = msgbox('exclamation large', $msg);
		} else {
			$line = lang('booking.notice.instances_to_create');
			$msg = sprintf($line, $num_bookable);
			$this->view_data['instances_msg'] = msgbox('info large', $msg);
		}

		$this->view_data['slots'] = $slots;
		$this->view_data['conflict_count'] = $conflict_count;
		$this->view_data['booking_capacity'] = $booking_capacity;
		$this->view_data['room_capacity'] = $room_capacity;

		$this->subview = 'bookings/create/single/recurring_preview';

		// $this->title = 'Preview recurring bookings';

		// $this->view = 'bookings/create/single/recurring_preview';
	}


	/**
	 * Create a single recurring booking.
	 *
	 */
	private function create_single_recurring()
	{
		$dates = $this->CI->input->post('dates');

		if (empty($dates) || !is_array($dates)) {
			$this->message = lang('booking.error.no_dates');
			return FALSE;
		}
		$booking_capacity = filter_var($this->CI->input->post('capacity'), FILTER_VALIDATE_INT);
		$room_capacity = max(1, (int) ($this->room->capacity ?? 1));
		if ($booking_capacity === FALSE || $booking_capacity < 1 || $booking_capacity > $room_capacity) {
			$this->message = lang('booking.capacity.invalid');
			return FALSE;
		}

		$allowed_dates = [];
		$recurring_start = $this->CI->input->post('recurring_start');
		$recurring_end = $this->CI->input->post('recurring_end');
		if (empty($recurring_start) || empty($recurring_end)) {
			$this->message = lang('app.form_error');
			return FALSE;
		}
		$start_bound = ($recurring_start === 'session')
			? datetime_from_string($this->session->date_start)
			: datetime_from_string($recurring_start);
		$end_bound = ($recurring_end === 'session')
			? datetime_from_string($this->session->date_end)
			: datetime_from_string($recurring_end);
		foreach ($this->recurring_dates as $row) {
			if ($row->date < $start_bound || $row->date > $end_bound) continue;
			$allowed_dates[$row->date->format('Y-m-d')] = true;
		}
		foreach (array_keys($dates) as $date) {
			if (!isset($allowed_dates[$date])) {
				$this->message = lang('app.form_error');
				return FALSE;
			}
		}
		if ($start_bound > $end_bound) {
			$this->message = lang('app.form_error');
			return FALSE;
		}

		$book_dates = [];
		foreach ($dates as $date => &$info) {
			if (!is_array($info) || !in_array($info['action'] ?? '', ['book', 'replace', 'do_not_book'], true)) {
				$info = ['action' => 'do_not_book'];
				continue;
			}
			if (in_array($info['action'], ['book', 'replace'], true)) $book_dates[] = $date;
		}
		unset($info);

		$existing_bookings = $this->CI->bookings_model->find_all_for_dates($book_dates, $this->period->period_id, $this->room->room_id);
		$target_user_id = (int) ($this->booking_user->user_id ?? $this->user->user_id);
		$capacity_skips = 0;
		foreach ($dates as $date => &$info) {
			$action = $info['action'] ?? 'do_not_book';
			if (!in_array($action, ['book', 'replace'], true)) continue;

			$key = Slot::generate_key($date, $this->period->period_id, $this->room->room_id);
			$bookings = $existing_bookings[$key] ?? [];
			$excluded_booking_id = null;
			if ($action === 'replace') {
				$replace_id = (int) ($info['replace_booking_id'] ?? 0);
				foreach ($bookings as $booking) {
					if ((int) $booking->booking_id === $replace_id) {
						$replace_actions = $this->get_actions($booking);
						if (isset($replace_actions['replace'])) $excluded_booking_id = $replace_id;
						break;
					}
				}
				if (!$excluded_booking_id) {
					$info['action'] = 'do_not_book';
					$capacity_skips++;
					continue;
				}
			}

			$remaining_capacity = $room_capacity;
			$duplicate_user = false;
			foreach ($bookings as $booking) {
				if ((int) $booking->booking_id === $excluded_booking_id) continue;
				$remaining_capacity -= max(1, (int) ($booking->capacity ?? 1));
				$existing_user_id = (int) ($booking->user_id ?? $booking->created_by ?? 0);
				if ($existing_user_id === $target_user_id) $duplicate_user = true;
			}
			if ($remaining_capacity < $booking_capacity || $duplicate_user) {
				$info['action'] = 'do_not_book';
				$capacity_skips++;
			}
		}
		unset($info);

		$user_constraints = $this->CI->users_model->get_constraints($this->user->user_id);
		$max_instances = $user_constraints['recur_max_instances'];
		if ( ! is_null($max_instances)) {
			$i = 0;
			foreach ($dates as $date => &$info) {
				if (!in_array($info['action'] ?? '', ['book', 'replace'], true)) continue;
				$i++;
				if ($i > $max_instances) {
					// Ensure we do not book once we have reached the constraint
					$info['action'] = 'do_not_book';
				}
			}
			unset($info);
		}

		$selected_count = count(array_filter($dates, fn($info) => in_array($info['action'] ?? '', ['book', 'replace'], true)));
		if ($selected_count === 0) {
			$this->message = lang('booking.capacity.recurring.none');
			return FALSE;
		}


		$repeat_data = [
			'session_id' => $this->session->session_id,
			'period_id' => $this->period->period_id,
			'room_id' => $this->room->room_id,
			'user_id' => $this->user->user_id,
			'department_id' => $this->department ? $this->department->department_id : NULL,
			'week_id' => $this->date_info->week_id,
			'weekday' => $this->date_info->weekday,
			'status' => Bookings_model::STATUS_BOOKED,
			'notes' => $this->CI->input->post('notes'),
			'capacity' => $booking_capacity,
			'dates' => $dates,
		];

		if (has_permission(Permission::BK_SGL_SET_USER, $this->room->room_id)) {
			// Allow user to be changed
			$post_user_id = $this->CI->input->post('user_id');
			$repeat_data['user_id'] = !empty($post_user_id) ? $post_user_id : null;
		}

		if (has_permission(Permission::BK_SGL_SET_DEPT, $this->room->room_id)) {
			$post_department_id = $this->CI->input->post('department_id');
			if ( ! empty($post_department_id)) {
				$repeat_data['department_id'] = $post_department_id;
			}
		}

		// bookings_repeat_model will also handle creation of individual bookings.
		$repeat_id = $this->CI->bookings_repeat_model->create($repeat_data);

		if ( ! $repeat_id) {
			$this->message = lang('booking.error.not_created');
			return FALSE;
		}

		$this->success = TRUE;
		$this->message = ($capacity_skips > 0)
			? sprintf(lang('booking.success.recurring.shared'), $selected_count, $capacity_skips)
			: sprintf(lang('booking.success.recurring.created'), $selected_count);
		return TRUE;
	}


}
