# Social Share

Lightweight WordPress share buttons — no external scripts, no tracking, no share-count APIs.

Networks: X, Facebook, WhatsApp, LinkedIn, Telegram, plus a Copy-link button (Clipboard API with a textarea fallback).

## Usage

- Install and activate — buttons are auto-appended to single posts.
- Or place `[social_share]` anywhere in a post.
- Settings → Social Share: choose networks, auto-insert on/off, position (before / after / both).

Developers: filter `wss_networks` to rename or add networks (the share URL for a custom network must be added via the same filter's companion — see the code; built-in networks cover the common five).

## License

MIT
