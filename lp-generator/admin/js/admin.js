(function ($) {
	'use strict';

	var config = window.lpgenAdmin || {};

	function showResult(message, ok) {
		var $target = $('#lpgen-action-result');
		if (!$target.length) {
			$target = $('#lpgen-tool-result');
		}
		if (!$target.length) {
			$target = $('<div id="lpgen-action-result" aria-live="polite"></div>');
			$('.lpgen-wrap').append($target);
		}
		$target
			.removeClass('lpgen-result--ok lpgen-result--error')
			.addClass(ok ? 'lpgen-result--ok' : 'lpgen-result--error')
			.text(message || '');
	}

	function call(action, data) {
		var payload = $.extend({}, data || {}, {
			action: 'lpgen_' + action,
			nonce: config.nonce
		});
		return $.post(config.ajaxUrl, payload, null, 'json');
	}

	$(document).on('click', '[data-lpgen-action]', function (event) {
		event.preventDefault();
		var $button = $(this);
		var action = $button.data('lpgen-action');

		if ('delete-log' === action || 'clear-logs' === action) { return; }
		var campaign = $button.data('campaign');

		if ('pick-images' === action) {
			if (!window.wp || !wp.media) {
				showResult('Media Library tidak tersedia.', false);
				return;
			}
			var frame = wp.media({
				title: 'Pilih gambar',
				button: { text: 'Gunakan gambar' },
				multiple: true,
				library: { type: 'image' }
			});
			frame.on('select', function () {
				var ids = frame.state().get('selection').map(function (attachment) {
					return attachment.id;
				});
				$('#lpgen_image_upload_ids').val(ids.join(','));
				$('#lpgen_image_upload_info').text(ids.length + ' gambar dipilih');
			});
			frame.open();
			return;
		}

		if ('start-campaign' === action && !window.confirm(config.i18n.confirmStart)) {
			return;
		}
		if ('publish-campaign' === action && !window.confirm(config.i18n.confirmPublish)) {
			return;
		}

		var data = {};
		if (campaign) {
			data.campaign = campaign;
		}
		if ('set-status' === action) {
			data.item = $button.data('item');
			data.status = $button.data('status');
		}
		if ('regenerate' === action) {
			data.item = $button.data('item');
		}
		if ('generate-tag-content' === action || 'generate-entities' === action) {
			data.campaign = $button.data('campaign');
			data.tag = $button.data('tag') || '';
		}
		if ('test-amazon' === action) {
			data.keyword = $('#lpgen_test_keyword').val() || '';
		}

		var original = $button.text();
		$button.prop('disabled', true).text(config.i18n.running);
		call(action.replace(/-/g, '_'), data)
			.done(function (response) {
				showResult(response && response.message ? response.message : '', !!(response && response.ok));
				if (response && response.ok && ('start-campaign' === action || 'publish-campaign' === action || 'regenerate' === action || 'generate-entities' === action || 'generate-tag-content' === action)) {
					window.setTimeout(function () { window.location.reload(); }, 900);
				}
			})
			.fail(function () {
				showResult('AJAX error', false);
			})
			.always(function () {
				$button.prop('disabled', false).text(original);
			});
	});

	var $logTable = $('.lpgen-log-table');
	if ($logTable.length) {
		var logFilters = $('#lpgen-log-filters');
		var logDebounce;
		var selectedLogIds = function () {
			return $logTable.find('input[name="log_ids[]"]:checked').map(function () { return $(this).val(); }).get();
		};
		logFilters.on('input', 'input[type="search"]', function () {
			window.clearTimeout(logDebounce);
			logDebounce = window.setTimeout(function () { logFilters.trigger('submit'); }, 500);
		});
		$('.lpgen-logs').on('click', '.lpgen-stat[data-level]', function () {
			logFilters.find('select[name="level"]').val($(this).data('level') || '');
			logFilters.trigger('submit');
		});
		$('#lpgen-log-check-all').on('change', function () {
			$logTable.find('input[name="log_ids[]"]').prop('checked', this.checked);
		});
		$(document).on('click', '[data-lpgen-action="delete-log"]', function (event) {
			event.preventDefault();
			var $button = $(this);
			var original = $button.text();
			$button.prop('disabled', true).text(config.i18n.running);
			call('delete_log', { log: $button.data('log') })
				.done(function (response) {
					showResult(response && response.message ? response.message : '', !!(response && response.ok));
					if (response && response.ok) { window.setTimeout(function () { window.location.reload(); }, 700); }
				})
				.fail(function () { showResult('AJAX error', false); })
				.always(function () { $button.prop('disabled', false).text(original); });
		});
		$(document).on('click', '[data-lpgen-action="clear-logs"]', function (event) {
			event.preventDefault();
			var $button = $(this);
			var data = { all: $button.data('all') ? 1 : 0 };
			if ($button.data('selected')) {
				var ids = selectedLogIds();
				if (!ids.length) { showResult('Pilih log terlebih dahulu.', false); return; }
				data.ids = ids;
			} else if (!data.all) {
				data.campaign = logFilters.find('select[name="campaign_id"]').val();
				data.level = logFilters.find('select[name="level"]').val();
			}
			var message = data.all ? 'Hapus semua log?' : (data.ids ? 'Hapus log terpilih?' : 'Hapus log sesuai filter?');
			if (!window.confirm(message)) { return; }
			var original = $button.text();
			$button.prop('disabled', true).text(config.i18n.running);
			call('clear_logs', data)
				.done(function (response) {
					showResult(response && response.message ? response.message : '', !!(response && response.ok));
					if (response && response.ok) { window.setTimeout(function () { window.location.reload(); }, 700); }
				})
				.fail(function () { showResult('AJAX error', false); })
				.always(function () { $button.prop('disabled', false).text(original); });
		});
		var autoTimer = null;
		$('#lpgen-log-autorefresh').on('change', function () {
			if (this.checked) { autoTimer = window.setInterval(function () { window.location.reload(); }, 15000); }
			else { window.clearInterval(autoTimer); }
		});
	}
})(jQuery);
