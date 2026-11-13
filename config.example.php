<?php
/**
 * Copy this file to config.local.php and fill in the Meta WhatsApp Cloud API values.
 * config.local.php is not served by Apache.
 */
return array(
    'api_key' => 'change-me',
    'graph_version' => 'v21.0',
    'phone_number_id' => '',
    'access_token' => '',
    'verify_token' => 'whatsapp-verify',
    'otp_template' => 'otp',
    'otp_language' => 'ar',
    // Meta authentication templates also need the code on the URL button.
    'otp_authentication_template' => true,
);
