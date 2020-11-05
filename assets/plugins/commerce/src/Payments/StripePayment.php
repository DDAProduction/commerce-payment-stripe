<?php

namespace Commerce\Payments;

class StripePayment extends Payment implements \Commerce\Interfaces\Payment
{

    const PAYMENT_KEY = 'stripe';
    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage(self::PAYMENT_KEY);

        \Stripe\Stripe::setApiKey($this->getSetting('secret_key'));

    }

    public function getPaymentMarkup()
    {

        $processor = $this->modx->commerce->loadProcessor();
        $order     = $processor->getOrder();
        $orderId  = $order['id'];

        $currency  = ci()->currency->getCurrency($order['currency']);


        $defaultCurrency = ci()->currency->getDefaultCurrencyCode();
        $amount = ci()->currency->convertToDefault($order['amount'], $currency['code']);
        $amount = (float)$amount * 100;


        $payment   = $this->createPayment($order['id'], $amount);



        $checkoutSession = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],

            'metadata'=>[
                'payment_id' => $payment['id']
            ],

            'line_items' => [[
                'currency' => $defaultCurrency,
                'amount' => $amount,
                'name' => $this->modx->parseText($this->lang['stripe.paymentTitle'], ['order_id' => $orderId]),
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $this->modx->getConfig('site_url') . 'commerce/' . self::PAYMENT_KEY . '/payment-success/?' . http_build_query([
                    'paymentHash' => $payment['hash'],
                ]),
            'cancel_url' => $this->modx->getConfig('site_url') . 'commerce/' . self::PAYMENT_KEY . '/payment-failed/?' . http_build_query([
                    'paymentHash' => $payment['hash'],
                ]),
        ]);

        return ci()->tpl->parseChunk('@CODE:'.file_get_contents(MODX_BASE_PATH.'assets/plugins/commerce/templates/front/stripe_redirect.tpl'), [
            'session_id'=>$checkoutSession->id,
            'public_key'=> $this->getSetting('public_key')
        ], true);
    }

    public function getRequestPaymentHash()
    {
        if (isset($_REQUEST['paymentHash']) && is_scalar($_REQUEST['paymentHash'])) {
            return $_REQUEST['paymentHash'];
        }
        return null;
    }
    public function handleCallback()
    {

        $endpointSecret = $this->getSetting('endpoint_secret');

        $payload = @file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        $this->log('handleCallback, data - ',1,[
            'payload' =>$payload,
            'sigHeader' =>$sigHeader,
        ]);

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            $this->log('Invalid payload',3);
            http_response_code(400);
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            $this->log('Invalid signature',3);
            http_response_code(400);
            exit();
        }



        $this->log('data',1,[
            'type'=>$event->type,
            'payment_status'=>$event->data->object->payment_status,
            'payment_id'=>$event->data->object->metadata->payment_id,
            'amount_total'=>$event->data->object->amount_total,
        ]);

        //доп проверка
        if ($event->type === 'checkout.session.completed' && $event->data->object->payment_status === 'paid') {

            try {
                $this->modx->commerce->loadProcessor()->processPayment($event->data->object->metadata->payment_id, floatval($event->data->object->amount_total) * 0.01);
                $this->log('payment process success');
                return true;
            } catch (\Exception $e) {
                $this->log('processPaymentError, exception message - '.$e->getMessage());
                return false;
            }
        }
        return false;
    }


    private function log($message,$type = 3,$data = []){
        if($this->getSetting('debug')){
            if(!empty($data)){
                $message .= '<pre>'.json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</pre>';
            }
            $this->modx->logEvent(834,$type,$message,self::PAYMENT_KEY);
        }
    }

}