(function( $ ) {
	'use strict';

	$( window ).load(function() {

		$('input[type=radio][name=buy-woo-credit]').on('change', function() {
			$('.PlanOptionItem__wrapper').removeClass('PlanOptionItem__wrapper--selected');
			$(this).parents('.PlanOptionItem__wrapper').addClass('PlanOptionItem__wrapper--selected');

			if( $( this ).data('iscustom') == 'yes' ) {
				$('.th-custom-credites-1').hide();
				$('.th-custom-credites-2').show();
			} else {
				$('.th-custom-credites-1').show();
				$('.th-custom-credites-2').hide();
			}
		});

		console.log($("#custom-credits").data('creditdetails'));

		$("#custom-credits").on( 'change', function() {
			var creditdetails = $(this).data('creditdetails');
			var credit = parseInt( $(this).val() );

			if( credit < 1 ) {
				
			}

			creditdetails.forEach( (element, index, array) => {
				if( credit >= element.min && credit <= element.max) {
					var per_credit_cost = element.cost.toFixed(2);
					var sale_cost = credit * per_credit_cost;

					// set values to radio button.
					$('.th_custom_credit').attr('data-costpercredit', per_credit_cost);
					$('.th_custom_credit').val(credit);

					// update the values.
					$('.th-custom-credites-2 .PlanParticulars__details__creditsText').html( credit + ' Songs');
					$('.th-custom-credites-2 .th-costpercredit').html( '$' + per_credit_cost);
					$('.th-custom-credites-2 .th-regularcost').html( '$' + credit.toFixed(2));
					$('.th-custom-credites-2 .th-salecost').html( '$' + sale_cost.toFixed(2));
					// console.log(credit);
					// console.log(element.cost);
					// return;

				}
			});

		});

		// process ajax request.
		$("#buy-now-credits").on('click', function() {
			var iscustom = $('input[name="buy-woo-credit"]:checked').data('iscustom');
			var product_id = $('input[name="buy-woo-credit"]:checked').attr('id');
			var credit = $('input[name="buy-woo-credit"]:checked').val();
			
			if( iscustom == "yes") {
				var cost_per_credit = $('input[name="buy-woo-credit"]:checked').attr('data-costpercredit');
			} else {
				var cost_per_credit = 0.0;
			}

			jQuery.ajax({
				url: ajax_public.ajaxurl,
				type: 'post',
				dataType: 'json',
				data: {
					action: 'th_purchase_credits',
					nonce: ajax_public.nonce,
					product_id: product_id,
					credit: credit,
					cost_per_credit: cost_per_credit,
					iscustom: iscustom,
				},
				beforeSend: function () {
					
				},
				success: function (response) {
					window.location.href = response;
				},
				error: function () {
					console.log('Error!');
				}
			});
			console.log(iscustom);
			console.log(product_id);
			console.log(credit);
			console.log(cost_per_credit);

		});

		$(document).on('click', "a[href$='user-credit-history/']", function(e){
			console.log("called");
			console.log(ajax_public.endpoint);
            $.ajax({
				url : ajax_public.endpoint,
				type : "GET",
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', play.nonce);
				}
			}).then(function(res){
				$('#sub-ajax-content').html(res.content);
				$(document).trigger('refresh');
				console.log("Called");
			});
		});

		$('#userCreditHistory').DataTable({
			"paging": true, // Enable pagination
			"pageLength": 2, // Set the number of rows per page
		});

		
	});

})( jQuery );