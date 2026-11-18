# Balance actions and Coin of Luck store

The player balance card provides Transfer to game, Top up, and Refresh. Refresh reads the authoritative cabinet balance without navigating. Transfer opens a native modal with only the current user's game accounts and characters, a whole CoL quantity, and debit preview. Character ownership, offline status, feature availability and available funds are checked on the server. Request keys prevent duplicate debits and duplicate deliveries.

`/cabinet/deposit/` remains a separate page in the project landing layout. It shows the cabinet's current server, base CoL input, slider, bonus tiers, server-calculated quote, country and display currency. Only servers actually configured for the cabinet are shown; no example x1/x30/x300 servers are fabricated. Returning from a character-specific purchase can open the preselected transfer modal; no automatic debit occurs.

`CoinPurchase` uses fixed-point arithmetic with configured `utopay.rate` (USD per base CoL), rounds the USD charge upward to cents, and floors bonus coins. Bonus thresholds: 10 CoL / 5%, 200 / 10%, 300 / 15%, 400 / 20%, 1000 / 25%. The quote accepts 1–100000 whole base CoL. Personal service/shop discounts do not apply. Other currency totals are indicative and never change USD settlement.

On the isolated test host, Demo top-up credits virtual cabinet CoL through the existing wallet ledger and an idempotent audited transaction. It does not create a real invoice or credit the separate exchange USD wallet. The handler refuses operation outside `PortalRuntime::isDemo()`. Eight requested payment families are displayed as disabled, unconnected methods; this change does not activate merchant accounts, real money collection, or a production game bridge. Connecting these requires a separately verified provider checkout and settlement integration using the same authoritative quote.

The owner's Deposit switch blocks both quote and demo purchase APIs. The transfer additionally requires Warehouse. The balance read remains available as part of the essential home page.

Validation: `bash tools/php-test.sh implementation/tests/coin-purchase.php`, existing domain and section tests, PHP syntax checks, JavaScript syntax checks, and authenticated end-to-end tests on `new.la2plus.com` using only isolated demo balances/inventory.
