(function() {
	var csrfTokenName = document.querySelector('meta[name="csrf-token-name"]');
	var csrfToken = document.querySelector('meta[name="csrf-token"]');
	var storageKey = 'crbs-theme';
	var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
	var hasStoredPreference = false;

	try {
		var storedTheme = localStorage.getItem(storageKey);
		hasStoredPreference = ['light', 'dark', 'dark-neutral'].indexOf(storedTheme) !== -1;
	} catch (error) {}

	function applyTheme(theme) {
		var isDark = theme !== 'light';
		document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
		document.documentElement.setAttribute('data-theme-variant', theme === 'dark-neutral' ? 'neutral' : 'standard');
		var toggle = document.getElementById('theme-toggle');
		var selector = document.getElementById('theme-select');
		if (toggle) {
			toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			toggle.setAttribute('aria-label', theme === 'light'
				? 'Switch to dark mode'
				: (theme === 'dark' ? 'Switch to neutral dark mode' : 'Switch to light mode'));
			toggle.setAttribute('title', theme === 'light'
				? 'Switch to dark mode'
				: (theme === 'dark' ? 'Switch to neutral dark mode' : 'Switch to light mode'));
		}
		if (selector && selector.value !== theme) selector.value = theme;
	}

	function systemTheme() {
		return media && media.matches ? 'dark-neutral' : 'light';
	}

	applyTheme(hasStoredPreference ? storedTheme : systemTheme());

	document.addEventListener('click', function(event) {
		var toggle = event.target.closest && event.target.closest('#theme-toggle');
		if (!toggle) return;

		var currentTheme = document.documentElement.getAttribute('data-theme-variant') === 'neutral'
			? 'dark-neutral'
			: document.documentElement.getAttribute('data-theme');
		var nextTheme = currentTheme === 'light' ? 'dark' : (currentTheme === 'dark' ? 'dark-neutral' : 'light');
		hasStoredPreference = true;
		applyTheme(nextTheme);
		try {
			localStorage.setItem(storageKey, nextTheme);
		} catch (error) {}
	});

	document.addEventListener('change', function(event) {
		if (!event.target || event.target.id !== 'theme-select') return;
		var theme = event.target.value;
		hasStoredPreference = true;
		applyTheme(theme);
		try {
			localStorage.setItem(storageKey, theme);
		} catch (error) {}
	});

	if (media) {
		var onSystemThemeChange = function(event) {
			if (!hasStoredPreference) applyTheme(event.matches ? 'dark-neutral' : 'light');
		};
		if (media.addEventListener) {
			media.addEventListener('change', onSystemThemeChange);
		} else if (media.addListener) {
			media.addListener(onSystemThemeChange);
		}
	}

	window.addEventListener('storage', function(event) {
		if (event.key !== storageKey) return;
		hasStoredPreference = ['light', 'dark', 'dark-neutral'].indexOf(event.newValue) !== -1;
		applyTheme(hasStoredPreference ? event.newValue : systemTheme());
	});
})();

// up.motion.config.duration = 150;
up.motion.config.enabled = false;
// up.history.config.enabled = false;

/**
 * Settings: LDAP: Form for testing the settings
 *
 */
up.compiler('[ldap-settings]', function(element) {

	function populateTestForm() {
		var attrs = up.Params.fromForm('#ldap_settings'),
			attrsArray = attrs.toArray();
		for (var i = 0; i < attrsArray.length; i++) {
			var sel = "[ldap-settings] [name='" + attrsArray[i]['name'] + "']";
			var dest = "[ldap-test] [name='" + attrsArray[i]['name'] + "']";
			var testEl = up.element.get(dest);
			if (testEl) {
				up.element.setAttrs(testEl, { value: attrsArray[i]['value'] });
			}
		}
	};

	populateTestForm();

	up.observe(element, { batch: true }, function(diff) {
		// console.log('Observed one or more changes: %o', diff)
		populateTestForm();
	});

});


