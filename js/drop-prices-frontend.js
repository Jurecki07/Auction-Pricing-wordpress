jQuery(document).ready(function($){
	$( ".drop-price-countdown" ).each(function( index ) {
		var time 	= $(this).data('time');
		var format 	= $(this).data('format');
		
		if(format == ''){
			format = 'yowdHMS';
		}
		$(this).dpcountdown({
			until:   $.dpcountdown.UTCDate((drop_prices_data.gtm_offset*60)-(new Date().getTimezoneOffset()),new Date(time*1000)),
			format: format, 
			
			onExpiry: refreshtime,
			expiryText: '<div class="over">'+drop_prices_data.finished+'</div>'
		});
			 
	});
});

function refreshtime(){
	jQuery('.drop-price-counter > p').hide();
	setTimeout(function () {
			location.reload();
	}, 1000 );
		
}