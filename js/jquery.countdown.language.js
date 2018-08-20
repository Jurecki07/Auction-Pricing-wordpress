/* http://keith-wood.name/countdown.html
 * Croatian Latin initialisation for the jQuery countdown extension
 * Written by Dejan Broz info@hqfactory.com (2011) */
(function($) {
	$.dpcountdown.regional['hr'] = {
		labels: [drop_prices_language_data.labels.Years, drop_prices_language_data.labels.Months, drop_prices_language_data.labels.Weeks, drop_prices_language_data.labels.Days, drop_prices_language_data.labels.Hours, drop_prices_language_data.labels.Minutes, drop_prices_language_data.labels.Seconds],
		labels1: [drop_prices_language_data.labels1.Year, drop_prices_language_data.labels1.Month, drop_prices_language_data.labels1.Week, drop_prices_language_data.labels1.Day, drop_prices_language_data.labels1.Hour, drop_prices_language_data.labels1.Minute, drop_prices_language_data.labels1.Second],
		
		compactLabels: [drop_prices_language_data.compactLabels.y, drop_prices_language_data.compactLabels.m, drop_prices_language_data.compactLabels.w, drop_prices_language_data.compactLabels.d],
		whichLabels: function(amount) {
			return (amount == 1 ? 1 : (amount >= 2 && amount <= 4 ? 2 : 0));
		},
		digits: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
		timeSeparator: ':', isRTL: false};
	$.dpcountdown.setDefaults($.dpcountdown.regional['hr']);
})(jQuery);
