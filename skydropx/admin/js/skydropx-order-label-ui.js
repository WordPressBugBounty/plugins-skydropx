(function ($) {
	'use strict';

	$(function () {
		$('a.skydropx_print_label, a.skydropx-print-label').on('click', function (e) {
			const href = this.href;
			if (!href) return;

			e.preventDefault();
			window.open(href, '_blank', 'noopener,noreferrer');
		});
	});
})(jQuery);


