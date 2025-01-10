<?php

return [
    'GOOGLE_CLIENT_ID' => env('GOOGLE_CLIENT_ID'),
    'GOOGLE_CLIENT_SECRET' => env('GOOGLE_CLIENT_SECRET'),
    'email_mark_as_read' => false, // set this to true if you want to make the email mark as read
    'REFRESH_TOKEN' => env('REFRESH_TOKEN'), //refresh token of user for which email being read
    'EMAIL_READ_SUBSCRIPTION_NAME' => env('EMAIL_READ_SUBSCRIPTION_NAME'),
    'EMAIL_READ_TOPIC_NAME' => env('EMAIL_READ_TOPIC_NAME')
];