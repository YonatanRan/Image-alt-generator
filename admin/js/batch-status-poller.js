/**
 * Batch status poller: one check on load; if batches in progress, poll every 60s until all done.
 */
(function() {
	'use strict';

	var POLL_INTERVAL_MS = 60000; // 1 minute
	var pollTimerId = null;

	function getNonce() {
		return typeof rrBatchPoller !== 'undefined' && rrBatchPoller.nonce ? rrBatchPoller.nonce : '';
	}

	function checkAnyInProgress(done) {
		var url = typeof ajaxurl !== 'undefined' ? ajaxurl : '';
		if (!url || !getNonce()) return;

		var formData = new FormData();
		formData.append('action', 'rr_any_batches_in_progress');
		formData.append('nonce', getNonce());

		fetch(url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				console.log('Data is: ',data);
				if (data.success && data.data && data.data.in_progress) {
					if (typeof done === 'function') done(true);
				} else {
					if (typeof done === 'function') done(false);
				}
			})
			.catch(function() {
				if (typeof done === 'function') done(false);
			});
	}

	function checkAndProcessBatches(done) {
		var url = typeof ajaxurl !== 'undefined' ? ajaxurl : '';
		if (!url || !getNonce()) {
			if (typeof done === 'function') done(true);
			return;
		}

		var formData = new FormData();
		formData.append('action', 'rr_check_and_process_batches');
		formData.append('nonce', getNonce());

		fetch(url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				console.log('Date is: ',data);
				var stillInProgress = data.success && data.data && data.data.still_in_progress === true;
				if (typeof done === 'function') done(stillInProgress);
			})
			.catch(function() {
				if (typeof done === 'function') done(true);
			});
	}

	function startPolling() {
		if (pollTimerId) return;
		pollTimerId = setInterval(function() {
			checkAndProcessBatches(function(stillInProgress) {
				if (!stillInProgress && pollTimerId) {
					clearInterval(pollTimerId);
					pollTimerId = null;
					if (typeof rrBatchPoller !== 'undefined' && rrBatchPoller.onAllDone === 'reload') {
						var batchesPage = document.querySelector('[data-rr-batch-jobs-page]');
						if (batchesPage) window.location.reload();
					}
				}
			});
		}, POLL_INTERVAL_MS);
	}

	function stopPolling() {
		if (pollTimerId) {
			clearInterval(pollTimerId);
			pollTimerId = null;
		}
	}

	function onLoad() {
		checkAnyInProgress(function(inProgress) {
			if (inProgress) startPolling();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onLoad);
	} else {
		onLoad();
	}

	window.rrBatchStopPolling = stopPolling;
	window.rrBatchStartPolling = startPolling;
})();
