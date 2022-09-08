jQuery(document).ready(function() {
try {
	var NovalnetPaymentInstance = NovalnetPayment();
	var googlepayNovalnetPaymentObj = NovalnetPaymentInstance.createPaymentObject();
	var requestData = {
		clientKey: my_ajax_object.client_key,
		paymentIntent: {
			merchant: {
				countryCode : String('#nn_google_pay').attr('data-country')
			},
			transaction: {
				amount: String('#nn_google_pay').attr('data-total-amount'),
				currency: String('#nn_google_pay').attr('data-currency'),	
				paymentMethod: "GOOGLEPAY",
				environment: "SANDBOX"
			},
			custom: {
				lang: String('#nn_google_pay').attr('data-order-lang')
			},
			wallet: {
				merchantName: "Testing purpose"
			},
			button: {
				type: "buy",
				style: "black",
				locale: "en-US",
				boxSizing: "fill",
				dimensions: {
					height: 45,
					width: 200
				}
			},
			callbacks: {
				onProcessCompletion: function (response, processedStatus) {
					// Only on success, we proceed further with the booking
					if (response.result.status == "SUCCESS") {
						
						
					} else {
						// Upon failure, displaying the error text 
						if(response.result.status_text) {
							alert(response.result.status_text);
						}
					}
					
				}
			}
		}
		
		
	};
	googlepayNovalnetPaymentObj.setPaymentIntent(requestData);
	googlepayNovalnetPaymentObj.isPaymentMethodAvailable(function(displayGooglePayButton) {
		if (displayGooglePayButton) {
			// Initiating the Payment Request for the Wallet Payment
			googlepayNovalnetPaymentObj.addPaymentButton("#nn_google_pay");
		}
	});
} catch (e) {
	// Handling the errors from the payment intent setup
	console.log(e.message);
}
});
