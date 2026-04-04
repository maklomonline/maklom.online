---
name: Bot Server System
description: Community bot account registration, API, admin approval, and Python bot client added in 2026-04-03
type: project
---

Bot server system implemented. Users can register community bot accounts (up to 6d rank) at /bot/register. Admin approves at /admin/bot-requests. Approved bots get a bot_api_token. Users download bot_client.py from /bot/download and run it with their GTP engine (e.g. KataGo). The bots page at /bots now shows community bots with online/offline status.

**Why:** Replace server-side KataGo bots with a community-run bot server system where users host their own GTP engines.

**How to apply:** When working on bot/game features, note that is_bot users with bot_api_token are community bots (not server-side). Use isBotOnline() helper on User model to check availability. Old BotMoveJob still runs for bots without bot_api_token.

Key files:
- app/Models/BotRequest.php
- app/Http/Controllers/BotServerController.php
- app/Http/Controllers/Admin/BotRequestController.php
- app/Http/Controllers/Api/BotApiController.php
- routes/api.php (new)
- public/downloads/bot_client.py
- resources/views/bot/register.blade.php
- resources/views/bot/download.blade.php
- resources/views/admin/bot-requests/index.blade.php
