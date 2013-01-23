;(function ($, window, undefined) {
  'use strict';

  var $doc = $(document),
      Modernizr = window.Modernizr;

  
  $.fn.foundationAlerts           ? $doc.foundationAlerts() : null;
  $.fn.foundationAccordion        ? $doc.foundationAccordion() : null;
  $.fn.foundationTooltips         ? $doc.foundationTooltips() : null;
  $('input, textarea').placeholder();
  
  
  $.fn.foundationButtons          ? $doc.foundationButtons() : null;
  
  
  $.fn.foundationNavigation       ? $doc.foundationNavigation() : null;
  
  
  $.fn.foundationTopBar           ? $doc.foundationTopBar({breakPoint: 940}) : null;
  
  
  $.fn.foundationMediaQueryViewer ? $doc.foundationMediaQueryViewer() : null;
  
    
  $.fn.foundationTabs             ? $doc.foundationTabs() : null;
    
  
  


  // UNCOMMENT THE LINE YOU WANT BELOW IF YOU WANT IE8 SUPPORT AND ARE USING .block-grids
  // $('.block-grid.two-up>li:nth-child(2n+1)').css({clear: 'both'});
  // $('.block-grid.three-up>li:nth-child(3n+1)').css({clear: 'both'});
  // $('.block-grid.four-up>li:nth-child(4n+1)').css({clear: 'both'});
  // $('.block-grid.five-up>li:nth-child(5n+1)').css({clear: 'both'});
  
  
  // Hide address bar on mobile devices
  if (Modernizr.touch) {
    $(window).load(function () {
      setTimeout(function () {
        window.scrollTo(0, 1);
      }, 0);
    });
  }
  

/////////////////////////////////////  
//	Form Validations
///////////////////////////////////// 

  $.validator.addMethod("valueNotEquals", function(value, element, arg){
    return arg != value;
  }, "Value must not equal arg.");

  $("#application_form select[name='app_birthdate[]']").each(function() {
    $(this).addClass("required");
  });
  
  $('#application_form').validate({
    rules: {
     'app_birthdate[]': { 
     	valueNotEquals: "0" 
     },
     app_email : {
     	email: true
     }
    },
    messages: {
     'app_birthdate[]': {
      valueNotEquals: "Please select your birthdate."
     }
    },
    errorPlacement: function(error, element) {
      if (element.attr("name") == "app_birthdate[]") {
        error.insertAfter(element.parent());
      } else {
      	error.insertAfter(element);
      }
    }
  });
  
  $('#account_form').validate({
    rules: {
      username: { 
        email: true 
      },
      screen_name: { 
        minlength: 5 
      },
      password: { 
        minlength: 5 
      },
      password_confirm: {
        equalTo: "#password"
      }
    },
    errorPlacement: function(error, element) {
      if (element.attr("name") == "accept_terms") {
        error.insertAfter(element.parent());
      } else {
      	error.insertAfter(element);
      }
    }
  });
  
  $('#account_edit_profile').validate({
  	rules: {
      screen_name: { 
        minlength: 5 
      }
    },
    errorPlacement: function(error, element) {
      if ((element.attr("name") == "bday_m") || (element.attr("name") == "bday_d") || (element.attr("name") == "bday_y")) {
        $('.label_birthdate_error').html('Select your birthdate');
      } else {
      	error.insertAfter(element);
      }
    }
  });
  
  $('#account_edit_profile #bday_m, #account_edit_profile #bday_d, #account_edit_profile #bday_y').change(function(){
  	if (($("#account_edit_profile #bday_m").val() != "") && ($("#account_edit_profile #bday_d").val() != "") && ($("#account_edit_profile #bday_y").val() != "")) {
  		$('.label_birthdate_error').html('');
  	}
  });
  
  $('#account_edit_settings').validate({
    rules: {
      email: { 
        email: true 
      },
      password: { 
      	required: false,
        minlength: 5 
      },
      password_confirm: {
      	required: false,
        equalTo: "#password"
      }
    }
  });
  
  $('#login-form').validate({
    rules: {
      username: { 
        email: true 
      },
      password: { 
        minlength: 5 
      }
    }
  });

  $('#forgot_password_form').validate({
    rules: {
      email: { 
        email: true 
      }
    }
  });
  
  $('#newsletter_form').validate({
    rules: {
      app_email: { 
        email: true 
      }
    }
  });
  
  $('#app_newsletter').click(function() {
    $(this).parent().next().toggleClass('require');
    $(this).parent().next().next().toggleClass('required');
  });
  
  $('#nav-bar-login form').validate();
  
  $('#lost-pass-form').validate();
  
  $('#contact-form').validate({
    rules: {
      email: { 
        email: true 
      }
    }
  });
  
  
  
/////////////////////////////////////  
//	Form Value Check
///////////////////////////////////// 

	function checkEmail() {
		$.ajax({
			type: 'GET',
			dataType: 'text',
			url: '/_ajax/email_check/'+$(this).val(),
			success: function(data){
				if (data > 0) {
					$("label.username_return").html("Not Available");
					$("label.username_return").removeClass('available').addClass('unavailable');
								
				} else {
					$("label.username_return").html("Available");
					$("label.username_return").removeClass('unavailable').addClass('available');						
				}
				if ($('#account_username').hasClass('error')) {
					$("label.username_return").html("");
				}
				if ($('#account_username').val() == "") {
					$("label.username_return").html("");
				}
				if ($('#account_username').val() == $('#account_username').data('current')) {
					$("label.username_return").html("");
				}
			}
		})
		
		return false;
	};
	
	function checkScreenname() {
		$.ajax({
			type: 'GET',
			url: '/_ajax/screen_name_check/'+$(this).val(),
			success: function(data){
				if (data > 0) {
					$("label.screen_name_return").html("Not Available")
					$("label.screen_name_return").removeClass('available').addClass('unavailable');
				} else {					
					$("label.screen_name_return").html("Available")
					$("label.screen_name_return").removeClass('unavailable').addClass('available');
				}
				if ($('#account_screen_name').hasClass('error')) {
					$("label.screen_name_return").html("");
				}
				if ($('#account_screen_name').val() == "") {
					$("label.screen_name_return").html("");
				}
				if ($('#account_screen_name').val() == $('#account_screen_name').data('current')) {
					$("label.screen_name_return").html("");
				}
			}
		})
		return false;
	};
  
	$("#account_username").bind('blur', checkEmail);
	$("#account_screen_name").bind('blur', checkScreenname);
	$("#account_form #account_username").focus();
  


/////////////////////////////////////  
//	Favorites
/////////////////////////////////////   
  
  $('a.Favorites_Save') .click (function() {
      var link = $(this).attr('href')
      $('.Favorites_Status').load(link, function() {
              $('.Favorites_Delete').show();
          });
      $(this).hide();
      return false;
  });
  $('a.Favorites_Save_Full') .click (function() {
      var link = $(this).attr('href')
      $('.Favorites_Status').load(link, function() {
              $('.Favorites_Delete').show();
          });
      $(this).hide();
      return false;
  });
  
  $('a.Favorites_Delete') .click (function() {
      var link = $(this).attr('href')
      $('.Favorites_Status').load(link, function() {
              $('.Favorites_Save').show();
          });
      $(this).hide();
      return false;
  });
  
  
/*   view more */
  
  $('.event .view-more').click(function() {
  	$(this).prev().toggleClass('hidden');
  });
  
  
/*   galleries */
  $("#gallery").orbit({ 
  	animation: 'fade',
  	startClockOnMouseOut: true,
  	timer: false,
  	bullets: true,
  	captions: true ,
  });
  
  $('#main-featured').cycle();
  $('#event-slideshow').cycle({
  	timeout: 0,
  	pager: '#event-slideshow-pager',
  	pagerAnchorBuilder: function(idx, slide) { 
        // return selector string for existing anchor 
        return '#event-slideshow-pager li:eq(' + idx + ') a'; 
    }
  }); 
  
  $('.event #event-slideshow-pager li').click(function(){
  	$('.event #event-slideshow').slideDown();
  })
	
	function hideSlideshow() {
		if ($('#event-gallery').hasClass('has-video')) {
			$('#event-slideshow').hide();
		}
	}
	
	hideSlideshow();

})(jQuery, this);
