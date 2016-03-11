jQuery(document).ready(function(){
        jQuery( '.datepicker' ).datepicker({
            showOn: 'button',
            buttonImage: URLS.plugins_url + '/images/calendar.gif',
            buttonImageOnly: true,
            buttonText: '',
            dateFormat: 'yy-mm-dd',
            minDate: 1
        });

        jQuery('.expire-btn').click(function(){
        	tagID = jQuery(this).attr("data-id");
        	tagName = jQuery(this).attr("data-name");

        	if (confirm("Remove the expiration date for the tag " + tagName + "?")) {
	           jQuery("#" + tagID ).val('1970-01-01');
	           jQuery("#expiretags").submit();
        	} else {
       		   return false;
            }
        });
});