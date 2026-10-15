<?php

if ( ! isset($active)) $active = NULL;

$items = [];

if (isset($group)) {
	$items[] = ['setup/rooms/groups', lang('room_group.all_groups'), 'arrow_turn_left.png'];
	$items[] = ['setup/rooms/groups/view/' . $group->room_group_id, lang('room.rooms'), 'school_manage_rooms.png'];
	$items[] = ['setup/rooms/groups/edit/' . $group->room_group_id, lang('app.action.update'), 'edit.png'];
	$items[] = ['setup/rooms/acl/room_group/' . $group->room_group_id, lang('acl.access_control'), 'lock.png'];
}

echo iconbar($items, $active);
