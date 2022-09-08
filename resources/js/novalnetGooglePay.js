jQuery(document).ready(function() {
    // Load the Google Pay button
    try {
        // Load the payment instances
        var NovalnetPaymentInstance = NovalnetPayment(); 
        var googlepayNovalnetPaymentObj = NovalnetPaymentInstance.createPaymentObject();
        // Setup the payment intent
        var requestData = {
            clientKey: '0f84e6cf6fe1b93f1db8198aa2eae719',
            paymentIntent: {
                merchant: {
                    countryCode : String(jQuery('#nn_google_pay').attr('data-country'))
                },
                transaction: {
                    amount: String(jQuery('#nn_google_pay').attr('data-total-amount')),
                    currency: String(jQuery('#nn_google_pay').attr('data-currency')),   
                    paymentMethod: "GOOGLEPAY",
                    environment: "SANDBOX"
                },
                custom: {
                    lang: String(jQuery('#nn_google_pay').attr('data-order-lang'))
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
                            jQuery('#nn_google_pay_token').val(response.result.token);
			    window.location.href = jQuery('#nn_payment_process_url').val();
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
        // Checking for the Payment Method availability
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
	
    // Submit the Google pay form
    $('#nn_google_pay_form').submit(function(){
  	alert("Submitted"); 
    });
});
