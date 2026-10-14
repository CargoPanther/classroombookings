<?php
if (!empty($edit)) {
	$img = ui_icon('edit.png');
	echo anchor("{$edit}", $img, 'title="Edit"');
}

$img = ui_icon('delete.png');
echo anchor("{$delete}", $img, 'title="Delete"');
