(function ($) {
	'use strict';

	var config = $.extend({ajaxUrl: window.ajaxurl || '', nonce: ''}, window.lpgenAdmin || {});
	config.i18n = $.extend({confirmStart: 'Mulai generate funnel ini?', confirmPublish: 'Publish item yang disetujui?', running: 'Memproses…'}, config.i18n || {});
	var activeRunners = {};

	function showResult(message, ok) {
		var $target = $('#lpgen-action-result');
		if (!$target.length) {
			$target = $('#lpgen-tool-result');
		}
		if (!$target.length) {
			$target = $('<div id="lpgen-action-result" aria-live="polite"></div>');
			var $container = $('.lpgen-wrap, .lpgen-campaign-form, .wrap').first();
			$container.prepend($target);
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
		return $.ajax({url: config.ajaxUrl, type: 'POST', data: payload, dataType: 'json', timeout: 900000});
	}

	$(document).on('click', '[data-lpgen-action]', function (event) {
		event.preventDefault();
		var $button = $(this);
		var action = $button.data('lpgen-action');

		if ('delete-log' === action || 'clear-logs' === action) { return; }
		var campaign = $button.data('campaign');
		var data = {};
		if ($button.prop('disabled')) { return; }
		if (!config.ajaxUrl || !config.nonce) { showResult('Data halaman belum lengkap. Muat ulang halaman lalu coba lagi.', false); return; }
		if (activeRunners[campaign] && ('start-campaign' === action || 'run-now' === action || 'publish-campaign' === action)) { showResult('Campaign ini sedang diproses. Tunggu progres yang sedang berjalan.', false); return; }

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

		if ('start-campaign' === action || 'generate-framework' === action) {
			if (('start-campaign' === action || 'restart-campaign' === action) && !window.confirm(config.i18n.confirmStart)) {
				return;
			}
			// Auto-serialize all campaign form inputs so user edits are saved even if draft wasn't published yet
			var formValues = {};
			$('input[name^="lpgen["], select[name^="lpgen["], textarea[name^="lpgen["]').each(function () {
				var name = $(this).attr('name');
				var match = name.match(/lpgen\[([^\]]+)\]/);
				if (match) {
					var key = match[1];
					if ($(this).is(':checkbox')) {
						formValues[key] = $(this).is(':checked') ? 1 : 0;
					} else if ($(this).is(':radio')) {
						if ($(this).is(':checked')) {
							formValues[key] = $(this).val();
						}
					} else {
						formValues[key] = $(this).val();
					}
				}
			});
			if (Object.keys(formValues).length) { data.lpgen = formValues; }
			var postTitle = $('#title').val();
			if (postTitle) {
				data.post_title = postTitle;
			}
		}
		if ('publish-campaign' === action && !window.confirm(config.i18n.confirmPublish)) {
			return;
		}

		if (campaign) {
			data.campaign = campaign;
		}
		if ('suggest-campaign' === action) {
			var kw = $('#lpgen_offer_name').val() || $('#title').val() || '';
			if (!$.trim(kw)) {
				alert('Silakan ketik Nama Produk / Jasa / Keyword terlebih dahulu.');
				return;
			}
			data.keyword = $.trim(kw);
			data.language = $('#lpgen_language').val() || 'id';
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
		var queueStarted = false;
		function releaseButton() { $button.prop('disabled', false).text(original); }
		$button.prop('disabled', true).text(config.i18n.running);

		if ('run-now' === action) {
			queueStarted = true;
			runQueueLoop(campaign, 0, releaseButton);
			return;
		}

		if ('start-campaign' === action) { showResult('Menyimpan campaign dan membuat antrean…', true); }
		call(action.replace(/-/g, '_'), data)
			.done(function (response) {
				if ('suggest-campaign' === action) {
					if (response && response.ok && response.data) {
						var d = response.data;
						if (d.audience) { $('#lpgen_audience').val(d.audience); }
						if (d.problem) { $('#lpgen_problem').val(d.problem); }
						if (d.promise) { $('#lpgen_promise').val(d.promise); }
						if (d.benefits) {
							var bText = $.isArray(d.benefits) ? d.benefits.join("\n") : d.benefits;
							$('#lpgen_benefits').val(bText);
						}
						if (d.manual_mofu_topics) {
							var mText = $.isArray(d.manual_mofu_topics) ? d.manual_mofu_topics.join("\n") : d.manual_mofu_topics;
							$('#lpgen_manual_mofu_topics').val(mText);
						}
						if (d.manual_tofu_topics) {
							var tText = $.isArray(d.manual_tofu_topics) ? d.manual_tofu_topics.join("\n") : d.manual_tofu_topics;
							$('#lpgen_manual_tofu_topics').val(tText);
						}
						if (d.target_areas) {
							var aText = $.isArray(d.target_areas) ? d.target_areas.join("\n") : d.target_areas;
							$('#lpgen_target_areas').val(aText);
						}
						// Open accordion so user sees the generated preview
						$('details').prop('open', true);
						showResult('✨ Rekomendasi AI berhasil diisikan ke form! Anda bisa mengubahnya sebelum mulai generate.', true);
					} else {
						showResult(response && response.message ? response.message : 'Gagal memproses rekomendasi AI', false);
					}
					return;
				}

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
				if (('start-campaign' === action || 'restart-campaign' === action || 'publish-campaign' === action) && response && response.ok) {
					queueStarted = true;
					updateCampaignRow(campaign, {status: 'publish-campaign' === action ? 'publishing' : 'generating', total: response.jobs, done_count: 0, failed: 0});
					runQueueLoop(campaign, response.jobs || 6, releaseButton);
					return;
				}
				if (response && response.ok && ('publish-campaign' === action || 'regenerate' === action || 'regenerate-image' === action || 'generate-entities' === action || 'generate-tag-content' === action || 'generate-framework' === action)) {
					window.setTimeout(function () { window.location.reload(); }, 900);
				}
			})
			.fail(function (xhr, state) {
				showResult(requestError(xhr, state), false);
			})
			.always(function () {
				if (!queueStarted) { releaseButton(); }
			});
	});

	function requestError(xhr, state) {
		if (xhr && xhr.responseJSON && xhr.responseJSON.message) { return xhr.responseJSON.message; }
		if (xhr && (xhr.status === 401 || xhr.status === 403 || String(xhr.responseText).trim() === '-1')) { return 'Sesi atau izin sudah tidak berlaku. Muat ulang halaman dan coba lagi.'; }
		if (state === 'timeout') { return 'Server belum memberi respons. Pekerjaan mungkin masih berjalan; gunakan Proses Antrean untuk melanjutkan, bukan Generate ulang.'; }
		return 'Permintaan gagal' + (xhr && xhr.status ? ' (HTTP ' + xhr.status + ')' : '') + '. Periksa koneksi dan Activity Log, lalu coba lagi.';
	}

	function updateCampaignRow(campaign, response) {
		var $row = $('[data-lpgen-campaign="' + Number(campaign) + '"]');
		if (!$row.length) { return; }
		var labels = {draft: 'Belum digenerate', generating: 'Sedang digenerate', review: 'Siap direview', publishing: 'Sedang diterbitkan', published: 'Selesai diterbitkan', failed: 'Perlu diperiksa'};
		if (response.status) { $row.find('[data-lpgen-field="status"]').text(labels[response.status] || response.status); }
		if (typeof response.total === 'number') {
			var done = response.done_count || 0, percent = response.total ? Math.round(done / response.total * 100) : 0;
			$row.find('[data-lpgen-field="queue"]').text(done + '/' + response.total + ' selesai · ' + (response.failed || 0) + ' gagal');
			$row.find('.lpgen-progress').attr('aria-valuenow', percent).find('.lpgen-progress__bar').css('width', percent + '%');
			$row.find('[data-lpgen-field="percent"]').text(percent + '%');
		}
	}

	function runQueueLoop(campaign, totalJobs, onStop) {
		if (activeRunners[campaign]) { if (onStop) { onStop(); } return; }
		activeRunners[campaign] = true;
		function stop() { if (ticker) { window.clearInterval(ticker); ticker = 0; } if ($countdown.length) { $countdown.text(''); } delete activeRunners[campaign]; if (onStop) { onStop(); } }

		var $runner = $('#lpgen-live-runner');
		var $bar = $('#lpgen_runner_bar');
		var $status = $('#lpgen_runner_status');
		var $countdown = $('#lpgen_runner_countdown');
		var $details = $('#lpgen_runner_details');
		var nextAt = 0;
		var nextLocal = '';
		var resTimezone = '';
		if ($runner.length) {
			$runner.show();
		} else {
			showResult('Sedang memproses antrean konten...', true);
		}

		function renderCountdown() {
			if (!$countdown.length) { return; }
			if (!nextAt || Date.now() / 1000 >= nextAt) { $countdown.text(''); return; }
			var s = Math.ceil(nextAt - Date.now() / 1000);
			var m = Math.floor(s / 60);
			var r = s % 60;
			$countdown.text('Jeda berikutnya: ' + (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r + (nextLocal ? ' · ' + nextLocal + ' ' + (resTimezone || '') : ''));
		}
		var ticker = window.setInterval(renderCountdown, 1000);

		function renderDetails(items) {
			if (!$details.length || !$.isArray(items)) { return; }
			$details.empty();
			$.each(items, function (_, item) {
				var mark = item.status === 'done' ? '✓' : (item.status === 'failed' ? '✕' : '…');
				var label = (item.title || (item.kind + ' #' + item.item_id));
				var line = mark + ' ' + label + ' — ' + item.kind + ' (' + item.status + ')';
				if (item.run_at_local) { line += ' · ' + item.run_at_local; }
				if (item.error) { line += ' · ' + item.error; }
				$('<li/>').text(line).appendTo($details);
			});
		}

		var total = totalJobs || 6;
		function step() {
			call('process_queue', { campaign: campaign })
				.done(function (res) {
					if (res) { updateCampaignRow(campaign, res); }
					if (res && res.next_run_at) { nextAt = Number(res.next_run_at); } else { nextAt = 0; }
					nextLocal = res && res.next_run_local ? res.next_run_local : '';
					resTimezone = res && res.timezone_label ? res.timezone_label : '';
					if (res && res.items) { renderDetails(res.items); }
					renderCountdown();
					if (!res || !res.ok) {
						if (res && res.busy) {
							showResult(res.message || 'Worker sedang memproses antrean.', true);
							if ($status.length) { $status.text(res.message || 'Menunggu worker.'); }
							window.setTimeout(step, 1500);
							return;
						}
						showResult(res && res.message ? res.message : 'Error processing queue', false);
						if ($status.length) { $status.text(res && res.message ? res.message : 'Error'); }
						stop();
						return;
					}
					if (res.waiting && res.next_run_at > Date.now() / 1000 + 1) {
						if ($status.length) { $status.text(res.message || 'Menunggu jeda antar konten.'); }
						window.setTimeout(step, 1000);
						return;
					}
					if (res.done && !(res.running > 0)) {
						stop();
						if ($bar.length) { $bar.css('width', '100%'); }
						var finishedMessage = res.failed > 0 ? 'Antrean selesai. ' + res.failed + ' item gagal atau ditahan. Periksa Review dan Activity Log.' : 'Selesai! Semua konten berhasil dibuat.';
						if ($status.length) { $status.text(finishedMessage); }
						showResult(finishedMessage, !(res.failed > 0));
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
					window.setTimeout(step, res.job_kind ? 300 : 3000);
				})
				.fail(function (xhr, state) {
					stop();
					showResult(requestError(xhr, state) + ' Gunakan Proses Antrean untuk melanjutkan.', false);
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
		var queueStarted = false;
		function releaseButton() { $button.prop('disabled', false).text(original); }
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
		var queueStarted = false;
		function releaseButton() { $button.prop('disabled', false).text(original); }
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
	$(document).on('click', '[data-lpgen-preview-download]', function () {
		var frame = document.getElementById($(this).attr('data-lpgen-preview-download'));
		if (!frame) { return; }
		var previewSource = (frame.getAttribute('srcdoc') || '').replace('<base href="about:srcdoc">', '');
		var blob = new Blob([previewSource], {type: 'text/html;charset=utf-8'});
		var url = URL.createObjectURL(blob), link = document.createElement('a');
		link.href = url; link.download = 'lp-generator-preview.html'; link.click();
		window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
	});
	$(document).on('click', '[data-lpgen-slot-picker]', function () {
		if (!window.wp || !wp.media) { return; }
		var target = $(this).attr('data-lpgen-slot-picker');
		var picker = wp.media({title: 'Pilih gambar untuk section ini', button: {text: 'Gunakan gambar'}, multiple: false, library: {type: 'image'}});
		picker.on('select', function () { var selected = picker.state().get('selection').first(); if (selected) { $('#' + target).val(selected.id).trigger('change'); $('#' + target + '_label').text(selected.get('title') || 'Gambar terpilih'); } });
		picker.open();
	});
	$(document).on('click', '[data-lpgen-slot-clear]', function () { var target = $(this).attr('data-lpgen-slot-clear'); $('#' + target).val(0).trigger('change'); $('#' + target + '_label').text('Belum dipilih'); });
})(jQuery);