/**
 * Session calendars: Navigation mode: make previous/next buttons functional.
 *
 */
up.compiler('.session-calendars.mode-nav', function(wrapperEl, data) {
	up.on(wrapperEl, 'click', '.nav-btn', function(event, el) {
		var currentMonth = el.closest('.session-calendar');
		var dir = el.getAttribute('data-dir');
		var navToMonth = null;
		switch (dir) {
			case 'prev': navToMonth = currentMonth.previousElementSibling; break;
			case 'next': navToMonth = currentMonth.nextElementSibling; break;
		}
		navToMonth.removeAttribute('hidden');
		currentMonth.setAttribute('hidden', '');
	});
});

/**
 * Session calendars: Config mode: Click on dates to change Timetable Week assignments.
 *
 */
up.compiler('.session-calendars.mode-config', function(sessionEl, data) {

	var weekIds = [];
	var weekClassNames = [];
	var numWeeks = data && data.weeks ? data.weeks.length : 0;

	// Process list of available weeks
	for (var i = 0; i < numWeeks; i++) {
		var weekId = parseInt(data.weeks[i], 10);
		className = 'week-' + weekId;
		weekIds.push(weekId);
		weekClassNames.push(className);
	}
	// Add blank entry for cycling through items
	weekIds.push(null);

	// Click event for changing week assignment of a given date
	up.on(sessionEl, 'click', '.date-btn', function(event, el) {

		var cell = el.closest('.date-cell');

		var weekstart = cell.getAttribute('data-weekstart');
		var weekid = cell.getAttribute('data-weekid');

		// Current Week ID
		var curWeekId = weekid ? '' + weekid : null;
		// Array index of the current selected week
		var curWeekIdx = weekIds.indexOf(parseInt(curWeekId, 10));
		// Array index of next week to select
		var nextWeekIdx = (curWeekIdx + 1) % weekIds.length;

		// Next Week ID from the list
		var newWeekId = weekIds[ (curWeekIdx + 1) % weekIds.length ];

		// All cells (potentially on other months) whose week starts on `weekstart`
		var cells = up.element.all(sessionEl, '.date-cell[data-weekstart="' + weekstart + '"]');

		// Process all cells that match start of week
		up.util.each(cells, function(cell) {

			// Remove existing week classes
			for (var i = 0; i < weekClassNames.length; i++) {
				cell.classList.remove(weekClassNames[i]);
			}

			// An actual Week ID to add
			if (newWeekId !== null) {
				cell.classList.add('week-' + newWeekId);
			}

			// Update value of hidden input
			var input = up.element.get(cell, 'input[type=hidden]');
			input.value = newWeekId;

			// Update the data- attr with the new Week ID
			cell.setAttribute('data-weekid', newWeekId);

		});

	});

});


/**
 * On new Unpoly fragments, process nodes with hyperscript
 *
 */
up.on('up:fragment:inserted', function(event, fragment) {
	// Skip if initial load
	if (event.origin == undefined) return;
	_hyperscript.processNode(fragment);
});


/**
 * Bookings page: controls forms
 *
 */
up.compiler('[up-copy-to]', function(copyBtn) {

	up.on(copyBtn, 'click', function(evt, el, data) {

		var group = el.getAttribute('up-copy-to');

		var srcEl = up.element.get('[up-copy-group="' + group + '"]');
		var value = srcEl.value;

		var allItems = up.element.all('[up-copy-group="' + group + '"]');
		up.util.each(allItems, function(item) {
			item.value = value;
		});

	});
});


up.compiler('select[up-autocomplete]', function(selectEl) {
	accessibleAutocomplete.enhanceSelectElement({
		selectElement: selectEl,
		displayMenu: 'overlay',
		dropdownArrow: function(obj) {
			return '<svg xmlns="http://www.w3.org/2000/svg" focusable="false" class="autocomplete__dropdown-arrow-down h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />';
		}
	});
});

