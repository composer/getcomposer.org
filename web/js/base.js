/*	------------------------------------------------------

	Base.js
	
	The master JS file.
	
	Written by Joey Emery.
	
	Contents:
		--- Document ready
		--- Latest Commit
		--- Mobile Navigation
		--- Resizer
	
------------------------------------------------------ */

/* ---		Document Ready		--- */
$(document).ready(function() {
	// Controls the mobile navigation stack.
	MobileNavigation.init();
	
	// Get the stuff from Github.
	LatestCommit.init('https://api.github.com/repos/composer/composer/commits');
	
	// Resizer.
	Resizer.init(new Array('MobileNavigation.close()'));
	
	// Time since something happened.
	$('.realtime_time').timeago();
	
	// Prettier scrolling.
	$('body').on('click', 'a.anchor, .toc li a', function(e) {
		e.preventDefault();
		
		$('body').animate({
			'scrollTop': ($($(this).attr('href')).offset().top - 70)
		}, 1000);
	});
});

/* ---		Latest Commit		--- */
var LatestCommit = {
	location: false,
	
	init: function(location) {
		if($('#latest_commit').length > 0) {
			this.location = location;
			this.getLatestCommit();
		}
	},
	
	getLatestCommit: function() {
		$.get(this.location, function(data) {
			var commit = data[0];
			
			$('#latest_commit #post_info a:first span:last').text(commit.commit.author.name);
			$('#latest_commit #post_info a:last span:last').text($.timeago(commit.commit.author.date));
			$('#latest_commit p').text(commit.commit.message);
			$('#latest_commit').removeClass('loading');
		});
	}
}

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

/* ---		Resizer		--- */
var Resizer = {
	width: false,
	
	init: function(functions) {
		this.calculate(functions);
	},
	
	calculate: function(functions) {
		this.width = $(window).width();
		var obj = this;
		$(window).resize(function(e) {
			obj.width = $(window).width();
			if(functions && functions.length > 0) {
				$.each(functions, function(key, value) {
					eval(value);
				});
			}
		});
	}
}