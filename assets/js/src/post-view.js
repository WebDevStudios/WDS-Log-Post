jQuery( document ).ready( function( $ ) {
	// Init some things
	var progress_value = parseInt( wds_log_post.progress_value, 10 ) || ''; // Make sure < 1 is 1.
	var progress_html  = '';
	var jqProgress;

	/**
	 * Replace the title and post content with readonly fields
	 */
	$('input[name="post_title"]').replaceWith( function() {
		return '<h2>' + this.value + '</h2>';
	});

	if ( progress_value ) {
		$(document).on( 'heartbeat-tick', tick );
		var comp_title = progress_value + '% ' + wds_log_post.messages.complete;

		progress_html = [
			'<div id="wds-log-progress-holder">',
				'<div class="spinner" style="visibility:visible; float: left;"></div>',
				'<strong style="float:left; margin: 5px" id="wds-log-progress-label">',
					wds_log_post.messages.current_task_progress + ':',
				'</strong>',
				'<div style="float: right" class="media-progress-bar" id="wds_log_progress" title="' + comp_title + '">',
				'</div>',
			'</div>'
		].join('');
	} else {
		$('#wds-log-progress-holder').remove();
	}

	$('textarea.wp-editor-area').replaceWith( function() {
		var height = parseFloat( 0.6 * $(window).outerHeight(), 10 );
		return [
			'<pre class="wp-editor-area">',
				wds_log_post.tax_info,
				'<textarea id="wds-log-content" style="width:100%;min-height:' + height + 'px" readonly="readonly">',
					$(this).val(),
				'</textarea>',
			'</pre>',
			progress_html
		].join('');
	});
		
	jqProgress = $( '#wds_log_progress' );

	// Kick it off.
	setStatus( progress_value, wds_log_post.progress_aborted == 'true' );

	/**
	 * Sets the current progress status.
	 *
	 * @param Int  percent The percentage complete.
	 * @param bool aborted Whether the request is aborted.
	 */
	function setStatus( percent, aborted ) {
		if ( ! percent ) {
			return;
		}

		jqProgress.progressbar({value: percent }).attr( 'title', percent + ' %' + wds_log_post.messages.complete );

		if ( percent >= 100 ) {
			complete( aborted ? true : false );
		} else if ( aborted ) {
			complete( true );
		}
	}

	/**
	 * Runs with each "tick" of the WP heartbeat.
	 *
	 * @param Event  e    The tick event.
	 * @param Object data The data from the XHR.
	 */
	function tick(e, data) {
		if ( data.wdslp_progress && data.wdslp_progress <= 100 ) {
			setStatus( parseInt( data.wdslp_progress, 10 ), data.wdslp_progress_aborted );
		}

		if ( data.wdslp_content ) {
			$('#wds-log-content' ).val( data.wdslp_content );
		}
	}

	/**
	 * Completes the progress bar on abortion or completion.
	 *
	 * @param Boolean aborted Whether the request was aborted.
	 */
	function complete( aborted ) {
		$('#wds-log-progress-holder .spinner').remove();
		var dashClass = aborted ? 'dashicons-no' : 'dashicons-yes';
		var message = aborted ? 'process_aborted' : 'process_complete';

		$('#wds-log-progress-label').text( wds_log_post.messages[ message ] ).addClass('dashicons-before ' + dashClass);
		$(document).off( 'heartbeat-tick', tick );
	}
});
