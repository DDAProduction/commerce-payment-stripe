//<?php
/**
 * Payment Stripe
 *
 * Stripe payments processing
 *
 * @category    plugin
 * @version     0.1
 * @author      DDA
 * @internal    @events OnBeforeOrderSending,OnManagerBeforeOrderRender,OnRegisterPayments
 * @internal    @properties &title=Title;text; &public_key=Public key;text; &secret_key=Secret key;text; &endpoint_secret=Endpoint Secret key;text; &debug=Debug;list;Yes==1||No==0;0
 * @internal    @modx_category Commerce
 * @internal    @disabled 0
 * @internal    @installset base
*/

//include file
require MODX_BASE_PATH.'assets/plugins/stripe/plugin.stripe.php';