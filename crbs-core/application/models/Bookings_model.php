<?php

use app\components\bookings\Context;
use app\components\bookings\Slot;
use app\components\bookings\exceptions\BookingValidationException;
use app\components\bookings\agent\UpdateAgent;


class Bookings_model extends CI_Model
{


	const STATUS_BOOKED = 10;
	const STATUS_CANCELLED = 15;

	protected $table = 'bookings';

	// Other objects to get/include with returned value
	private $include = [];

	// Error message
	private $error = FALSE;

	// private $all_periods;
	// private $periods_by_day_num;


	// Legacy:
	public $table_headings = '';
	public $table_rows = array();



	public function __construct()
	{
		$this->load->helper('result');
		$this->load->model('sessions_model');
	}


	public function get_error()
	{
		return $this->error;
	}


	/**
	 * Execute one atomic model operation. A nested success is provisional: the
	 * caller still owns the outer transaction and must check its final commit.
	 */
	public function booking_transaction(callable $operation)
	{
		$nested = $this->db->trans_active();
		$savepoint = NULL;
		if ($nested) {
			static $sequence = 0;
			$savepoint = 'crbs_booking_' . ++$sequence;
			if ( ! $this->db->query('SAVEPOINT ' . $savepoint, FALSE, FALSE)) return FALSE;
		} elseif ( ! $this->db->trans_begin()) {
			return FALSE;
		}

		$finished = FALSE;
		try {
			$result = $operation();
			if ( ! $result || ! $this->db->trans_status()) return FALSE;
			if ($nested) {
				if ( ! $this->db->query('RELEASE SAVEPOINT ' . $savepoint, FALSE, FALSE)) return FALSE;
			} elseif ( ! $this->db->trans_commit()) {
				return FALSE;
			}
			$finished = TRUE;
			return $result;
		} finally {
			if ( ! $finished) {
				if ($nested) {
					try {
						$rolled_back = $this->db->query('ROLLBACK TO SAVEPOINT ' . $savepoint, FALSE, FALSE);
					} catch (Throwable $e) {
						$rolled_back = FALSE;
					}
					if ($rolled_back) $this->db->query('RELEASE SAVEPOINT ' . $savepoint, FALSE, FALSE);
					if ( ! $rolled_back) {
						// A failed savepoint rollback cannot be allowed to leave partial
						// writes for an unwitting caller to commit.
						while ($this->db->trans_active()) {
							if ( ! $this->db->trans_rollback()) {
								throw new RuntimeException('Unable to roll back booking transaction');
							}
						}
						throw new RuntimeException('Unable to roll back booking savepoint; outer transaction rolled back');
					}
				} elseif ($this->db->trans_active() && ! $this->db->trans_rollback()) {
					throw new RuntimeException('Unable to roll back booking transaction');
				}
			}
		}
	}


	public function include($objects)
	{
		if ( ! is_array($objects)) {
			$objects = [ $objects ];
		}

		$this->include = $objects;

		return $this;
	}


