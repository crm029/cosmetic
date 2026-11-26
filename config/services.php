<?php
return [
 'zarinpal'=>[
  'base_url'=>env('ZARINPAL_BASE_URL','https://api.zarinpal.com/pg/v4/payment'),
  'startpay_url'=>env('ZARINPAL_STARTPAY_URL','https://www.zarinpal.com/pg/StartPay'),
  'merchant_id'=>env('ZARINPAL_MERCHANT_ID')
 ],
 '0098sms'=>['api_key'=>env('SMS_API_KEY'),'base_url'=>env('SMS_BASE_URL')],
];