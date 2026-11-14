/**
 * Commerce UX Studio - WP Admin Interactive Controller (Live AJAX Batch Sync)
 *
 * File Path: assets/js/admin-studio.js
 */
(function ($) {
	'use strict';

	$(function () {
		const $bulkBtn = $('#cux-run-bulk-sync');
		const $statusBox = $('#cux-bulk-sync-status');

		function runBatchSync(offset) {
			$.ajax({
				url: (typeof cuxAdminVars !== 'undefined' && cuxAdminVars.ajaxUrl) ? cuxAdminVars.ajaxUrl : ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cux_run_bulk_sync',
					nonce: (typeof cuxAdminVars !== 'undefined') ? cuxAdminVars.nonce : '',
					offset: offset
				},
				success: function (response) {
					if (response && response.success && response.data) {
						const data = response.data;
						const percent = data.total > 0 ? Math.min(100, Math.round((data.processed / data.total) * 100)) : 100;

						$statusBox.html(
							'<div style="margin-bottom:6px;font-weight:600;color:#1e293b;">' + data.message + ' (' + percent + '%)</div>' +
							'<div style="width:100%;height:10px;background:#e2e8f0;border-radius:6px;overflow:hidden;">' +
							'<div style="width:' + percent + '%;height:100%;background:#2271b1;transition:width 0.25s ease;"></div>' +
							'</div>'
						);

						if (data.has_more) {
							runBatchSync(data.next_offset);
						} else {
							$bulkBtn.prop('disabled', false).text('Sync All Products Now');
						}
					} else {
						const errMsg = (response && response.data && response.data.message) ? response.data.message : 'Error running batch sync.';
						$statusBox.html('<strong style="color:#dc2626;">' + errMsg + '</strong>');
						$bulkBtn.prop('disabled', false).text('Sync All Products Now');
					}
				},
				error: function () {
					$statusBox.html('<strong style="color:#dc2626;">Connection error while syncing products. Please try again.</strong>');
					$bulkBtn.prop('disabled', false).text('Sync All Products Now');
				}
			});
		}

		$bulkBtn.on('click', function (e) {
			e.preventDefault();
			$bulkBtn.prop('disabled', true).text('Syncing Catalog...');
			$statusBox.show().html('<strong>Starting Smart Stock & Gallery Sync...</strong>');
			runBatchSync(0);
		});
	});
})(jQuery);
