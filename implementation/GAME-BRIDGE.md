# Game integration contract

Inventory features remain disabled until a server-side implementation passes the acceptance cases below. The repository contains the website client and retryable delivery worker. Game server sources and a testing server were not supplied; direct live SQL inventory editing is deliberately not used.

Endpoint: private HTTPS `POST /operations`. Secret: environment `LA2PLUS_GAME_BRIDGE_SECRET`, never a database setting or browser variable.

Headers: `X-LA2PLUS-Time` (Unix seconds), `X-LA2PLUS-Signature` (hex HMAC-SHA256 over `timestamp + "\n" + exact JSON body`). Reject timestamps outside ±60 seconds and validate signatures with a constant-time comparison. Limit network access to the website host.

Request fields: `operation`, `key`, `gs_id`, `user_id`, `payload`. Persist each key with a fingerprint of the entire request; identical retries return the original receipt. A key with another fingerprint must fail. Receipts and inventory mutations must commit together in the game service's authoritative transaction.

Operations:

- `inventory`: payload `{char_id, account}`; verify the live character belongs to that account, return tradable inventory with instance IDs, names, icons, quantity, grade and all attributes.
- `escrow`: payload `{char_id, account, object_id}`; verify character offline and ownership, reject equipped/bound/quest/forbidden items, reserve the instance so it cannot remain usable in the game. Return `{ok:true,key,receipt,item:{item_id,name,quantity,enchant,kind,grade,augmented,attributes}}`. `kind` is WEAPON, ARMOR or JEWELRY. Preserve every attribute required to recreate the item, including augmentation IDs.
- `deliver`: payload `{char_id,item}`; verify offline character, restore the full snapshot exactly once. Return `{ok:true,key,state:"delivered",receipt}` only after the inventory change is committed. For an online character, return retryable failure without consuming the item.

Acceptance gate: with a dedicated test character, escrow/deliver ordinary and augmented +16 items; compare all metadata; retry requests, crash after game commit before HTTP response, retry after site restart, run two workers and race game login. There must be one item, one receipt and no property loss in every case. Log evidence and adapter revision before setting `bridge_verified=1` through a reviewed deployment migration. Never expose an unverified bridge with the feature flag enabled.

Trybit gate: verify current API USD settlement fields, confirmed transaction status, order ID/UUID binding, fee-on-top configuration and supported currencies using the provider's approved test environment. Set `net_topup_verified=1` only after that evidence exists. Existing donation payment handlers remain separate.
