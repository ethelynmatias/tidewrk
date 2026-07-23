<?php

return [
    'fail_inventory' => env('FULFILLMENT_FAIL_INVENTORY', false),
    'fail_payment'   => env('FULFILLMENT_FAIL_PAYMENT', false),
    'fail_shipping'  => env('FULFILLMENT_FAIL_SHIPPING', false),
];
