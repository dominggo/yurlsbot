# YOURLS Telegram Bot

Manage your [YOURLS](https://yourls.org) short links from Telegram: shorten, list, check stats, delete — from one admin chat.

## Install

1. Drop this folder into `user/plugins/` in your YOURLS install, so you end up with `user/plugins/telegram-bot/plugin.php`.
2. In YOURLS admin → **Manage Plugins**, **Activate** "Telegram Bot". A new "Telegram Bot" link appears in the admin menu.
3. Message [@BotFather](https://t.me/BotFather) on Telegram → send `/newbot` → follow the prompts (pick a name and a `@username` ending in `bot`) → it gives you a token like `123456789:AA...`.
4. Send any message (e.g. "hi") to your new bot, then open this in a browser with your real token:
   `https://api.telegram.org/bot<your-token>/getUpdates`
   Look for `"chat":{"id":...}` in the JSON — that number is your chat id.
5. Open the plugin's settings page (`your-site/admin/plugins.php?page=telegram-bot`, or the "Telegram Bot" admin menu link), paste the token and chat id, and click **Save & register webhook**. This also calls Telegram's `setWebhook` for you automatically — you'll see a green "Webhook registered with Telegram" confirmation (or a red error if something's off, e.g. a bad token).
6. Test it: message your bot `/start` — it should reply with the command list. Then try `/short https://example.com` to confirm the whole chain works end to end.

## Commands

| Command | What it does |
|---|---|
| `/short <url> [keyword]` | Shortens a URL; keyword is optional (YOURLS generates one if omitted) |
| `/links [search]` | Shows the 10 most recent links, or matching links if you give a search term |
| `/stats <keyword>` | Clicks, target URL and creation date for one link |
| `/delete <keyword>` | Asks you to confirm (inline Yes/Cancel buttons), then deletes |

Only the chat id you configured gets a reply — the bot is silent to everyone else, including group chats it might be added to.

## How it works

The plugin is entirely self-contained: `webhook.php` bootstraps just enough of YOURLS (the same technique `yourls-api.php` itself uses) to call YOURLS' own functions directly (`yourls_add_new_link()`, `yourls_delete_link_by_keyword()`, etc.) — no external HTTP calls to your own site, no separate bot process to host or keep running.

Security:
- Telegram's webhook request is checked against a random secret token (auto-generated on first save, sent as `X-Telegram-Bot-Api-Secret-Token`) — anyone else POSTing to the webhook URL gets a 403.
- Every incoming message's chat id is checked against your configured admin chat id; anything else is silently dropped.
- Deleting a link always asks for confirmation first.

## Requirements

YOURLS 1.8+ (tested on 1.10.6). PHP's `curl` extension (bundled with YOURLS' own requirements).

## License

MIT.
