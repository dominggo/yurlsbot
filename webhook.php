<?php
/*
 * Telegram webhook receiver for the Telegram Bot plugin.
 *
 * Deliberately outside YOURLS' normal page routing (same technique
 * yourls-api.php itself uses) so Telegram's servers can POST straight here
 * with no YOURLS login. Auth is the X-Telegram-Bot-Api-Secret-Token header
 * checked in telegrambot_handle_webhook() -- see plugin.php.
 */
require_once( dirname( __FILE__ ) . '/../../../includes/load-yourls.php' );

telegrambot_handle_webhook();
