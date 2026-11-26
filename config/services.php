<?php
return [
 'zarinpal'=>[
  'base_url'=>env('ZARINPAL_BASE_URL','https://sandbox.zarinpal.com/pg/v4/payment'),
  'startpay_url'=>env('ZARINPAL_STARTPAY_URL','https://sandbox.zarinpal.com/pg/StartPay'),
  'merchant_id'=>env('ZARINPAL_MERCHANT_ID')
 ],
 '0098sms'=>[
  'base_url'=>env('SMS_BASE_URL'),
  'username'=>env('SMS_USERNAME'),
  'password'=>env('SMS_PASSWORD'),
  'sender'=>env('SMS_SENDER'),
 ],
];