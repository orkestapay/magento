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
    "uiComponent",
    "Magento_Checkout/js/model/payment/renderer-list",
], function (Component, rendererList) {
    "use strict";
    rendererList.push({
        type: "orkestapay_cards",
        component: "Orkestapay_Cards/js/view/payment/method-renderer/cc-form",
    });
    /** Add view logic here if needed */
    return Component.extend({});
});
