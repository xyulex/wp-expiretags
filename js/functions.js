jQuery(document).ready(function(){
        jQuery( '.datepicker' ).datepicker({
            showOn: 'button',
            buttonImage: ET.plugins_url + '/images/calendar.gif',
            buttonImageOnly: true,
            buttonText: '',
            dateFormat: 'yy-mm-dd',
            minDate: 1
        });

        jQuery('.expire-btn').on('click', function(e) {
            e.stopImmediatePropagation();
        	tagID      = jQuery(this).attr("data-id");
        	tagName    = jQuery(this).attr("data-name");

        	if (confirm(ET.delete_confirm)) {
	           jQuery("#" + tagID )
                   .val('1970-01-01')
                   .css('color', 'white');
               jQuery("#submit").click();
            } else {
            return false;
            }
        });

});