(function($,mw) {
	"use strict";
	window.WH = window.WH || {};
	window.WH.SummaryEditTool = {
		tool_url: '/Special:SummaryEditTool',

		openEditUI: function() {
			if ($('#summary_edit_tool').length) return;

			var url = this.tool_url+'?action=get_edit_box&page_title='+mw.config.get('wgTitle');

			$.getJSON(url, $.proxy(function(data) {
				if (data.html.length) {
					this.showEditUI(data.html);
				}
			},this));
		},

		closeEditUI: function(callback_function) {
			$('#summary_edit_tool').slideUp(function() {
				$(this).remove();
				if (callback_function) callback_function();
			});
		},

		showEditUI: function(html) {
			$('#intro').after(html);
			this.addHandlers();
			$('#summary_edit_tool').slideDown(function() {
				$('html, body').animate({scrollTop: $(this).position().top}, 300);
			});
		},

		addHandlers: function() {
			$('.summary_edit_close').on('click', function() {
				WH.SummaryEditTool.closeEditUI();
				return false;
			});

			$('#set_submit').click(function() {
				$(this).fadeOut();
				WH.SummaryEditTool.submitSummary();
				return false;
			});
		},

		submitSummary: function() {
			if (!this.validateForm()) return;

			$.post(
				this.tool_url,
				{
					action: 'submit',
					page_title: mw.config.get('wgTitle'),
					content: $('#set_content').val(),
					last_sentence: $('#set_last_sentence').val(),
					show_at_top: $('#set_checkbox').is(':checked') ? 1 : 0
				},
				$.proxy(function(result) {
					this.showSubmitResult(result);
				}, this),
				'json'
			);
		},

		validateForm: function() {
			var err = '';

			if ($('#set_content').val().trim() == '') {
				err = mw.message('set_err_no_summary').text();
			}
			// else if ($('#set_last_sentence').val().trim() == '') {
			// 	err = mw.message('set_err_no_last_sentence').text();
			// }

			if (err != '') this.showError(err);

			return err == '';
		},

		showError: function(err) {
			$('#summary_edit_err').slideUp(function() {
				$(this).html(err).slideDown();
			});
		},

		showSubmitResult: function(result) {
			if (result.success) {
				this.closeEditUI(this.updateSummarySection);
			}
			else {
				$('#summary_edit_main').html(result.text);
				$('#set_post_submit_buttons').show();
			}
		},

		updateSummarySection: function() {
			$.getJSON(
				'api.php?action=parse&format=json&page=Summary:'+mw.config.get('wgTitle'),
				$.proxy(function(data) {
					if (data && data.parse) {
						var summary = data.parse.text['*'];

						if ($('#quick_summary_section').length) {
							$('#quick_summary_section').hide();
						}
						else {
							$('#temp_summary').attr('id','quick_summary_section');
						}

						$('#quick_summary_section').find('.section_text p').remove();
						$('#quick_summary_section .clearall').before(summary);

						var at_top = $('#summary_position').hasClass('sumpos_top');
						if (at_top) {
							$('#intro').after($('#quick_summary_section'));
						}
						else {

							if ($('div.video.section').length)
								$('div.video.section').after($('#quick_summary_section'));
							else if ($('div.qa.section').length)
								$('div.qa.section').after($('#quick_summary_section'));
							else
								$('div.steps:last').after($('#quick_summary_section'));

						}

						$('#quick_summary_section').slideDown();

						$('html, body').animate({scrollTop: $('#quick_summary_section').position().top}, 300);
					}
				},this)
			);
		}
	}
})(jQuery, mw);
