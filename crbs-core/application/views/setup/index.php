<?php

echo $this->session->flashdata('saved');
echo '<p class="setup-navigation-hint">' . html_escape(lang('setup.navigation.hint')) . '</p>';
