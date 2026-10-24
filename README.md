# YOURLS Telegram Bot

Manage your [YOURLS](https://yourls.org) short links from Telegram: shorten, list, check stats, delete — from one admin chat.

## Install

1. Drop this folder into `user/plugins/` in your YOURLS install, so you end up with `user/plugins/telegram-bot/plugin.php`.
2. In YOURLS admin → **Manage Plugins**, **Activate** "Telegram Bot".
3. On Telegram, message [@BotFather](https://t.me/BotFather) → `/newbot` → copy the token it gives you.
4. Send any message to your new bot, then open `https://api.telegram.org/bot<your-token>/getUpdates` in a browser to find your numeric chat id (`message.chat.id`).
5. In YOURLS admin, open the plugin's **Settings** page (next to it on Manage Plugins), paste the token and chat id, and save. The plugin registers the webhook with Telegram for you — no manual `setWebhook` call needed.

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
