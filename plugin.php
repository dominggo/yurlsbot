<?php
/*
Plugin Name: Telegram Bot
Plugin URI: https://github.com/dominggo/yourls-telegram-bot
Description: Manage your short links from Telegram: /short, /links, /stats, /delete, and per-link View/Update/Delete buttons from /links. One admin chat only. See this plugin's Settings page (Manage Plugins) to connect your bot.
Version: 1.3.0
Author: dominggo
Author URI: https://github.com/dominggo
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'TELEGRAMBOT_OPTION_TOKEN',   'telegrambot_token' );
define( 'TELEGRAMBOT_OPTION_CHATID',  'telegrambot_admin_chat_id' );
define( 'TELEGRAMBOT_OPTION_SECRET',  'telegrambot_webhook_secret' );
define( 'TELEGRAMBOT_OPTION_PENDING', 'telegrambot_pending_update' ); // keyword awaiting a new URL, or ''
define( 'TELEGRAMBOT_PAGE_SLUG',      'telegram-bot' );

/* -------------------------------------------------------------------------
 * Admin settings page (Manage Plugins -> Settings next to this plugin)
 * ---------------------------------------------------------------------- */

yourls_add_action( 'plugins_loaded', 'telegrambot_register_page' );
function telegrambot_register_page() {
    yourls_register_plugin_page( TELEGRAMBOT_PAGE_SLUG, 'Telegram Bot', 'telegrambot_render_page' );
}

function telegrambot_webhook_url() {
    return YOURLS_SITE . '/user/plugins/' . TELEGRAMBOT_PAGE_SLUG . '/webhook.php';
}

