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
    
  
  
  $("#featured").orbit();
    

  // UNCOMMENT THE LINE YOU WANT BELOW IF YOU WANT IE8 SUPPORT AND ARE USING .block-grids
  // $('.block-grid.two-up>li:nth-child(2n+1)').css({clear: 'both'});
  // $('.block-grid.three-up>li:nth-child(3n+1)').css({clear: 'both'});
  // $('.block-grid.four-up>li:nth-child(4n+1)').css({clear: 'both'});
  // $('.block-grid.five-up>li:nth-child(5n+1)').css({clear: 'both'});
  
  
  //Form Validations
  $.validator.addMethod("valueNotEquals", function(value, element, arg){
    return arg != value;
  }, "Value must not equal arg.");

  $("#application_form select[name='app_birthdate[]']").each(function() {
    $(this).addClass("required");
  });
  
  $('#application_form').validate({
    rules: {
     'app_birthdate[]': { valueNotEquals: "0" }
    },
    messages: {
     'app_birthdate[]': {
      valueNotEquals: "Please select your birthdate."
     }
    },
    errorPlacement: function(error, element) {
      if (element.attr("name") == "app_birthdate[]") {
        error.insertAfter(element.parent());
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
      }
    }
  });
  
  $('#nav-bar-login form').validate();
  $('#login-form').validate();
  $('#forgot_password_form').validate();
  
  $('#app_newsletter').click(function() {
    $(this).parent().next().toggleClass('require');
    $(this).parent().next().next().toggleClass('required');
  });
  

  // Hide address bar on mobile devices
  if (Modernizr.touch) {
    $(window).load(function () {
      setTimeout(function () {
        window.scrollTo(0, 1);
      }, 0);
    });
  }

})(jQuery, this);
