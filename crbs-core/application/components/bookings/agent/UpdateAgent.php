<?php

namespace app\components\bookings\agent;

defined('BASEPATH') OR exit('No direct script access allowed');


use app\components\bookings\exceptions\AgentException;
use Permission;


/**
 * Agent handles the editing of a booking.
 *
 */
class UpdateAgent extends BaseAgent
{


	// Agent type
	const TYPE = 'update';

	// Features that can be changed
	const FEATURE_DATE = 'date';
	const FEATURE_PERIOD = 'period';
	const FEATURE_ROOM = 'room';
	const FEATURE_DEPARTMENT = 'department';
	const FEATURE_VIEW_USER = 'view_user';
	const FEATURE_EDIT_USER = 'edit_user';
	const FEATURE_VIEW_NOTES = 'view_notes';
	const FEATURE_EDIT_NOTES = 'edit_notes';
	const FEATURE_CAPACITY = 'capacity';

	// Edit modes
	const EDIT_ONE = '1';
	const EDIT_FUTURE = 'future';
	const EDIT_ALL = 'all';


	// Booking being edited
	private $booking;

	// Edit mode
	private $edit_mode;

	// Features
	private $features = [];


	/**
	 * Initialise the Agent with some values.
	 *
	 * Depending on the type of booking, these will be retrieved from different places.
	 *
	 */
	public function load()
	{
		// Load booking that is being edited
		$booking_id = $this->CI->input->post_get('booking_id');
		$includes = [
			'week',
			'period',
			'room',
			'department',
			'user',
		];
		if (!empty($booking_id)) $this->booking = $this->CI->bookings_model->include($includes)->get($booking_id);
		if ( ! $this->booking) throw AgentException::forNoBooking();

		if ( ! booking_editable($this->booking)) throw AgentException::forAccessDenied();

		// Get session of booking
		$this->session = $this->CI->sessions_model->get($this->booking->session_id);
		if ( ! $this->session) throw AgentException::forNoSession();

		$schedule = $this->CI->schedules_model->get_applied_schedule($this->session->session_id, $this->booking->room->room_group_id);

		if (!empty($schedule)) {

			// Load the list of available periods and rooms
			$this->all_periods = (array) $this->CI->periods_model->filtered([
				'schedule_id' => $schedule->schedule_id,
				'bookable' => 1,
			]);

			if (!empty($this->booking->room->room_group_id)) {
				$this->all_rooms = (array) $this->CI->rooms_model->get_bookable_rooms([
					'user_id' => $this->user->user_id,
					'room_group_id' => $this->booking->room->room_group_id,
				]);
			}
		}


		// Get edit mode.
		// This flag helps determine what fields can be edited (important for recurring bookings selection)
		// Options are single, future, or all.
		$this->edit_mode = $this->CI->input->post_get('edit') ?: self::EDIT_ONE;
		if ( ! in_array($this->edit_mode, [self::EDIT_ONE, self::EDIT_FUTURE, self::EDIT_ALL], TRUE)
			|| ($this->edit_mode !== self::EDIT_ONE && empty($this->booking->repeat_id))) {
			throw AgentException::forAccessDenied();
		}

		$is_booking_owner = ($this->booking->user_id == $this->user->user_id);
		if ($this->booking->repeat_id) {
			$can_view_notes = has_permission(Permission::BK_RECUR_VIEW_OTHER_NOTES, $this->booking->room_id);
			$can_view_user = has_permission(Permission::BK_RECUR_VIEW_OTHER_USERS, $this->booking->room_id);
			$can_set_user = has_permission(Permission::BK_RECUR_SET_USER, $this->booking->room_id);
			$can_edit_other = has_permission(Permission::BK_RECUR_EDIT_OTHER, $this->booking->room_id);
			$can_set_departent = has_permission(Permission::BK_RECUR_SET_DEPT, $this->booking->room_id);
		} else {
			$can_view_notes = has_permission(Permission::BK_SGL_VIEW_OTHER_NOTES, $this->booking->room_id);
			$can_view_user = has_permission(Permission::BK_SGL_VIEW_OTHER_USERS, $this->booking->room_id);
			$can_set_user = has_permission(Permission::BK_SGL_SET_USER, $this->booking->room_id);
			$can_edit_other = has_permission(Permission::BK_SGL_EDIT_OTHER, $this->booking->room_id);
			$can_set_departent = has_permission(Permission::BK_SGL_SET_DEPT, $this->booking->room_id);
		}

		if ($can_set_user) {
			$this->populate_users();
		}

		if ($can_set_departent) {
			$this->populate_departments();
		}

		$this->features = [
			self::FEATURE_DATE => $is_booking_owner || $can_edit_other,
			self::FEATURE_PERIOD => $is_booking_owner || $can_edit_other,
			self::FEATURE_ROOM => $is_booking_owner || $can_edit_other,
			self::FEATURE_VIEW_NOTES => $is_booking_owner || $can_view_notes,
			self::FEATURE_EDIT_NOTES => $is_booking_owner || ($can_view_notes && $can_edit_other),
			self::FEATURE_DEPARTMENT => $can_set_departent,
			self::FEATURE_VIEW_USER => $is_booking_owner || $can_view_user,
			self::FEATURE_EDIT_USER => ($can_view_user && $can_set_user),
			self::FEATURE_CAPACITY => ($is_booking_owner || $can_edit_other) && $this->edit_mode === self::EDIT_ONE,
		];

		// If a recurring booking future or all is being edited, then it can't be moved in time or space.
		if ($this->booking->repeat_id) {
			if (in_array($this->edit_mode, [self::EDIT_FUTURE, self::EDIT_ALL])) {
				$this->features[self::FEATURE_DATE] = false;
				$this->features[self::FEATURE_PERIOD] = false;
				$this->features[self::FEATURE_ROOM] = false;
			}
		}

		$this->handle_edit();
	}