	public function get($booking_id)
	{
		$this->db->reset_query();

		$this->db->select([
			'b.*',
			'd.week_id AS week_id',
			'p.time_start',
			'p.time_end',
			'p.schedule_id',
		]);

		$this->db->from("{$this->table} b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('dates d', 'date', 'LEFT');
		$this->db->join('weeks w', 'week_id', 'LEFT');

		$this->db->where('b.booking_id', $booking_id);
		$this->db->limit(1);

		$query = $this->db->get();

		if ($query->num_rows() === 1) {
			return $this->wake_value($query->row());
		}

		return FALSE;
	}


	/**
	 * Get all bookings by specified IDs.
	 *
	 */
	public function find_by_ids(array $booking_ids)
	{
		if (empty($booking_ids)) return [];

		$this->db->reset_query();

		$this->db->select([
			'b.booking_id',
			'b.repeat_id',
			'b.period_id',
			'b.room_id',
			'b.user_id',
			'b.capacity',
			'b.date',
			'b.status',
			'b.notes',
			'p.time_start',
			'p.time_end',
		]);

		$this->db->select([
			'u.user_id AS user__user_id',
			'u.username AS user__username',
			'u.displayname AS user__displayname'
		], FALSE);

		$this->db->select([
			'r.week_id AS repeat__week_id',
			'r.weekday AS repeat__weekday',
		], FALSE);

		$this->db->select([
			'w.week_id AS repeat_week__week_id',
			'w.name AS repeat_week__name',
			'w.fgcol AS repeat_week__fgcol',
			'w.bgcol AS repeat_week__bgcol',
		], FALSE);

		$this->db->select([
			'rm.user_id AS room__user_id',
			'rm.name AS room__name',
		]);

		$this->db->select([
			'dp.name AS department__name',
		]);

		$this->db->select([
			'p.name AS period__name',
			'p.time_start AS period__time_start',
			'p.time_end AS period__time_end',
		]);

		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('rooms rm', 'room_id', 'INNER');
		$this->db->join('users u', 'b.user_id = u.user_id', 'LEFT');
		$this->db->join('bookings_repeat r', 'repeat_id', 'LEFT');
		$this->db->join('weeks w', 'week_id', 'LEFT');
		$this->db->join('departments dp', 'b.department_id = dp.department_id', 'LEFT');

		$this->db->where('b.status', self::STATUS_BOOKED);

		$this->db->where_in('b.booking_id', $booking_ids);

		$query = $this->db->get();
		$result = $query->result();

		foreach ($result as &$row) {
			$row = $this->wake_value($row);
		}

		return $result;
	}


	/**
	 * Find all bookings in a repeating series.
	 *
	 */
	public function find_by_repeat($repeat_id)
	{
		$this->db->reset_query();

		$this->db->select([
			'b.*',
			'p.time_start',
			'p.time_end',
		]);

		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->where('b.repeat_id', $repeat_id);

		$this->db->order_by('date', 'ASC');

		$query = $this->db->get();
		$result = $query->result();

		$out = [];

		foreach ($result as &$row) {
			$key = Slot::generate_key($row->date, $row->period_id, $row->room_id);
			$out[$key] = $this->wake_value($row);
		}

		return $out;
	}


	/**
	 * Given a list of dates, a Period ID and Room ID, get a list of active
	 * bookings that already exist for that criteria.
	 *
	 * @param $dates Array of dates in Y-m-d format to check for
	 *
	 */
	public function find_conflicts(array $dates, $period_id, $room_id)
	{
		if (empty($dates)) return false;

		$this->db->reset_query();

		$this->db->select([
			'b.booking_id',
			'b.repeat_id',
			'b.period_id',
			'b.room_id',
			'b.user_id',
			'b.date',
			'b.status',
			'b.notes',
			'p.time_start',
			'p.time_end',
		]);

		$this->db->select([
			'u.user_id AS user__user_id',
			'u.username AS user__username',
			'u.displayname AS user__displayname'
		], FALSE);

		$this->db->select([
			'r.room_id AS room__room_id',
			'r.name AS room__name',
		], FALSE);

		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('users u', 'user_id', 'LEFT');
		$this->db->join('rooms r', 'room_id', 'LEFT');

		$this->db->where('b.period_id', $period_id);
		$this->db->where('b.room_id', $room_id);
		$this->db->where('b.status', self::STATUS_BOOKED);
		$this->db->where_in('b.date', $dates);

		$this->db->order_by('date', 'ASC');

		$query = $this->db->get();
		$result = $query->result();

		$out = [];

		foreach ($result as &$row) {
			$key = Slot::generate_key($row->date, $row->period_id, $row->room_id);
			$out[$key] = $this->wake_value($row);
		}

		return $out;
	}


	public function find_all_for_slot($date, $period_id, $room_id)
	{
		$this->db->reset_query();
		$this->db->select([
			'b.booking_id', 'b.repeat_id', 'b.period_id', 'b.room_id', 'b.user_id',
			'b.capacity', 'b.date', 'b.status', 'b.notes', 'b.department_id',
			'p.name AS period__name',
			'p.time_start AS period__time_start',
			'p.time_end AS period__time_end',
		]);
		$this->db->select([
			'u.user_id AS user__user_id',
			'u.username AS user__username',
			'u.displayname AS user__displayname',
		], FALSE);
		$this->db->select('d.name AS department__name', FALSE);
		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('users u', 'user_id', 'LEFT');
		$this->db->join('departments d', 'b.department_id = d.department_id', 'LEFT');
		$this->db->where([
			'b.date' => $date,
			'b.period_id' => $period_id,
			'b.room_id' => $room_id,
			'b.status' => self::STATUS_BOOKED,
		]);
		$this->db->order_by('b.booking_id', 'ASC');

		$query = $this->db->get();
		$out = [];
		$previous_include = $this->include;
		$this->include = ['user'];
		try {
			foreach ($query->result() as $row) {
				$out[] = $this->wake_value($row);
			}
		} finally {
			$this->include = $previous_include;
		}
		return $out;
	}


	public function find_all_for_dates(array $dates, $period_id, $room_id)
	{
		if (empty($dates)) return [];

		$this->db->reset_query();
		$this->db->select([
			'b.booking_id', 'b.repeat_id', 'b.period_id', 'b.room_id', 'b.user_id',
			'b.created_by', 'b.capacity', 'b.date', 'b.status', 'b.notes',
			'p.name AS period__name',
			'p.time_start AS period__time_start',
			'p.time_end AS period__time_end',
		]);
		$this->db->select([
			'u.user_id AS user__user_id',
			'u.username AS user__username',
			'u.displayname AS user__displayname',
		], FALSE);
		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('users u', 'user_id', 'LEFT');
		$this->db->where([
			'b.period_id' => $period_id,
			'b.room_id' => $room_id,
			'b.status' => self::STATUS_BOOKED,
		]);
		$this->db->where_in('b.date', $dates);
		$this->db->order_by('b.date', 'ASC');
		$this->db->order_by('b.booking_id', 'ASC');

		$query = $this->db->get();
		$out = [];
		$previous_include = $this->include;
		$this->include = [];
		try {
			foreach ($query->result() as $row) {
				$key = Slot::generate_key($row->date, $row->period_id, $row->room_id);
				$out[$key][] = $this->wake_value($row);
			}
		} finally {
			$this->include = $previous_include;
		}

		return $out;
	}


	/**
	 * Find all bookings relevant to the provided Context.
	 *
	 * Context will include things like room, dates, session.
	 *
	 * @param Context $context Populated Context instance.
	 * @return array Array of bookings
	 *
	 */
	public function find_for_context(Context $context)
	{
		$this->db->reset_query();

		$this->db->select([
			'b.booking_id',
			'b.repeat_id',
			'b.period_id',
			'b.room_id',
			'b.user_id',
			'b.capacity',
			'b.date',
			'b.status',
			'b.notes',
			'p.time_start',
			'p.time_end',
		]);

		$this->db->select([
			'u.user_id AS user__user_id',
			'u.username AS user__username',
			'u.displayname AS user__displayname'
		], FALSE);

		$this->db->select([
			'r.week_id AS repeat__week_id',
			'r.weekday AS repeat__weekday',
		], FALSE);

		$this->db->select([
			'w.week_id AS repeat_week__week_id',
			'w.name AS repeat_week__name',
			'w.fgcol AS repeat_week__fgcol',
			'w.bgcol AS repeat_week__bgcol',
		], FALSE);

		$this->db->select([
			'rm.user_id AS room__user_id',
		]);

		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('rooms rm', 'room_id', 'INNER');
		$this->db->join('users u', 'b.user_id = u.user_id', 'LEFT');
		$this->db->join('bookings_repeat r', 'repeat_id', 'LEFT');
		$this->db->join('weeks w', 'week_id', 'LEFT');

		$this->db->where('b.status', self::STATUS_BOOKED);
		if ($context->session) {
			$this->db->where('b.session_id', $context->session->session_id);
		} else {
			$this->db->where('b.session_id', -1);
		}

		switch ($context->display_type) {

			case 'day':
				$this->db->where('b.date', $context->datetime->format('Y-m-d'));
				break;

			case 'room':
				$this->db->where([
					'b.room_id' => ($context->room) ? $context->room->room_id : null,
				]);
				$this->db->where([
					'b.date >=' => $context->week_start->format('Y-m-d'),
					'b.date <=' => $context->week_end->format('Y-m-d'),
				]);
				break;
		}

		$query = $this->db->get();
		$result = $query->result();

		$out = [];

		foreach ($result as &$row) {
			$key = Slot::generate_key($row->date, $row->period_id, $row->room_id);
			$out[$key][] = $this->wake_value($row);
		}

		return $out;
	}


	public function export_unbuffered(array $filter, $fn)
	{
		$columns = [
			'Booking ID' => 'b.booking_id',
			'Recurring ID' => 'b.repeat_id',
			'People' => 'b.capacity',
			'Type' => "IF(b.repeat_id IS NULL, 'Single', 'Recurring')",
			'Status' => "CASE b.status WHEN 10 THEN 'Booked' WHEN 15 THEN 'Cancelled' END",
			'Session' => 'sess.name',
			'Date' => 'b.date',
			'Weekday' => 'DAYNAME(b.date)',
			'Timetable Week' => 'w.name',
			'Period' => 'p.name',
			'Schedule' => 'sched.name',
			'Start Time' => "TIME_FORMAT(p.time_start, '%H:%i')",
			'End Time' => "TIME_FORMAT(p.time_end, '%H:%i')",
			'Room' => 'r.name',
			'Room Group' => 'rg.name',
			'Notes' => 'b.notes',
			'Username' => 'u.username',
			'User' => 'COALESCE(u.displayname, u.username)',
			'Department' => 'dept.name',
			'Created At' => 'b.created_at',
			'Created User' => 'creu.username',
			'Updated At' => 'b.updated_at',
			'Updated User' => 'updu.username',
			'Cancelled At' => 'b.cancelled_at',
			'Cancelled User' => 'canu.username',
		];

		$this->db->reset_query();

		foreach ($columns as $alias => $select) {
			$str = sprintf("%s AS '%s'", $select, $alias);
			$this->db->select($str, false);
		}

		$this->db->from("{$this->table} AS b");
		$this->db->join('sessions sess', 'b.session_id = sess.session_id', 'INNER');
		$this->db->join('periods p', 'b.period_id = p.period_id', 'INNER');
		$this->db->join('schedules sched', 'p.schedule_id = sched.schedule_id', 'INNER');
		$this->db->join('rooms r', 'b.room_id = r.room_id', 'INNER');
		$this->db->join('room_groups rg', 'r.room_group_id = rg.room_group_id', 'INNER');
		$this->db->join('dates d', 'b.date = d.date AND b.session_id = d.session_id', 'INNER');
		$this->db->join('weeks w', 'd.week_id = w.week_id', 'LEFT');
		$this->db->join('departments dept', 'b.department_id = dept.department_id', 'LEFT');
		$this->db->join('users u', 'b.user_id = u.user_id', 'LEFT');
		$this->db->join('users creu', 'b.created_by = creu.user_id', 'LEFT');
		$this->db->join('users updu', 'b.updated_by = updu.user_id', 'LEFT');
		$this->db->join('users canu', 'b.cancelled_by = canu.user_id', 'LEFT');

		$include_cancelled = $filter['include_cancelled'] ?? 0;
		if ($include_cancelled == 0) {
			$this->db->where('b.status', self::STATUS_BOOKED);
		}

		if (array_key_exists('session_id', $filter) && ! empty($filter['session_id'])) {
			$this->db->where('b.session_id', $filter['session_id']);
		}

		if (array_key_exists('room_group_id', $filter) && ! empty($filter['room_group_id'])) {
			$this->db->where('r.room_group_id', $filter['room_group_id']);
		}

		$query = $this->db->get();

		$fn(array_keys($columns));

		while ($row = $query->unbuffered_row('array')) {
			$fn(array_values($row));
		}

		// $result = $query->result();

		// return $result;
	}


	/**
	 * Check various parameters of a booking creation request to ensure it can
	 * be made, no conflicts will occur and all parameters are correct.
	 *
	 */
	public function validate_booking($data, array $exclude_booking_ids = [], $check_duplicate_user = TRUE)
	{
		$capacity = filter_var($data['capacity'] ?? 1, FILTER_VALIDATE_INT);
		if ($capacity === FALSE || $capacity < 1) {
			throw BookingValidationException::forCapacityExceeded();
		}

		$room_query = $this->db->query(
			'SELECT capacity FROM rooms WHERE room_id = ? FOR UPDATE',
			[$data['room_id']]
		);
		$room = $room_query ? $room_query->row() : FALSE;
		if ( ! $room) throw BookingValidationException::forCapacityExceeded();

		$room_capacity = max(1, (int) $room->capacity);
		if ($capacity > $room_capacity) {
			throw BookingValidationException::forCapacityExceeded();
		}

		$exclude_booking_ids = array_values(array_filter(array_map('intval', $exclude_booking_ids)));
		if ($check_duplicate_user) {
			$person_id = !empty($data['user_id'])
				? (int) $data['user_id']
				: (int) ($data['_identity_creator_id'] ?? $this->userauth->user->user_id);
			$duplicate_sql = 'SELECT booking_id FROM bookings
					WHERE `date` = ? AND period_id = ? AND room_id = ? AND status = ?
					AND (user_id = ? OR (user_id IS NULL AND created_by = ?))';
			$duplicate_bindings = [
				$data['date'], $data['period_id'], $data['room_id'], self::STATUS_BOOKED,
				$person_id, $person_id,
			];
			if ($exclude_booking_ids) {
				$duplicate_sql .= ' AND booking_id NOT IN (' . implode(',', array_fill(0, count($exclude_booking_ids), '?')) . ')';
				$duplicate_bindings = array_merge($duplicate_bindings, $exclude_booking_ids);
			}
			$duplicate_query = $this->db->query($duplicate_sql . ' FOR UPDATE', $duplicate_bindings);
			if ( ! $duplicate_query) throw BookingValidationException::forCapacityExceeded();
			$duplicate = $duplicate_query->row();
			if ($duplicate) {
				throw BookingValidationException::forDuplicateUserBooking();
			}
		}

		// A locking read sees the latest committed rows even when the caller has
		// already established a REPEATABLE READ snapshot in an outer transaction.
		$sql = 'SELECT capacity
				FROM bookings
				WHERE `date` = ? AND period_id = ? AND room_id = ? AND status = ?';
		$bindings = [$data['date'], $data['period_id'], $data['room_id'], self::STATUS_BOOKED];
		$exclude_booking_ids = array_values(array_filter(array_map('intval', $exclude_booking_ids)));
		if ($exclude_booking_ids) {
			$sql .= ' AND booking_id NOT IN (' . implode(',', array_fill(0, count($exclude_booking_ids), '?')) . ')';
			$bindings = array_merge($bindings, $exclude_booking_ids);
		}

		$query = $this->db->query($sql . ' FOR UPDATE', $bindings);
		if ( ! $query) throw BookingValidationException::forCapacityExceeded();
		$booked_capacity = 0;
		foreach ($query->result() as $existing) {
			$booked_capacity += max(1, (int) $existing->capacity);
		}
		if ($booked_capacity + $capacity > $room_capacity) {
			throw BookingValidationException::forCapacityExceeded();
		}

		return TRUE;
	}


	public function create($data)
	{
		$this->error = FALSE;
		$data = $this->sleep_values($data);
		$data['capacity'] = $data['capacity'] ?? 1;
		$data['_identity_creator_id'] = $this->userauth->user->user_id;

		return $this->booking_transaction(function () use ($data) {
			try {
				$this->validate_booking($data);
			} catch (BookingValidationException $e) {
				$this->error = $e->getMessage();
				return FALSE;
			}
			$data['capacity'] = (int) $data['capacity'];
			$data['created_at'] = date('Y-m-d H:i:s');
			$data['created_by'] = $this->userauth->user->user_id;
			unset($data['_identity_creator_id']);

			if ( ! $this->db->insert($this->table, $data)) return FALSE;
			$id = $this->db->insert_id();
			return $id ?: FALSE;
		});
	}


	/** The optional expected booking is the snapshot used to authorise the edit. */
	public function update($booking_id, $data, $edit_mode = UpdateAgent::EDIT_ONE, $expected_booking = NULL)
	{
		$this->error = FALSE;
		if ( ! in_array($edit_mode, [UpdateAgent::EDIT_ONE, UpdateAgent::EDIT_FUTURE, UpdateAgent::EDIT_ALL], TRUE)) return FALSE;
		$booking = $expected_booking ?: $this->get($booking_id);
		if ( ! $booking || (int) $booking->booking_id !== (int) $booking_id || $booking->status != self::STATUS_BOOKED) return FALSE;
		$series = $edit_mode !== UpdateAgent::EDIT_ONE;
		if ($series && empty($booking->repeat_id)) return FALSE;

		$allowed = $series ? ['user_id', 'department_id', 'notes'] : ['date', 'period_id', 'room_id', 'user_id', 'department_id', 'notes', 'capacity'];
		if (array_diff(array_keys($data), $allowed)) return FALSE;
		$data = $this->sleep_values($data);
		$date = $booking->date->format('Y-m-d');

		return $this->booking_transaction(function () use ($booking_id, $data, $edit_mode, $booking, $series, $date) {
		// Discover rooms before taking locks. Recheck the locked rows below: a
		// snapshot that missed a moved/new instance must never be used to write.
		$rooms = [(int) $booking->room_id];
		if (isset($data['room_id'])) $rooms[] = (int) $data['room_id'];
		$series_sql = 'SELECT * FROM bookings WHERE repeat_id = ? AND session_id = ?';
		$series_bind = [$booking->repeat_id, $booking->session_id];
		if ($edit_mode === UpdateAgent::EDIT_FUTURE) {
			$series_sql .= ' AND `date` >= ?';
			$series_bind[] = $date;
		}
		if ($series) {
			$query = $this->db->query($series_sql, $series_bind);
			if ( ! $query) {
				return FALSE;
			}
			foreach ($query->result() as $row) $rooms[] = (int) $row->room_id;
		}
		$rooms = array_values(array_unique($rooms));
		sort($rooms, SORT_NUMERIC);
		foreach ($rooms as $room_id) {
			$query = $this->db->query('SELECT room_id FROM rooms WHERE room_id = ? FOR UPDATE', [$room_id]);
			if ( ! $query || ! $query->row()) {
				return FALSE;
			}
		}

		$query = $this->db->query('SELECT * FROM bookings WHERE booking_id = ? FOR UPDATE', [$booking_id]);
		$current = $query ? $query->row() : FALSE;
		if ( ! $current || ! $this->matches_edit_snapshot($current, $booking)) {
			return FALSE;
		}
		$query = $series ? $this->db->query($series_sql . ' ORDER BY booking_id FOR UPDATE', $series_bind) : NULL;
		if ($series && ! $query) {
			return FALSE;
		}
		$rows = $series ? $query->result() : [$current];
		$targets = [];
		try {
			foreach ($rows as $row) {
				if ($row->status != self::STATUS_BOOKED) continue;
				if ( ! in_array((int) $row->room_id, $rooms, TRUE) || ! $this->can_edit_row($row, $data)) {
					return FALSE;
				}
				$target = array_merge((array) $row, $data, ['_identity_creator_id' => $row->created_by]);
				$identity_changed = $target['date'] != $row->date
					|| (int) $target['period_id'] !== (int) $row->period_id
					|| (int) $target['room_id'] !== (int) $row->room_id
					|| (int) ($target['user_id'] ?? 0) !== (int) ($row->user_id ?? 0);
				if ( ! $series || $identity_changed) {
					$this->validate_booking($target, [(int) $row->booking_id], $identity_changed);
				}
				if ($series && $identity_changed) {
					$key = implode(':', [$target['date'], $target['period_id'], $target['room_id'], $target['user_id'] ?: $target['_identity_creator_id']]);
					if (isset($targets[$key])) throw BookingValidationException::forDuplicateUserBooking();
					$targets[$key] = TRUE;
				}
			}
		} catch (BookingValidationException $e) {
			$this->error = $e->getMessage();
			return FALSE;
		}

		$data['updated_at'] = date('Y-m-d H:i:s');
		$data['updated_by'] = $this->userauth->user->user_id;
		$success = TRUE;
		foreach ($rows as $row) {
			if ($row->status != self::STATUS_BOOKED) continue;
			if ( ! $this->db->update($this->table, $data, ['booking_id' => $row->booking_id], 1)) {
				$success = FALSE;
				break;
			}
		}
		if ($success && $edit_mode === UpdateAgent::EDIT_ALL) {
			$where = ['repeat_id' => $booking->repeat_id, 'session_id' => $booking->session_id];
			$repeat = $this->db->query('SELECT repeat_id FROM bookings_repeat WHERE repeat_id = ? AND session_id = ? FOR UPDATE', array_values($where));
			$success = $repeat && $repeat->row() && $this->db->update('bookings_repeat', $data, $where, 1);
		}
		return (bool) $success;
		});
	}

	private function matches_edit_snapshot($row, $booking)
	{
		if ($row->status != self::STATUS_BOOKED || $row->date !== $booking->date->format('Y-m-d')) return FALSE;
		foreach (['booking_id', 'repeat_id', 'session_id', 'period_id', 'room_id', 'user_id', 'created_by', 'capacity', 'department_id', 'notes', 'updated_at', 'updated_by'] as $field) {
			$actual = $row->$field ?? NULL;
			$expected = $booking->$field ?? NULL;
			if (($actual === NULL) !== ($expected === NULL) || ($actual !== NULL && (string) $actual !== (string) $expected)) return FALSE;
		}
		return TRUE;
	}

	private function can_edit_row($row, array $data)
	{
		if ( ! booking_editable($row)) return FALSE;
		$recurring = ! empty($row->repeat_id);
		$owner = $row->user_id == $this->userauth->user->user_id;
		$notes_permission = $recurring ? Permission::BK_RECUR_VIEW_OTHER_NOTES : Permission::BK_SGL_VIEW_OTHER_NOTES;
		$user_permission = $recurring ? Permission::BK_RECUR_VIEW_OTHER_USERS : Permission::BK_SGL_VIEW_OTHER_USERS;
		$set_user = $recurring ? Permission::BK_RECUR_SET_USER : Permission::BK_SGL_SET_USER;
		$set_dept = $recurring ? Permission::BK_RECUR_SET_DEPT : Permission::BK_SGL_SET_DEPT;
		if (array_key_exists('notes', $data) && ! $owner && ! has_permission($notes_permission, $row->room_id)) return FALSE;
		if (array_key_exists('user_id', $data) && (! has_permission($user_permission, $row->room_id) || ! has_permission($set_user, $row->room_id))) return FALSE;
		if (array_key_exists('department_id', $data) && ! has_permission($set_dept, $row->room_id)) return FALSE;
		return TRUE;
	}


	/**
	 * Cancel a single instance of a booking.
	 *
	 */
	public function cancel_single($booking_id)
	{
		return $this->booking_transaction(function () use ($booking_id) {
			$query = $this->db->query('SELECT b.*, p.time_end FROM bookings b JOIN periods p ON p.period_id = b.period_id WHERE b.booking_id = ? FOR UPDATE', [$booking_id]);
			$row = $query ? $query->row() : FALSE;
			if ( ! $row || $row->status != self::STATUS_BOOKED) return FALSE;
			$row->time_end = datetime_from_string($row->date . ' ' . $row->time_end);
			$row->date = datetime_from_string($row->date);
			if ( ! booking_cancelable($row)) return FALSE;

			$data = [
				'status' => self::STATUS_CANCELLED,
				'cancelled_at' => date('Y-m-d H:i:s'),
				'cancelled_by' => $this->userauth->user->user_id,
			];
			$where = ['booking_id' => $booking_id, 'status' => self::STATUS_BOOKED];
			return $this->db->update($this->table, $data, $where, 1) && $this->db->affected_rows() === 1;
		});
	}


	/**
	 * Cancel booking + future instances in series.
	 *
	 */
	public function cancel_future($booking_id)
	{
		return $this->cancel_series($booking_id, TRUE);
	}


	public function cancel_all($booking_id)
	{
		return $this->cancel_series($booking_id, FALSE);
	}

	private function cancel_series($booking_id, $future)
	{
		$booking = $this->get($booking_id);
		if ( ! $booking || empty($booking->repeat_id) || $booking->status != self::STATUS_BOOKED) return FALSE;
		return $this->booking_transaction(function () use ($booking_id, $future, $booking) {
		$sql = 'SELECT b.*, p.time_start, p.time_end FROM bookings b JOIN periods p ON p.period_id = b.period_id WHERE b.repeat_id = ? AND b.session_id = ?';
		$bindings = [$booking->repeat_id, $booking->session_id];
		if ($future) {
			$sql .= ' AND b.date >= ?';
			$bindings[] = $booking->date->format('Y-m-d');
		}
		$rooms = [];
		$query = $this->db->query($sql, $bindings);
		if ( ! $query) {
			return FALSE;
		}
		foreach ($query->result() as $row) $rooms[] = (int) $row->room_id;
		$rooms[] = (int) $booking->room_id;
		$rooms = array_values(array_unique($rooms));
		sort($rooms, SORT_NUMERIC);
		foreach ($rooms as $room_id) {
			$room = $this->db->query('SELECT room_id FROM rooms WHERE room_id = ? FOR UPDATE', [$room_id]);
			if ( ! $room || ! $room->row()) {
				return FALSE;
			}
		}
		$current = $this->db->query('SELECT * FROM bookings WHERE booking_id = ? FOR UPDATE', [$booking_id]);
		if ( ! $current || ! $current->row() || ! $this->matches_edit_snapshot($current->row(), $booking)) {
			return FALSE;
		}
		$query = $this->db->query($sql . ' ORDER BY b.booking_id FOR UPDATE', $bindings);
		if ( ! $query) {
			return FALSE;
		}
		$rows = $query->result();
		$target_ids = [];
		foreach ($rows as $row) {
			if ($row->status != self::STATUS_BOOKED) continue;
			if ( ! in_array((int) $row->room_id, $rooms, TRUE)) {
				return FALSE;
			}
			$row->time_end = datetime_from_string($row->date . ' ' . $row->time_end);
			$row->date = datetime_from_string($row->date);
			if ( ! booking_cancelable($row)) {
				// The helper checks the time before room-level permission. Check
				// permission independently so a past row cannot hide a denial.
				$now = new DateTime();
				$today = (clone $now)->setTime(0, 0, 0);
				$past = ($row->time_end instanceof DateTime && $now > $row->time_end)
					|| ($row->date instanceof DateTime && $row->date < $today);
				$authorized = $row->user_id == $this->userauth->user->user_id
					|| has_permission(Permission::BK_RECUR_CANCEL_OTHER, $row->room_id);
				if ( ! $future && $past && $authorized && (int) $row->booking_id !== (int) $booking_id) continue;
				return FALSE;
			}
			$target_ids[] = (int) $row->booking_id;
		}
		if ( ! in_array((int) $booking_id, $target_ids, TRUE)) return FALSE;

		$data = [
			'status' => self::STATUS_CANCELLED,
			'cancelled_at' => date('Y-m-d H:i:s'),
			'cancelled_by' => $this->userauth->user->user_id,
		];

		$success = TRUE;
		foreach ($target_ids as $target_id) {
			if ( ! $this->db->update($this->table, $data, ['booking_id' => $target_id], 1)) {
				$success = FALSE;
				break;
			}
		}
		if ($success && ! $future) {
			$where = ['repeat_id' => $booking->repeat_id, 'session_id' => $booking->session_id];
			$repeat = $this->db->query('SELECT repeat_id FROM bookings_repeat WHERE repeat_id = ? AND session_id = ? FOR UPDATE', array_values($where));
			$success = $repeat && $repeat->row() && $this->db->update('bookings_repeat', $data, $where, 1);
		}
		return (bool) $success;
		});
	}


	public function wake_value($row)
	{
		$row = nest_object_keys($row);

		if (isset($row->period) && is_object($row->period)) {
			$row->time_start = $row->period->time_start;
			$row->time_end = $row->period->time_end;
		}

		$datetime_value = (empty($row->time_start))
			? $row->date
			: "{$row->date} {$row->time_start}"
			;

		$row->date = datetime_from_string($datetime_value);

		if (is_object($row->date)) {
			$row->time_start = datetime_from_string(sprintf('%s %s', $row->date->format('Y-m-d'), $row->time_start));
			$row->time_end = datetime_from_string(sprintf('%s %s', $row->date->format('Y-m-d'), $row->time_end));
		}

		foreach ($this->include as $include) {

			switch ($include) {

				case 'user':
					$this->load->model('users_model');
					$this->load->model('departments_model');
					$user = $this->users_model->get_by_id($row->user_id);
					unset($user->password);
					$row->user = $user;
					if ($row->user) {
						$row->user->department = ($user->department_id)
							? $this->departments_model->Get($user->department_id)
							: false;
					}
					break;

				case 'department':
					$this->load->model('departments_model');
					$row->department = isset($row->department_id)
						? $this->departments_model->Get($row->department_id)
						: false;
					break;

				case 'room':
					$this->load->model('rooms_model');
					$room = $this->rooms_model->get_by_id($row->room_id);
					$row->room = $room;
					$row->room->info = $this->rooms_model->room_info($room);
					$row->room->fields = $this->rooms_model->GetFields();
					$row->room->fieldvalues = $this->rooms_model->GetFieldValues($room->room_id);
					break;

				case 'week':
					$this->load->model('weeks_model');
					$row->week = isset($row->week_id)
						? $this->weeks_model->get($row->week_id)
						: false;
					break;

				case 'period':
					$this->load->model('periods_model');
					$row->period = $this->periods_model->get($row->period_id);
					break;

				case 'session':
					$this->load->model('sessions_model');
					$row->session = $this->sessions_model->get($row->session_id);
					break;

				case 'repeat':
					$this->load->model('bookings_repeat_model');
					$row->repeat = $this->bookings_repeat_model->get($row->repeat_id);
					break;

			}
		}

		return $row;
	}


	public function sleep_values($data)
	{
		if (isset($data['user_id'])) {
			$data['user_id'] = (!empty($data['user_id']))
				? (int) $data['user_id']
				: NULL;
		}

		if (isset($data['department_id'])) {
			$data['department_id'] = (!empty($data['department_id']))
				? (int) $data['department_id']
				: NULL;
		}

		if (isset($data['date'])) {
			$dt = datetime_from_string($data['date']);
			$data['date'] = $dt ? $dt->format('Y-m-d') : NULL;
		}

		return $data;
	}


	/**
	 * Given a session ID, delete any existing bookings that fall outside of its date range.
	 *
	 */
	public function check_session_dates($session_id)
	{
		$session = $this->sessions_model->get($session_id);
		if ( ! $session) return FALSE;

		$sql = "DELETE FROM {$this->table}
				WHERE session_id = ?
				AND (`date` < ? OR `date` > ?)";

		$this->db->query($sql, [
			$session->session_id,
			$session->date_start->format('Y-m-d'),
			$session->date_end->format('Y-m-d'),
		]);

		return $this->db->affected_rows();
	}


	/**
	 * Delete entries for a given session.
	 *
	 */
	public function delete_by_session($session_id)
	{
		return $this->db->delete($this->table, ['session_id' => $session_id]);
		return $this->db->delete('bookings_repeat', ['session_id' => $session_id]);
	}


	function ByRoomOwner($user_id)
	{
		$date = new \DateTime();
		$start = $date->format('Y-m-d');
		$date->modify('+14 days');
		$end = $date->format('Y-m-d');

		$this->db->reset_query();

		$this->db->select([
			'b.booking_id',
			'b.date',
			'b.notes',
		]);

		$this->db->select([
			'p.name AS period__name',
			'p.time_start AS period__time_start',
			'p.time_end AS period__time_end',
		], FALSE);

		$this->db->select([
			'r.room_id AS room__room_id',
			'r.room_group_id AS room__room_group_id',
			'r.name AS room__name',
		], FALSE);

		$this->db->select([
			'u.user_id AS user__user_id',
			'u.username AS user__username',
			'u.displayname AS user__displayname'
		], FALSE);

		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('rooms r', 'room_id', 'INNER');
		$this->db->join('users u', 'b.user_id = u.user_id', 'INNER');

		$this->db->where('r.user_id', $user_id);
		$this->db->where('b.user_id!=', $user_id);
		$this->db->where('b.repeat_id IS NULL');
		$this->db->where('b.status', self::STATUS_BOOKED);
		$this->db->where('b.date>=', $start);
		$this->db->where('b.date<=', $end);

		$this->db->order_by('b.date', 'ASC');
		$this->db->order_by('p.time_start', 'ASC');

		$query = $this->db->get();
		$result = $query->result();

		if ($query->num_rows() == 0) return FALSE;

		foreach ($result as &$row) {
			$row = $this->wake_value($row);
		}

		return $result;
	}




	function ByUser($user_id)
	{
		$date = new \DateTime();
		$start = $date->format('Y-m-d');
		$time = $date->format('H:i') . ':00';
		$date->modify('+14 days');
		$end = $date->format('Y-m-d');

		$this->db->reset_query();

		$this->db->select([
			'b.booking_id',
			'b.date',
			'b.notes',
		]);

		$this->db->select([
			'p.name AS period__name',
			'p.time_start AS period__time_start',
			'p.time_end AS period__time_end',
		], FALSE);

		$this->db->select([
			'r.room_id AS room__room_id',
			'r.room_group_id AS room__room_group_id',
			'r.name AS room__name',
		], FALSE);

		$this->db->from("{$this->table} AS b");
		$this->db->join('periods p', 'period_id', 'INNER');
		$this->db->join('rooms r', 'room_id', 'INNER');

		$this->db->where('b.user_id', $user_id);
		$this->db->where('b.repeat_id IS NULL');
		$this->db->where('b.status', self::STATUS_BOOKED);

		$this->db->where('b.date<=', $end);

		$start = $this->db->escape($start);
		$end = $this->db->escape($end);
		$time = $this->db->escape($time);
		$this->db->where("( (b.date > {$start}) OR (b.date = {$start} AND p.time_start > {$time}) )");

		$this->db->order_by('b.date', 'ASC');
		$this->db->order_by('p.time_start', 'ASC');

		$query = $this->db->get();
		$result = $query->result();

		if ($query->num_rows() == 0) return FALSE;

		foreach ($result as &$row) {
			$row = $this->wake_value($row);
		}

		return $result;
	}


	function TotalNum($user_id = 0)
	{
		$total = [];

		// All bookings by user, EVER!
		$sql = "SELECT COUNT(booking_id) AS total
				FROM bookings
				WHERE user_id = ?";
		$query = $this->db->query($sql, [$user_id]);
		$row = $query->row_array();
		$total['all'] = (int) $row['total'];

		// All bookings by user, for the current session
		$sql = "SELECT COUNT(b.booking_id) AS total
				FROM bookings b
				JOIN sessions s USING (session_id)
				WHERE b.user_id = ?
				AND s.is_current = 1";
		$query = $this->db->query($sql, [$user_id]);
		$row = $query->row_array();
		$total['session'] = (int) $row['total'];

		// All bookings up to and including today
		// $sql = "SELECT COUNT(booking_id) AS total
		// 		FROM bookings
		// 		WHERE bookings.user_id = ?
		// 		AND bookings.date <= ?";
		// $query = $this->db->query($sql, [$user_id, $today]);
		// $row = $query->row_array();
		// $total['todate'] = $row['total'];

		$total['active'] = $this->users_model->get_scheduled_booking_count($user_id);

		return $total;
	}




}
