<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Customer Name
    |--------------------------------------------------------------------------
    |
    | The name used when creating test customers in Stripe. This appears
    | in your Stripe dashboard so you can identify demo-created customers.
    |
    */

    'customer_name' => env('DEMO_CUSTOMER_NAME', 'Demo User'),

    /*
    |--------------------------------------------------------------------------
    | Demo Customer Email Domain
    |--------------------------------------------------------------------------
    |
    | The email domain used for test customers. Each scenario creates a
    | unique email like demo-flex-sub-1711234567@your-domain.test
    |
    */

    'customer_email_domain' => env('DEMO_CUSTOMER_EMAIL_DOMAIN', 'cashier-demo.test'),

];
