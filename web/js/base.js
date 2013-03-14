/*	------------------------------------------------------

	Base.less
	
	The master JS file.
	
	Written by Joey Emery.
	Last modified: 13/03/2013 13:23PM.
	--- By: Joey Emery.
	
	Contents:
		--- Document ready
	
------------------------------------------------------ */

/* ---		Document Ready		--- */
$(document).ready(function() {
	$('.realtime_time').timeago();

	MobileNavigation.init();
});

/* ---		Mobile Navigation	--- */
var MobileNavigation = {
	distance:	'-250px',
	speed:		400,
	is_open: 	false,
	
	/* Sets everything up and fires relevant methods */
	init: function() {
		$('#mini_nav').css('height', $('body').innerHeight());
		this.bind();
	},
	
	/* Toggle between open/close */
	toggle: function() {
		if(this.is_open) {
			this.close();
		} else {
			this.open();
		}
	},
	
	/* Opens the navigation */
	open: function() {
		$('#slide_wrapper').css('width', $('body').innerWidth());
		
		$('#slide_wrapper').animate({
			'margin-left'	:	this.distance
		}, this.speed, function() {
			$('#mini_nav').css('z-index', 100);
		});
		this.is_open = true;
	},
	
	/* Closes the navation */
	close: function() {	
		$('#mini_nav').css('z-index', 9);
		
		$('#slide_wrapper').animate({
			'margin-left'	:	0
		}, this.speed, function() {			
			$('#slide_wrapper').css('width', 'auto');
		});
		this.is_open = false;
	},
	
	/* Bind elements to their relative methods */
	bind: function() {
		var instance = this;
		
		$('#header ul').on('click', 'li.show_menu a', function(e) {
			e.preventDefault();
			instance.toggle();
		});
	}
}