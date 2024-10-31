(function ($) {
	'use strict';
	$(document).ready(function () {
		if ($('#check_rates').length > 0) {
			let pid = $('input#pid').val();
			let url = $('input#url').val()+'?q='+$('input[name="quantity"]').val();
			fetch(url)
					.then((response) => {
						if (!response.ok) {
							return null;
						}
						const contentType = response.headers.get("content-type");
						if (!contentType || !contentType.includes("application/json")) {
							return null;
						}
						return response.text().then(text => {
							if (!text) {
								return null;
							}
							try {
								return JSON.parse(text);
							} catch (error) {
								return null;
							}
						});
					})
					.then((data) => {
						let el = data;
						$(el).insertAfter($('#check_rates'));
						$('#lease_click').on('click',function() {
							console.log('lease init');
							if (!data || Object.keys(data).length === 0) {
								return;
							}
							let formData = $('form.cart').serializeArray();
							let variantIdObj = formData.find(item => item.name === 'variation_id');
							let variantId = variantIdObj ? variantIdObj.value : null;
							let id = $(this).attr('data-pid');
							let q = $('input[name="quantity"]').val();
							let baseUrl = $(this).attr('data-baseurl');
							let newUrl = baseUrl + '/pkoleasing_render?pid=' + id + '&q=1&type=ITEM';
							if (variantId) {
								newUrl += '&variant_id=' + variantId;
							}
							top.location.href = newUrl;
						});
					});
		}
		if ($('body').hasClass('woocommerce-cart') && $('.pkol_widget').length > 0) {
		 setInterval(function() {
			$('#lease_click').on('click',function() {
				console.log('lease init');
				let id = $(this).attr('data-pid');
				//let q = $('input[name="quantity"]').val();
				top.location.href = $(this).attr('data-baseurl')+'/pkoleasing_render?pid='+id+'&type=CART';
		});
			},300);
		}
	})
})(jQuery);