function telegrambot_render_page() {
    $notice = '';

    if( isset( $_POST['telegrambot_submit'] ) ) {
        yourls_verify_nonce( TELEGRAMBOT_PAGE_SLUG );
        $notice = telegrambot_save_settings();
    }

    $token   = htmlspecialchars( yourls_get_option( TELEGRAMBOT_OPTION_TOKEN ) );
    $chat_id = htmlspecialchars( yourls_get_option( TELEGRAMBOT_OPTION_CHATID ) );
    $nonce   = yourls_create_nonce( TELEGRAMBOT_PAGE_SLUG );
    $webhook_url = htmlspecialchars( telegrambot_webhook_url() );

    echo <<<HTML
        <h2>Telegram Bot</h2>

        <h3>Setup</h3>
        <ol>
            <li>Message <a href="https://t.me/BotFather">@BotFather</a> on Telegram &rarr; send <code>/newbot</code> &rarr; follow the
                prompts (pick a name and a <code>@username</code> ending in <code>bot</code>) &rarr; it gives you a token like
                <code>123456789:AA...</code>.</li>
            <li>Send any message (e.g. "hi") to your new bot, then open this in a browser with your real token:<br>
                <code>https://api.telegram.org/bot&lt;your-token&gt;/getUpdates</code><br>
                Look for <code>"chat":{"id":...}</code> in the JSON &mdash; that number is your chat id.</li>
            <li>Paste both into the form below and click <b>Save &amp; register webhook</b>. This also calls Telegram's
                <code>setWebhook</code> and <code>setMyCommands</code> for you automatically (so the "/" menu in Telegram shows
                every command with a description) &mdash; you'll see a green "Webhook registered with Telegram"
                confirmation (or a red error if something's off, e.g. a bad token).</li>
            <li>Test it: message your bot <code>/start</code> &mdash; it should reply with the command list. Then try
                <code>/short https://example.com</code> to confirm the whole chain works end to end.</li>
        </ol>

        <form method="post">
        <input type="hidden" name="nonce" value="$nonce" />
        <table class="table">
        <tr><td><label for="telegrambot_token">Bot token</label></td>
            <td><input type="text" id="telegrambot_token" name="telegrambot_token" size="50" value="{$token}" placeholder="123456789:AA..." /></td></tr>
        <tr><td><label for="telegrambot_chat_id">Admin chat id</label></td>
            <td><input type="text" id="telegrambot_chat_id" name="telegrambot_chat_id" size="20" value="{$chat_id}" placeholder="your numeric Telegram id" />
            <br><small>Only this chat id can command the bot. Everyone else is ignored.</small></td></tr>
        </table>
        <p><input type="submit" name="telegrambot_submit" value="Save &amp; register webhook" /></p>
        </form>

        $notice

        <h3>Webhook URL</h3>
        <p><code>$webhook_url</code></p>
        <p><small>You don't need to call Telegram's <code>setWebhook</code> yourself &mdash; step 3 above does it. This is just
        here for reference / troubleshooting.</small></p>

        <h3>Commands</h3>
        <ul>
            <li><code>/short &lt;url&gt; [keyword]</code> &mdash; shorten a URL, optional custom keyword</li>
            <li><code>/links [search]</code> &mdash; most recent short links, or matching a search term &mdash; each one has
                <b>View</b>, <b>Update</b> and <b>Delete</b> buttons</li>
            <li><code>/stats &lt;keyword&gt;</code> &mdash; clicks, target URL and date for one short link</li>
            <li><code>/delete &lt;keyword&gt;</code> &mdash; asks to confirm, then deletes</li>
            <li><code>/cancel</code> &mdash; cancel an in-progress Update (after tapping a link's Update button)</li>
            <li><code>/help</code> (or <code>/start</code>) &mdash; shows this command list inside Telegram</li>
        </ul>
HTML;
}

function telegrambot_save_settings() {
    $token   = isset( $_POST['telegrambot_token'] )   ? trim( $_POST['telegrambot_token'] )   : '';
    $chat_id = isset( $_POST['telegrambot_chat_id'] ) ? trim( $_POST['telegrambot_chat_id'] ) : '';

    if( $chat_id !== '' && !preg_match( '/^-?[0-9]+$/', $chat_id ) ) {
        return '<p style="color:red">Chat id must be numeric (the id Telegram gives you, not a @username).</p>';
    }

    yourls_update_option( TELEGRAMBOT_OPTION_TOKEN, $token );
    yourls_update_option( TELEGRAMBOT_OPTION_CHATID, $chat_id );

    if( !yourls_get_option( TELEGRAMBOT_OPTION_SECRET ) ) {
        yourls_update_option( TELEGRAMBOT_OPTION_SECRET, telegrambot_random_secret() );
    }

    if( $token === '' ) {
        return '<p style="color:red">Token cleared &mdash; webhook not registered.</p>';
    }

    $result = telegrambot_api_call( $token, 'setWebhook', array(
        'url'          => telegrambot_webhook_url(),
        'secret_token' => yourls_get_option( TELEGRAMBOT_OPTION_SECRET ),
    ) );

    telegrambot_api_call( $token, 'setMyCommands', array(
        'commands' => json_encode( telegrambot_bot_commands() ),
    ) );

    if( $result && !empty( $result['ok'] ) ) {
        return '<p style="color:green">Saved. Webhook registered with Telegram.</p>';
    }
    $desc = isset( $result['description'] ) ? htmlspecialchars( $result['description'] ) : 'no response';
    return "<p style=\"color:red\">Saved, but Telegram's setWebhook failed: $desc</p>";
}

/**
 * The "/" command menu Telegram shows for this bot (setMyCommands).
 */
function telegrambot_bot_commands() {
    return array(
        array( 'command' => 'short',  'description' => 'Shorten a URL: /short <url> [keyword]' ),
        array( 'command' => 'links',  'description' => 'List/search short links: /links [search]' ),
        array( 'command' => 'stats',  'description' => 'Stats for one link: /stats <keyword>' ),
        array( 'command' => 'delete', 'description' => 'Delete a link: /delete <keyword>' ),
        array( 'command' => 'cancel', 'description' => 'Cancel an in-progress Update' ),
        array( 'command' => 'help',   'description' => 'Show this command list' ),
    );
}

function telegrambot_random_secret() {
    return bin2hex( random_bytes( 24 ) );
}

/* -------------------------------------------------------------------------
 * Telegram API helper
 * ---------------------------------------------------------------------- */

function telegrambot_api_call( $token, $method, $params = array() ) {
    $ch = curl_init( "https://api.telegram.org/bot{$token}/{$method}" );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
    $body = curl_exec( $ch );
    curl_close( $ch );
    if( $body === false ) {
        return false;
    }
    return json_decode( $body, true );
}

function telegrambot_send( $text, $reply_markup = null ) {
    $token   = yourls_get_option( TELEGRAMBOT_OPTION_TOKEN );
    $chat_id = yourls_get_option( TELEGRAMBOT_OPTION_CHATID );
    if( !$token || !$chat_id ) {
        return;
    }
    $params = array(
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML',
    );
    if( $reply_markup !== null ) {
        $params['reply_markup'] = json_encode( $reply_markup );
    }
    telegrambot_api_call( $token, 'sendMessage', $params );
}

/* -------------------------------------------------------------------------
 * Webhook entry point (called from webhook.php, which just bootstraps
 * YOURLS and calls this -- see that file).
 * ---------------------------------------------------------------------- */

function telegrambot_handle_webhook() {
    $secret = yourls_get_option( TELEGRAMBOT_OPTION_SECRET );
    $sent   = isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
    if( !$secret || !hash_equals( $secret, $sent ) ) {
        http_response_code( 403 );
        exit;
    }

    $raw    = file_get_contents( 'php://input' );
    $update = json_decode( $raw, true );
    if( !is_array( $update ) ) {
        exit; // nothing to do, but still 200 so Telegram doesn't retry forever
    }

    $admin_chat_id = yourls_get_option( TELEGRAMBOT_OPTION_CHATID );

    if( isset( $update['callback_query'] ) ) {
        telegrambot_handle_callback( $update['callback_query'], $admin_chat_id );
        exit;
    }

    if( !isset( $update['message']['text'], $update['message']['chat']['id'] ) ) {
        exit;
    }
    $chat_id = (string) $update['message']['chat']['id'];
    $text    = trim( $update['message']['text'] );

    if( !hash_equals( (string) $admin_chat_id, $chat_id ) ) {
        exit; // silent to everyone but the configured admin
    }

    $pending = yourls_get_option( TELEGRAMBOT_OPTION_PENDING );
    if( $pending && substr( $text, 0, 1 ) !== '/' ) {
        telegrambot_cmd_apply_update( $pending, $text );
        return;
    }
    if( $pending ) {
        yourls_update_option( TELEGRAMBOT_OPTION_PENDING, '' ); // any slash command abandons the pending update
        if( preg_match( '/^\/cancel/i', $text ) ) {
            telegrambot_send( 'Cancelled.' );
            return;
        }
    }

    telegrambot_dispatch( $text );
}

function telegrambot_dispatch( $text ) {
    if( preg_match( '/^\/short\s+(\S+)(?:\s+(\S+))?/i', $text, $m ) ) {
        telegrambot_cmd_short( $m[1], isset( $m[2] ) ? $m[2] : '' );
    } elseif( preg_match( '/^\/links(?:\s+(.+))?/i', $text, $m ) ) {
        telegrambot_cmd_links( isset( $m[1] ) ? trim( $m[1] ) : '' );
    } elseif( preg_match( '/^\/stats\s+(\S+)/i', $text, $m ) ) {
        telegrambot_cmd_stats( $m[1] );
    } elseif( preg_match( '/^\/delete\s+(\S+)/i', $text, $m ) ) {
        telegrambot_cmd_delete_confirm( $m[1] );
    } elseif( preg_match( '/^\/(start|help)/i', $text ) ) {
        telegrambot_send(
            "<b>Telegram Bot for YOURLS</b>\n\n" .
            "/short &lt;url&gt; [keyword] \xe2\x80\x94 shorten a URL, keyword optional\n" .
            "/links [search] \xe2\x80\x94 recent links, or matching a search term\n" .
            "/stats &lt;keyword&gt; \xe2\x80\x94 clicks, target URL and date for one link\n" .
            "/delete &lt;keyword&gt; \xe2\x80\x94 asks to confirm, then deletes\n" .
            "/cancel \xe2\x80\x94 cancel an in-progress Update\n\n" .
            "Each link in /links also has <b>View</b>, <b>Update</b> and <b>Delete</b> buttons \xe2\x80\x94 " .
            "tap Update, then send the new target URL in your next message."
        );
    }
    // anything else: silently ignored, same as accessbot's convention
}

function telegrambot_cmd_short( $url, $keyword ) {
    if( !filter_var( $url, FILTER_VALIDATE_URL ) || !preg_match( '#^https?://#i', $url ) ) {
        telegrambot_send( 'Not a valid http(s) URL.' );
        return;
    }
    $result = yourls_add_new_link( $url, $keyword );
    if( !empty( $result['status'] ) && $result['status'] === 'success' ) {
        telegrambot_send( htmlspecialchars( $result['shorturl'] ) );
    } else {
        $msg = isset( $result['message'] ) ? $result['message'] : 'unknown error';
        telegrambot_send( 'Could not shorten: ' . htmlspecialchars( $msg ) );
    }
}

function telegrambot_fetch_links( $search ) {
    $table = YOURLS_DB_TABLE_URL;
    if( $search !== '' ) {
        return yourls_get_db()->fetchObjects(
            "SELECT keyword, url, clicks FROM `$table` WHERE keyword LIKE :s OR url LIKE :s OR title LIKE :s ORDER BY timestamp DESC LIMIT 10",
            array( 's' => '%' . $search . '%' )
        );
    }
    return yourls_get_db()->fetchObjects(
        "SELECT keyword, url, clicks FROM `$table` ORDER BY timestamp DESC LIMIT 10"
    );
}

function telegrambot_links_text( $rows ) {
    $lines = array();
    foreach( $rows as $row ) {
        $clicks = (int) $row->clicks;
        $clicks_label = $clicks . ' click' . ( $clicks === 1 ? '' : 's' );
        $shorturl = htmlspecialchars( yourls_link( $row->keyword ) );
        $lines[] = "\xe2\x80\xa2 " . '<a href="' . $shorturl . '">' . htmlspecialchars( $row->keyword ) . "</a> \xe2\x80\x94 " . $clicks_label;
    }
    return implode( "\n\n", $lines );
}

function telegrambot_cmd_links( $search ) {
    $rows = telegrambot_fetch_links( $search );
    if( !$rows ) {
        telegrambot_send( 'No links found.' );
        return;
    }
    $header = $search !== '' ? 'Links matching "' . htmlspecialchars( $search ) . '"' : 'Recent links';
    $text = '<b>' . $header . "</b>\n\n" . telegrambot_links_text( $rows );

    $keyboard = array();
    foreach( $rows as $row ) {
        $kw = $row->keyword;
        $keyboard[] = array(
            array( 'text' => 'View',   'callback_data' => 'lview:'   . $kw ),
            array( 'text' => 'Update', 'callback_data' => 'lupd:'    . $kw ),
            array( 'text' => 'Delete', 'callback_data' => 'ldelask:' . $kw ),
        );
    }
    telegrambot_send( $text, array( 'inline_keyboard' => $keyboard ) );
}

function telegrambot_cmd_stats( $keyword ) {
    $info = yourls_get_keyword_infos( $keyword );
    if( !$info ) {
        telegrambot_send( 'Unknown keyword: ' . htmlspecialchars( $keyword ) );
        return;
    }
    telegrambot_send(
        "<b>" . htmlspecialchars( $keyword ) . "</b>\n" .
        'URL: ' . htmlspecialchars( $info['url'] ) . "\n" .
        'Clicks: ' . (int) $info['clicks'] . "\n" .
        'Created: ' . htmlspecialchars( $info['timestamp'] )
    );
}

function telegrambot_cmd_delete_confirm( $keyword ) {
    if( !yourls_keyword_is_taken( $keyword ) ) {
        telegrambot_send( 'Unknown keyword: ' . htmlspecialchars( $keyword ) );
        return;
    }
    telegrambot_send(
        'Delete <b>' . htmlspecialchars( $keyword ) . '</b>? This breaks the link for anyone who has it.',
        array( 'inline_keyboard' => array( array(
            array( 'text' => 'Yes, delete', 'callback_data' => 'delok:' . $keyword ),
            array( 'text' => 'Cancel',      'callback_data' => 'delno' ),
        ) ) )
    );
}

function telegrambot_cmd_apply_update( $keyword, $new_url ) {
    yourls_update_option( TELEGRAMBOT_OPTION_PENDING, '' );

    if( !yourls_keyword_is_taken( $keyword ) ) {
        telegrambot_send( 'That link is gone now: ' . htmlspecialchars( $keyword ) );
        return;
    }
    if( !filter_var( $new_url, FILTER_VALIDATE_URL ) || !preg_match( '#^https?://#i', $new_url ) ) {
        // put it back so they can just resend without tapping Update again
        yourls_update_option( TELEGRAMBOT_OPTION_PENDING, $keyword );
        telegrambot_send( 'Not a valid http(s) URL. Send the new target URL for <b>' . htmlspecialchars( $keyword ) . '</b> (or /cancel).' );
        return;
    }

    $result = yourls_edit_link( $new_url, $keyword, $keyword );
    if( !empty( $result['status'] ) && $result['status'] === 'success' ) {
        telegrambot_send( 'Updated <b>' . htmlspecialchars( $keyword ) . "</b> \xe2\x86\x92 " . htmlspecialchars( $new_url ) );
    } else {
        $msg = isset( $result['message'] ) ? $result['message'] : 'unknown error';
        telegrambot_send( 'Could not update: ' . htmlspecialchars( $msg ) );
    }
}

function telegrambot_handle_callback( $callback_query, $admin_chat_id ) {
    $token    = yourls_get_option( TELEGRAMBOT_OPTION_TOKEN );
    $chat_id  = isset( $callback_query['message']['chat']['id'] ) ? (string) $callback_query['message']['chat']['id'] : '';
    $data     = isset( $callback_query['data'] ) ? $callback_query['data'] : '';
    $query_id = isset( $callback_query['id'] ) ? $callback_query['id'] : '';

    if( !hash_equals( (string) $admin_chat_id, $chat_id ) ) {
        return;
    }
    if( $token && $query_id ) {
        telegrambot_api_call( $token, 'answerCallbackQuery', array( 'callback_query_id' => $query_id ) );
    }

    if( $data === 'delno' ) {
        telegrambot_send( 'Cancelled.' );
        return;
    }
    if( strpos( $data, 'delok:' ) === 0 ) {
        $keyword = substr( $data, 6 );
        if( yourls_keyword_is_taken( $keyword ) ) {
            yourls_delete_link_by_keyword( $keyword );
            telegrambot_send( 'Deleted: ' . htmlspecialchars( $keyword ) );
        } else {
            telegrambot_send( 'Already gone: ' . htmlspecialchars( $keyword ) );
        }
        return;
    }
    if( strpos( $data, 'lview:' ) === 0 ) {
        telegrambot_cmd_stats( substr( $data, 6 ) );
        return;
    }
    if( strpos( $data, 'ldelask:' ) === 0 ) {
        telegrambot_cmd_delete_confirm( substr( $data, 8 ) );
        return;
    }
    if( strpos( $data, 'lupd:' ) === 0 ) {
        $keyword = substr( $data, 5 );
        if( !yourls_keyword_is_taken( $keyword ) ) {
            telegrambot_send( 'Unknown keyword: ' . htmlspecialchars( $keyword ) );
            return;
        }
        yourls_update_option( TELEGRAMBOT_OPTION_PENDING, $keyword );
        telegrambot_send( 'Send the new target URL for <b>' . htmlspecialchars( $keyword ) . '</b> (or /cancel).' );
    }
}
