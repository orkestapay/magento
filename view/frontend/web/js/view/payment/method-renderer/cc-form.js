/**
 * Orkestapay_Cards Magento JS component
 *
 * @category    Orkestapay
 * @package     Orkestapay_Cards
 * @author      Federico Balderas
 * @copyright   Orkestapay (http://orkestapay.com)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */
/*browser:true*/
/*global define*/
define([
  "Magento_Payment/js/view/payment/cc-form",
  "jquery",
  "Magento_Checkout/js/model/quote",
  "Magento_Customer/js/model/customer",
  "Magento_Checkout/js/model/full-screen-loader",
  "Magento_Payment/js/model/credit-card-validation/validator",
], function (Component, $, quote, customer, fullScreenLoader) {
  var customerData = null;
  var is_sandbox = window.checkoutConfig.payment.orkestapay_credentials.is_sandbox === "0" ? false : true;
  var merchant_id = window.checkoutConfig.payment.orkestapay_credentials.merchant_id;
  var public_key = window.checkoutConfig.payment.orkestapay_credentials.public_key;
  var create_order_url = window.checkoutConfig.payment.create_order_url;
  var complete_3ds_url = window.checkoutConfig.payment.complete_3ds_url;

  var orkestapay = initOrkestaPay({ merchant_id, public_key, is_sandbox });
  console.log("orkestapay.js is ready!");

  // antifraudes
  orkestapay
    .getDeviceInfo()
    .then(({ device_session_id }) => {
      console.log("setDeviceSessionId", device_session_id);
      $("#device_session_id").val(device_session_id);
    })
    .catch((error) => {
      console.error("setDeviceSessionId", error);
    });

  return Component.extend({
    defaults: {
      template: "Orkestapay_Cards/payment/orkestapay-form",
    },

    getCode: function () {
      return "orkestapay_cards";
    },

    isActive: function () {
      return true;
    },
    isLoggedIn: function () {
      return window.checkoutConfig.payment.is_logged_in;
    },
    notLogged: function () {
      return !window.checkoutConfig.payment.is_logged_in;
    },
    /**
     * Prepare and process payment information
     */
    preparePayment: async function () {
      var self = this;
      var $form = $("#" + this.getCode() + "-form");

      this.messageContainer.clear();

      if ($form.validation() && !$form.validation("isValid")) {
        return false;
      }

      fullScreenLoader.startLoader();

      var year = $("#orkestapay_cards_expiration_yr").val();
      var holder_name = this.getCustomerFullName();
      var card = $("#orkestapay_cards_cc_number").val();
      var cvc = $("#orkestapay_cards_cc_cid").val();
      var month = $("#orkestapay_cards_expiration").val();

      var card = {
        holder_name,
        card_number: card.replace(/ /g, ""),
        expiration_date: {
          expiration_month: month.padStart(2, "0"),
          expiration_year: year,
        },
        verification_code: cvc,
      };

      var payment_method_data = {
        card,
        one_time_use: true,
      };

      if (this.validateAddress() !== false) {
        payment_method_data["billing_address"] = this.validateAddress();
      }

      var orkestapay_card = await orkestapay.createCard();
      var payment_method = await orkestapay_card.createToken(payment_method_data);

      var payment_method_id = payment_method.payment_method_id;
      var device_session_id = $("#device_session_id").val();

      $("#orkestapay_token").val(payment_method_id);

      $.post(create_order_url, { payment_method_id, device_session_id })
        .done((response) => {
          if (response.hasOwnProperty("error")) {
            fullScreenLoader.stopLoader();

            self.messageContainer.addErrorMessage({
              message: response.message,
            });
            return;
          }

          var payment_id = response.payment_id;

          if (response.status === "COMPLETED") {
            $("#orkestapay_payment_id").val(payment_id);

            self.placeOrder();
            return;
          }

          if (response.status === "FAILED" || response.status === "REJECTED") {
            fullScreenLoader.stopLoader();

            self.messageContainer.addErrorMessage({
              message: response.transactions[0].provider.message,
            });
            return;
          }

          // Redirect to the 3DSecure page
          if (response.status === "PAYMENT_ACTION_REQUIRED" && response.user_action_required.type === "THREE_D_SECURE_SPECIFIC") {
            window.location.href = response.user_action_required.three_d_secure_specific.three_ds_redirect_url;
            return;
          }

          if (response.status === "PAYMENT_ACTION_REQUIRED" && response.user_action_required.type === "THREE_D_SECURE_AUTHENTICATION") {
            orkestapay
              .startModal3DSecure({
                merchant_provider_id: response.user_action_required.three_d_secure_authentication.merchant_provider_id,
                payment_id: response.payment_id,
                order_id: response.order_id,
              })
              .then((result) => {
                if (result) {
                  $.post(complete_3ds_url, {
                    orkestapay_payment_id: payment_id,
                  })
                    .done((response3ds) => {
                      if (response3ds.status !== "SUCCESS") {
                        self.messageContainer.addErrorMessage({
                          message: response3ds.provider.message,
                        });
                        return;
                      }

                      $("#orkestapay_payment_id").val(payment_id);

                      self.placeOrder();
                      return;
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                      console.log("Error complete3DSPayment: " + textStatus);
                    });
                }
              })
              .catch((err) => {
                console.error("startModal3DSecure", err);

                fullScreenLoader.stopLoader();

                self.messageContainer.addErrorMessage({
                  message: err.message,
                });
                return;
              });
          }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          console.log("Error: " + textStatus); // Acción cuando la solicitud falla
          fullScreenLoader.stopLoader();
        });
    },
    /**
     * @override
     */
    getData: function () {
      return {
        method: "orkestapay_cards",
        additional_data: {
          cc_cid: this.creditCardVerificationNumber(),
          cc_type: this.creditCardType(),
          cc_exp_year: this.creditCardExpYear(),
          cc_exp_month: this.creditCardExpMonth(),
          cc_number: this.creditCardNumber(),
          orkestapay_token: $("#orkestapay_token").val(),
          device_session_id: $("#device_session_id").val(),
          payment_id: $("#orkestapay_payment_id").val(),
        },
      };
    },
    validate: function () {
      var $form = $("#" + this.getCode() + "-form");
      return $form.validation() && $form.validation("isValid");
    },
    getCustomerFullName: function () {
      customerData = quote.billingAddress._latestValue;
      return customerData.firstname + " " + customerData.lastname;
    },
    setDeviceSessionId: async function (orkestapay) {
      try {
        const { device_session_id } = await orkestapay.getDeviceInfo();
        console.log("setDeviceSessionId", device_session_id);
        $("#device_session_id").val(device_session_id);
      } catch (err) {
        console.error("setDeviceSessionId", err);
      }
    },
    validateAddress: function () {
      customerData = quote.billingAddress._latestValue;

      if (typeof customerData.city === "undefined" || customerData.city.length === 0) {
        return false;
      }

      if (typeof customerData.countryId === "undefined" || customerData.countryId.length === 0) {
        return false;
      }

      if (typeof customerData.postcode === "undefined" || customerData.postcode === null || customerData.postcode.length === 0) {
        return false;
      }

      if (typeof customerData.street === "undefined" || customerData.street[0].length === 0) {
        return false;
      }

      if (typeof customerData.region === "undefined" || customerData.region.length === 0) {
        return false;
      }

      var address = {
        first_name: customerData.firstname,
        last_name: customerData.lastname,
        line_1: customerData.street[0],
        line_2: customerData.street[1],
        city: customerData.city,
        state: customerData.region,
        country: customerData.countryId,
        zip_code: customerData.postcode,
      };

      return address;
    },
  });
});
