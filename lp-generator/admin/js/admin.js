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

		if ('run-now' === action) {
			$button.prop('disabled', false).text(original);
			runQueueLoop(campaign);
			return;
		}

		call(action.replace(/-/g, '_'), data)
			.done(function (response) {
				if ('check-update' === action && response && response.ok) {
					var $target = $('#lpgen-action-result');
					if (!$target.length) { $target = $('#lpgen-tool-result'); }
					if (!$target.length) {
						$target = $('<div id="lpgen-action-result" aria-live="polite"></div>');
						$('.lpgen-wrap').prepend($target);
					}
					$target.removeClass('lpgen-result--ok lpgen-result--error').addClass('lpgen-result--ok');
					if (response.has_update && response.update_url) {
						$target.html(
							'<strong>' + (response.message || 'Pembaruan tersedia!') + '</strong> ' +
							'<a href="' + response.update_url + '" class="button button-primary" style="margin-left:10px;vertical-align:middle;">' +
							'Perbarui Sekarang (v' + response.latest_version + ')' +
							'</a>'
						);
					} else {
						$target.text(response.message || 'Plugin sudah versi terbaru.');
					}
					return;
				}

				showResult(response && response.message ? response.message : '', !!(response && response.ok));
				if ('start-campaign' === action && response && response.ok) {
					runQueueLoop(campaign, response.jobs || 6);
					return;
				}
				if (response && response.ok && ('publish-campaign' === action || 'regenerate' === action || 'generate-entities' === action || 'generate-tag-content' === action || 'generate-framework' === action)) {
					window.setTimeout(function () { window.location.reload(); }, 900);
				}
			})
			.fail(function () {
				showResult('AJAX error', false);
			})
			.always(function () {
				if ('start-campaign' !== action) {
					$button.prop('disabled', false).text(original);
				}
			});
	});

	function runQueueLoop(campaign, totalJobs) {
		var $runner = $('#lpgen-live-runner');
		var $bar = $('#lpgen_runner_bar');
		var $status = $('#lpgen_runner_status');
		if ($runner.length) {
			$runner.show();
		} else {
			showResult('Sedang memproses antrean konten...', true);
		}

		var total = totalJobs || 6;
		function step() {
			call('process_queue', { campaign: campaign })
				.done(function (res) {
					if (!res || !res.ok) {
						if (res && res.busy) {
							window.setTimeout(step, 1500);
							return;
						}
						showResult(res && res.message ? res.message : 'Error processing queue', false);
						if ($status.length) { $status.text(res && res.message ? res.message : 'Error'); }
						return;
					}
					if (res.done || res.remaining === 0) {
						if ($bar.length) { $bar.css('width', '100%'); }
						if ($status.length) { $status.text('Selesai! Semua konten berhasil dibuat.'); }
						showResult('Selesai! Semua konten berhasil dibuat.', true);
						window.setTimeout(function () {
							window.location.reload();
						}, 1200);
						return;
					}
					var rem = res.remaining;
					if (rem > total) { total = rem; }
					var pct = Math.max(10, Math.min(95, Math.round(((total - rem) / total) * 100)));
					if ($bar.length) { $bar.css('width', pct + '%'); }
					if ($status.length) {
						$status.text('Sedang memproses... ' + (res.message || '') + ' (' + rem + ' tersisa)');
					}
					window.setTimeout(step, 300);
				})
				.fail(function () {
					showResult('Koneksi terputus saat memproses queue. Coba klik "Proses Antrean Sekarang".', false);
				});
		}
		step();
	}

	// Dynamic campaign form toggles and framework preview
	$(document).on('change', '.lpgen-toggle-bofu', function () {
		if ('existing' === $(this).val()) {
			$('#lpgen_bofu_existing_box').slideDown(150);
		} else {
			$('#lpgen_bofu_existing_box').slideUp(150);
		}
	});

	$(document).on('change', '.lpgen-toggle-topic', function () {
		if ('manual' === $(this).val()) {
			$('#lpgen_topic_auto_row').hide();
			$('#lpgen_topic_manual_row_mofu, #lpgen_topic_manual_row_tofu').show();
		} else {
			$('#lpgen_topic_auto_row').show();
			$('#lpgen_topic_manual_row_mofu, #lpgen_topic_manual_row_tofu').hide();
		}
	});

	$(document).on('change', '#lpgen_framework', function () {
		var key = $(this).val();
		var data = window.lpgenFrameworkData || {};
		var steps = (data[key] && data[key].steps) ? data[key].steps : ['Attention', 'Interest', 'Desire', 'Action'];
		var $preview = $('#lpgen_framework_steps_preview');
		if ($preview.length) {
			var html = '<div class="lpgen-steps-badges" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">';
			html += '<span style="font-weight:600;font-size:12px;color:#50575e;">Langkah Copywriting:</span>';
			$.each(steps, function (idx, stepName) {
				html += '<span class="lpgen-badge lpgen-badge--done" style="font-size:12px;padding:3px 8px;">' + (idx + 1) + '. ' + $('<div>').text(stepName).html() + '</span>';
			});
			html += '</div>';
			$preview.html(html);
		}
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
