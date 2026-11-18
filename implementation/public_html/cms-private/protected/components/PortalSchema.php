<?php
/** Additive migration. No balances, credentials or existing game tables are rewritten. */
class PortalSchema
{
    public static function install(PDO $db, $prefix = '')
    {
        if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) throw new InvalidArgumentException('Invalid prefix');
        $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        $tables = [
            'portal_settings' => 'name VARCHAR(80) PRIMARY KEY, value TEXT NOT NULL',
            'portal_wallets' => 'user_id BIGINT PRIMARY KEY, available BIGINT NOT NULL DEFAULT 0, reserved BIGINT NOT NULL DEFAULT 0',
            'portal_ledger' => "id $id, user_id BIGINT NOT NULL, available_delta BIGINT NOT NULL, reserved_delta BIGINT NOT NULL, operation VARCHAR(40) NOT NULL, reference VARCHAR(128) NOT NULL UNIQUE, created_at BIGINT NOT NULL",
            'portal_requests' => "id $id, user_id BIGINT NOT NULL, request_key VARCHAR(80) NOT NULL UNIQUE, operation VARCHAR(40) NOT NULL, fingerprint VARCHAR(64) NOT NULL, result TEXT NOT NULL, created_at BIGINT NOT NULL",
            'portal_security' => 'user_id BIGINT PRIMARY KEY, pin_hash VARCHAR(255) NULL, failed_attempts INTEGER NOT NULL DEFAULT 0, locked_until BIGINT NOT NULL DEFAULT 0, email_verified_at BIGINT NULL, email_hash VARCHAR(64) NULL',
            'portal_codes' => "id $id, user_id BIGINT NOT NULL, purpose VARCHAR(80) NOT NULL, code_hash VARCHAR(255) NOT NULL, email_hash VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, consumed INTEGER NOT NULL DEFAULT 0, created_at BIGINT NOT NULL",
            'portal_hidden_accounts' => 'user_id BIGINT NOT NULL, link_id BIGINT NOT NULL, PRIMARY KEY(user_id,link_id)',
            'portal_discounts' => 'user_id BIGINT PRIMARY KEY, percent INTEGER NOT NULL, expires_at BIGINT NOT NULL, actor_id BIGINT NOT NULL, updated_at BIGINT NOT NULL',
            'portal_items' => "id $id, user_id BIGINT NOT NULL, gs_id BIGINT NOT NULL, source_key VARCHAR(128) NOT NULL UNIQUE, item_id BIGINT NOT NULL, name VARCHAR(255) NOT NULL, quantity BIGINT NOT NULL, enchant INTEGER NOT NULL DEFAULT 0, kind VARCHAR(16) NOT NULL, grade VARCHAR(4) NOT NULL, augmented INTEGER NOT NULL DEFAULT 0, attributes TEXT NOT NULL, state VARCHAR(24) NOT NULL, created_at BIGINT NOT NULL",
            'portal_lots' => "id $id, seller_id BIGINT NOT NULL, item_id BIGINT NOT NULL, gs_id BIGINT NOT NULL, price BIGINT NOT NULL, fee_percent INTEGER NOT NULL, state VARCHAR(24) NOT NULL, buyer_id BIGINT NULL, sold_at BIGINT NULL, created_at BIGINT NOT NULL",
            'portal_withdrawals' => "id $id, user_id BIGINT NOT NULL, amount BIGINT NOT NULL, fee BIGINT NOT NULL, net BIGINT NOT NULL, address VARCHAR(64) NOT NULL, state VARCHAR(24) NOT NULL, txid VARCHAR(64) NULL UNIQUE, actor_id BIGINT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL",
            'portal_deliveries' => "id $id, user_id BIGINT NOT NULL, item_id BIGINT NOT NULL, gs_id BIGINT NOT NULL, char_id BIGINT NOT NULL, operation_key VARCHAR(128) NOT NULL UNIQUE, state VARCHAR(24) NOT NULL, receipt VARCHAR(255) NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL",
            'portal_payments' => "id $id, user_id BIGINT NOT NULL, amount BIGINT NOT NULL, invoice_id VARCHAR(128) NULL UNIQUE, payment_url TEXT NULL, state VARCHAR(24) NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL",
            'portal_audit' => "id $id, actor_id BIGINT NOT NULL, action VARCHAR(80) NOT NULL, target VARCHAR(128) NOT NULL, details TEXT NOT NULL, created_at BIGINT NOT NULL",
        ];
        foreach ($tables as $name => $columns) $db->exec("CREATE TABLE IF NOT EXISTS {$prefix}{$name} ($columns)$tail");
        $indexes=[
            'portal_items'=>['owner'=>['user_id','gs_id','state','id']],
            'portal_lots'=>['catalog'=>['gs_id','state','id'],'seller'=>['seller_id','id'],'buyer'=>['buyer_id','id']],
            'portal_codes'=>['challenge'=>['user_id','purpose','id']],
            'portal_ledger'=>['history'=>['user_id','id']],
            'portal_withdrawals'=>['active'=>['user_id','state']],
            'portal_audit'=>['security'=>['actor_id','action','created_at']],
        ];
        foreach ($indexes as $table=>$definitions) foreach ($definitions as $suffix=>$columns) {
            $name=$prefix.$table.'_'.$suffix;
            if ($mysql) {
                $q=$db->query('SHOW INDEX FROM '.$prefix.$table);
                $exists=false; foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) if ($r['Key_name']===$name) $exists=true;
            } else {
                $q=$db->prepare("SELECT name FROM sqlite_master WHERE type='index' AND name=?"); $q->execute([$name]); $exists=(bool)$q->fetchColumn();
            }
            if (!$exists) $db->exec('CREATE INDEX '.$name.' ON '.$prefix.$table.'('.implode(',',$columns).')');
        }
        $settings = [
            'schema_version' => '1', 'cabinet_enabled' => '0', 'market_enabled' => '0',
            'warehouse_enabled' => '0', 'topup_enabled' => '0', 'withdrawals_enabled' => '0',
            'sale_fee_percent' => '10', 'bridge_url' => '', 'bridge_verified' => '0',
        ];
        foreach ($settings as $key => $value) {
            $check = $db->prepare("SELECT name FROM {$prefix}portal_settings WHERE name=?");
            $check->execute([$key]);
            if (!$check->fetchColumn()) {
                $q = $db->prepare("INSERT INTO {$prefix}portal_settings(name,value) VALUES(?,?)");
                $q->execute([$key, $value]);
            }
        }
    }
}