	private function handle_edit()
	{
		$this->view = 'bookings/edit/form';
		$this->title = lang('booking.edit.title');

		if ($this->CI->input->post()) {
			$this->process_edit_booking();
		}
	}


	/**
	 * Main vars to ensure are in the view.
	 *
	 */
	public function get_view_data()
	{
		$room_capacity = max(1, (int) ($this->booking->room->capacity ?? 1));
		$slot_bookings = $this->CI->bookings_model->find_all_for_slot(
			$this->booking->date->format('Y-m-d'),
			$this->booking->period_id,
			$this->booking->room_id
		);
		$other_booked_capacity = 0;
		foreach ($slot_bookings as $slot_booking) {
			if ((int) $slot_booking->booking_id === (int) $this->booking->booking_id) continue;
			$other_booked_capacity += max(1, (int) ($slot_booking->capacity ?? 1));
		}
		$capacity_max = max(1, $room_capacity - $other_booked_capacity);

		$vars = [

			'booking' => $this->booking,
			'features' => $this->features,
			'edit_mode' => $this->edit_mode,
			'capacity_max' => $capacity_max,

		];

		return $vars;
	}


	/**
	 * Edit a booking
	 *
	 */
	private function process_edit_booking()
	{
		$rules = $this->get_validation_rules($this->booking->booking_id);
		$this->CI->load->library('form_validation');
		$this->CI->form_validation->set_rules($rules);

		if ($this->CI->form_validation->run() == FALSE) {
			$this->message = lang('app.form_error');
			return FALSE;
		}

		// Build data array with values that can be updated.
		// These are passed directly to db->update(), so it should only include
		// the fields that are permitted according to the edit mode.

		$booking_data = [];

		if ($this->features[self::FEATURE_DATE]) {
			$value = $this->CI->input->post('booking_date');
			// The edit form uses day/month/year; ISO dates are also accepted by
			// the existing date helper and model. Reject rollover and free-form dates.
			if (is_string($value) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/D', $value, $parts)) {
				$valid = checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3]);
			} elseif (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)) {
				$valid = checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
			} else {
				$valid = FALSE;
			}
			$date = $valid ? datetime_from_string($value) : FALSE;
			if ( ! $date) {
				$this->message = lang('app.form_error');
				return FALSE;
			}
			$booking_data['date'] = $date->format('Y-m-d');
		}

		if ($this->features[self::FEATURE_PERIOD]) {
			$booking_data['period_id'] = $this->CI->input->post('period_id');
		}

		if ($this->features[self::FEATURE_ROOM]) {
			$booking_data['room_id'] = $this->CI->input->post('room_id');
		}

		if ($this->features[self::FEATURE_DEPARTMENT]) {
			$booking_data['department_id'] = $this->CI->input->post('department_id');
		}

		if ($this->features[self::FEATURE_EDIT_USER]) {
			$booking_data['user_id'] = $this->CI->input->post('user_id');
		}

		if ($this->features[self::FEATURE_EDIT_NOTES]) {
			$booking_data['notes'] = $this->CI->input->post('notes');
		}

		if ($this->features[self::FEATURE_CAPACITY]) {
			$booking_data['capacity'] = (int) $this->CI->input->post('capacity');
		}

		if ($this->edit_mode === self::EDIT_ONE && ! $this->valid_destination($booking_data)) {
			$this->message = lang('booking.edit.error');
			return FALSE;
		}

		// Own the outer transaction: CI nested commits do not commit, and nested
		// rollbacks do not undo writes. Lock the original creator before rooms,
		// in the same order as single-booking creation.
		if ($this->CI->db->trans_active() || ! $this->CI->db->trans_begin()) {
			$this->message = lang('booking.edit.error');
			return FALSE;
		}
		$committed = FALSE;
		try {
			$creator = (int) ($this->booking->created_by ?? 0);
			if ($this->edit_mode === self::EDIT_ONE && empty($this->booking->repeat_id) && $creator > 0) {
				$before = (array) $this->booking;
				$before['date'] = $this->booking->date->format('Y-m-d');
				$after = array_merge($before, $booking_data);
				$quota = $this->CI->users_model->lock_scheduled_booking_quota($creator, [$after], [$before]);
				if ($quota === FALSE) {
					$this->message = lang('booking.edit.error');
					return FALSE;
				}
				$limit = $this->CI->users_model->get_constraints($creator)['max_active_bookings'];
				if ($limit !== NULL && $quota['requested'] > 0 && $quota['count'] + $quota['requested'] > (int) $limit) {
					$this->message = lang('booking.error.must_select_fewer');
					return FALSE;
				}
			}
			$update = $this->CI->bookings_model->update($this->booking->booking_id, $booking_data, $this->edit_mode, $this->booking);
			if ($update && $this->CI->db->trans_status()) $committed = $this->CI->db->trans_commit();
		} finally {
			// An unexpected exception inside a nested model may leave its CI
			// depth open; unwind all levels owned by this agent.
			if ( ! $committed) {
				while ($this->CI->db->trans_active()) {
					if ( ! $this->CI->db->trans_rollback()) break;
				}
			}
		}

		if ($committed) {

			$msgs = [
				self::EDIT_ONE => lang('booking.edit.one.success'),
				self::EDIT_FUTURE => lang('booking.edit.future.success'),
				self::EDIT_ALL => lang('booking.edit.all.success'),
			];

			$this->message = $msgs[$this->edit_mode];
			$this->success = TRUE;

			return TRUE;
		}

		$err = $this->CI->bookings_model->get_error();

		$this->message = $err ?: lang('booking.edit.error')
			;

		return FALSE;
	}

	private function valid_destination(array $data)
	{
		$room_id = $data['room_id'] ?? $this->booking->room_id;
		$period_id = $data['period_id'] ?? $this->booking->period_id;
		$date = isset($data['date']) ? datetime_from_string($data['date']) : $this->booking->date;
		if ( ! $date) return FALSE;
		$date_string = $date->format('Y-m-d');
		$moved = $date_string !== $this->booking->date->format('Y-m-d')
			|| (int) $room_id !== (int) $this->booking->room_id
			|| (int) $period_id !== (int) $this->booking->period_id;
		// Do not prevent metadata-only edits of existing, now-unbookable slots.
		if ( ! $moved) return TRUE;

		$room = $this->CI->rooms_model->get_bookable_rooms([
			'user_id' => $this->user->user_id,
			'room_id' => $room_id,
		]);
		if ( ! $room || empty($room->room_group_id)
			|| (int) $room->room_group_id !== (int) $this->booking->room->room_group_id) return FALSE;

		$recurring = ! empty($this->booking->repeat_id);
		$create_permission = $recurring ? Permission::BK_RECUR_CREATE : Permission::BK_SGL_CREATE;
		if ( ! has_permission($create_permission, $room_id)) return FALSE;

		// A move must not transfer source-room assignment privileges to the destination.
		if (array_key_exists('user_id', $data) && (int) $data['user_id'] !== (int) $this->booking->user_id) {
			$view = $recurring ? Permission::BK_RECUR_VIEW_OTHER_USERS : Permission::BK_SGL_VIEW_OTHER_USERS;
			$set = $recurring ? Permission::BK_RECUR_SET_USER : Permission::BK_SGL_SET_USER;
			if ( ! has_permission($view, $room_id) || ! has_permission($set, $room_id)) return FALSE;
		}
		if (array_key_exists('department_id', $data) && (int) $data['department_id'] !== (int) $this->booking->department_id) {
			$set = $recurring ? Permission::BK_RECUR_SET_DEPT : Permission::BK_SGL_SET_DEPT;
			if ( ! has_permission($set, $room_id)) return FALSE;
		}
		if (array_key_exists('notes', $data) && (string) $data['notes'] !== (string) ($this->booking->notes ?? '')
			&& (int) $this->booking->user_id !== (int) $this->user->user_id) {
			$view = $recurring ? Permission::BK_RECUR_VIEW_OTHER_NOTES : Permission::BK_SGL_VIEW_OTHER_NOTES;
			$edit = $recurring ? Permission::BK_RECUR_EDIT_OTHER : Permission::BK_SGL_EDIT_OTHER;
			if ( ! has_permission($view, $room_id) || ! has_permission($edit, $room_id)) return FALSE;
		}

		$schedule = $this->CI->schedules_model->get_applied_schedule($this->booking->session_id, $room->room_group_id);
		$period = $this->CI->periods_model->get($period_id);
		$day = 'day_' . $date->format('N');
		if ( ! $schedule || ! $period || empty($period->bookable)
			|| (int) $period->schedule_id !== (int) $schedule->schedule_id || empty($period->$day)) return FALSE;

		$date_info = $this->CI->dates_model->get_by_date($date_string);
		if ( ! $date_info || (int) $date_info->session_id !== (int) $this->booking->session_id
			|| empty($date_info->week_id) || ! empty($date_info->holiday_id)) return FALSE;

		$today = (new \DateTime())->setTime(0, 0, 0);
		$start = $this->session->date_start->format('Y-m-d');
		$end = $this->session->date_end->format('Y-m-d');
		if ( ! empty($this->session->is_current) && ! has_permission(Permission::BK_SGL_CREATE)
			&& ! has_permission(Permission::BK_RECUR_CREATE)) {
			$start = max($start, $today->format('Y-m-d'));
		}
		if ($date_string < $start || $date_string > $end) return FALSE;

		// Notice constraints apply to one-time bookings, not recurring series.
		if ( ! $recurring) {
			$constraints = $this->CI->users_model->get_constraints($this->user->user_id);
			$min = $constraints['range_min'] ?? NULL;
			$max = $constraints['range_max'] ?? NULL;
			if ($min !== NULL && $date_string < (clone $today)->modify(sprintf('%+d days', (int) $min))->format('Y-m-d')) return FALSE;
			if ($max !== NULL && $date_string > (clone $today)->modify(sprintf('%+d days', (int) $max))->format('Y-m-d')) return FALSE;
		}
		return TRUE;
	}


	private function get_validation_rules($booking_id)
	{
		$rules = [];

		if ($this->features[self::FEATURE_DATE]) {
			$rules[] = ['field' => 'booking_date', 'label' => 'lang:app.date', 'rules' => 'required|valid_date'];
		}

		if ($this->features[self::FEATURE_PERIOD]) {
			$rules[] = ['field' => 'period_id', 'label' => 'lang:period.period', 'rules' => 'required|integer'];
		}

		if ($this->features[self::FEATURE_ROOM]) {
			$rules[] = ['field' => 'room_id', 'label' => 'lang:room.room', 'rules' => 'required|integer'];
		}

		if ($this->features[self::FEATURE_DEPARTMENT]) {
			$rules[] = ['field' => 'department_id', 'label' => 'lang:department.department', 'rules' => 'integer'];
		}

		if ($this->features[self::FEATURE_EDIT_USER]) {
			$rules[] = ['field' => 'user_id', 'label' => 'lang:user.user', 'rules' => 'integer'];
		}

		if ($this->features[self::FEATURE_EDIT_NOTES]) {
			$rules[] = ['field' => 'notes', 'label' => 'lang:booking.notes', 'rules' => 'max_length[255]'];
		}

		if ($this->features[self::FEATURE_CAPACITY]) {
			$rules[] = ['field' => 'capacity', 'label' => 'lang:booking.capacity', 'rules' => 'required|integer|greater_than[0]'];
		}

		return $rules;
	}


}
