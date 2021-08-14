jQuery( function( $ ) {
	"use strict";
	$('body').on('change', '.thm_add_insurance', function() {
		if (this.checked) {
			const modal = document.getElementById('thm_insurance_modal');
			modal.style.display = "block";

			$( '.thm_insurance_quote' ).val(this.dataset.quotation);
			$( '.thm_cart_item_key' ).val(this.dataset.cartItemKey);

			checkbox_ref = this;
		} else {
			$( '.thm_insurance_quote' ).val('0');
			$( '.thm_cart_item_key' ).val(this.dataset.cartItemKey);

			jQuery( "body" ).trigger( "update_checkout" );
			jQuery( "body" ).trigger( "wc_update_cart" );
		}
	});

	$('body').on('click', '.thm-accept-insurance', function() {
		const modal = document.getElementById('thm_insurance_modal');
		modal.style.display = "none";

		$( '.thm_insurance_term_hidden' ).val( $( '.thm_insurance_term' ).val() );

		jQuery( "body" ).trigger( "update_checkout" );
		jQuery( "body" ).trigger( "wc_update_cart" );
	});

	// Get the modal
	var modal = document.getElementById('thm_insurance_modal');

	// Get the <span> element that closes the modal
	var span = document.getElementsByClassName("thm-close")[0];

	// Save checkbox for reference in this variable
	var checkbox_ref = '';

	// When the user clicks anywhere outside of the modal, close it
	window.onclick = function(event) {
		if (event.target == modal) {
			checkbox_ref.checked = false;
			const modal = document.getElementById('thm_insurance_modal');
			modal.style.display = "none";
		}
	}

	$('body').on('click', '.thm-close, .btn-ghost', function() {
		checkbox_ref.checked = false;
		$( '.thm_insurance_quote' ).val('');
		const modal = document.getElementById('thm_insurance_modal');
		modal.style.display = "none";
	});

	$('body').on('change', '.thm_insurance_term', function() {
		let checkbox_element = $(this).next().next();

		if ( checkbox_element[0].checked ) {
			checkbox_element[0].checked = false;
			$( '.thm_insurance_quote' ).val('0');
			$( '.thm_cart_item_key' ).val(checkbox_element[0].dataset.cartItemKey);
		}
		jQuery( "body" ).trigger( "update_checkout" );
		jQuery( "body" ).trigger( "wc_update_cart" );

		if ( wc_add_to_cart_params && !wc_add_to_cart_params.is_cart ) {
			window.location.href = window.location.href + '?term=' + this.value;
		}
	});

	jQuery( document ).ready( thm_accordian_fn );

	jQuery( document ).ready( function (){
		const modal = document.getElementById('thm_insurance_modal');
		modal.style.display = "none";
	});

	function thm_accordian_fn() {
		var acc = document.getElementsByClassName( "thm-accordion" );
		var i;

		for ( i = 0; i < acc.length; i++ ) {
			acc[i].addEventListener( "click", function() {
				this.classList.toggle( "thm-active" );

				/* Toggle between hiding and showing the active panel */
				var panel = this.nextElementSibling;
				if ( panel.style.display === "block" ) {
					panel.style.display = "none";
				} else {
					panel.style.display = "block";
				}
			});
		}
	}
});