up.compiler('.dashboard-welcome-message', function(messageEl) {
	var hideUrl = messageEl.getAttribute('data-hide-url');
	if (!hideUrl) {
		return;
	}

	function hideMessage() {
		messageEl.style.display = 'none';
	}

	up.on(messageEl, 'click', '.dashboard-welcome-dismiss', function() {
		hideMessage();
	});

	up.on(messageEl, 'click', '.dashboard-welcome-never', function() {
		fetch(hideUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams(csrfTokenName && csrfToken ? {
				[csrfTokenName.content]: csrfToken.content
			} : {}),
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		}).catch(function() {});
		hideMessage();
	});
});

// up.compiler('[up-remove-row]', function(el) {
// 	up.on(el, 'click', function(evt, el, data) {
// 		evt.preventDefault();
// 		var row = el.closest('tr');
// 		up.element.remove(row);
// 		// console.log(row);
// 		// console.log(row);
// 	});
// });


/**
 * Unavailable slots: popup content: change position of popup based on X pos of element.
 *
 * This is used for unavailable slots - like holidays or period unavailability.
 *
 */
up.macro('.bookings-grid-button[up-content]', function(el) {
	el.setAttribute('up-position', getPopupAlignment(el));
});


/**
 * Determine where to place the popup (attached via data-up-popup).
 *
 */
function getPopupAlignment(el) {

	var rect = el.getBoundingClientRect();
	var posX = Math.ceil(rect.x);
	var docWidth = document.documentElement.clientWidth;
	var docHalf = Math.floor(docWidth / 2);

	if (posX < docHalf) {
		return 'right';
	} else {
		return 'left';
	}
}


function doToast(type, text, time) {

	var classes = {
		'success': 'toastify-success',
		'error': 'toastify-error',
		'warning': 'toastify-warning',
		'info': 'toastify-info',
	};
	var className = classes[type] ? classes[type] : 'toastify-info';

	// console.log(type, className);

	Toastify({
		text: text,
		duration: time === undefined || time === null ? 30000 : time,
		className: className,
		close: true,
		gravity: "bottom",
		position: "right",
		stopOnFocus: false,
	}).showToast();
}


up.compiler('.action-notification-source', function(source) {
	var boxes = source.querySelectorAll('.msgbox');
	if (!boxes.length) {
		var plainText = (source.innerText || source.textContent || '').trim();
		if (plainText) doToast('info', plainText, 30000);
		source.remove();
		return;
	}

	Array.prototype.forEach.call(boxes, function(box) {
		var classes = Array.prototype.slice.call(box.classList);
		var type = 'info';
		if (classes.indexOf('error') !== -1) {
			type = 'error';
		} else if (classes.indexOf('exclamation') !== -1 || classes.indexOf('notice') !== -1 || classes.indexOf('warning') !== -1) {
			type = 'warning';
		}
		var content = box.querySelector('.msgbox-content') || box;
		var text = (content.innerText || content.textContent || '').trim();
		if (text) doToast(type, text, 30000);
	});

	source.remove();
});

// Show/hide toggle for room door codes
document.addEventListener('click', function(event) {
	var button = event.target.closest && event.target.closest('.door-code-toggle');
	if (!button) return;

	var wrapper = button.closest('.door-code');
	var value = wrapper.querySelector('.door-code-value');
	var mask = wrapper.querySelector('.door-code-mask');
	var reveal = value.hidden;

	value.hidden = !reveal;
	mask.hidden = reveal;
	button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
	button.textContent = reveal ? button.getAttribute('data-hide') : button.getAttribute('data-show');
});

//

htmx.on('toast', function(evt) {
	if ( ! evt.detail.type) return;
	var type = evt.detail.type.toLowerCase();
	doToast(type, evt.detail.text, evt.detail.time ? evt.detail.time : null);
});
