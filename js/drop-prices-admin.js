jQuery(document).ready(function(){
	var calendar_image = '';
	if (typeof woocommerce_writepanel_params != 'undefined'){
		calendar_image = woocommerce_writepanel_params.calendar_image;
	} else if (typeof woocommerce_admin_meta_boxes != 'undefined'){
		calendar_image = woocommerce_admin_meta_boxes.calendar_image;
	}
	
	jQuery('.datetimepicker').datetimepicker(
		{defaultDate: "",
		dateFormat: "yy-mm-dd",
		numberOfMonths: 1,
		showButtonPanel: true,
		showOn: "button",
		buttonImage: calendar_image,
		buttonImageOnly: true
		});	
		
	var productType = jQuery('#product-type').val();
	if (productType=='auction'){
		jQuery('.show_if_simple').show();
		jQuery('.inventory_options').hide();
	}
	jQuery('#product-type').live('change', function(){
		if  (jQuery(this).val() =='auction'){
			jQuery('.show_if_simple').show();
			jQuery('.inventory_options').hide();
		}
	});
	jQuery('#_virtual').addClass('show_if_auction');
	jQuery('label[for="_virtual"]').addClass('show_if_auction');
	jQuery('#_downloadable').addClass('show_if_auction');
	jQuery('label[for="_downloadable"]').addClass('show_if_auction');
});