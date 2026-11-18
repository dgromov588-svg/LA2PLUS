<?php
/** Retryable outbox. The game endpoint must deduplicate operation_key before inventory writes. */
class PortalDelivery
{
    public static function process(PortalStore $store,$id,callable $bridge)
    {
        return $store->atomic(function () use ($store,$id,$bridge) {
            $row=$store->query('SELECT d.*,i.item_id AS game_item_id,i.name,i.quantity,i.enchant,i.augmented,i.attributes FROM {{portal_deliveries}} d JOIN {{portal_items}} i ON i.id=d.item_id WHERE d.id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['state']==='delivered') return ['already'=>true];
            // Lock the account ledger row to serialize retries across workers.
            $store->wallet($row['user_id'],true);
            $state=$store->query('SELECT state FROM {{portal_deliveries}} WHERE id=?',[$id])->fetchColumn();
            if ($state==='delivered') return ['already'=>true];
            $receipt=$bridge($row);
            if (empty($receipt['ok']) || ($receipt['key']??'')!==$row['operation_key'] || empty($receipt['receipt']) || ($receipt['state']??'')!=='delivered') throw new RuntimeException('Доставка не подтверждена.');
            $store->query("UPDATE {{portal_deliveries}} SET state='delivered',receipt=?,updated_at=? WHERE id=?",[$receipt['receipt'],time(),$id]);
            $store->query("UPDATE {{portal_items}} SET state='delivered' WHERE id=?",[$row['item_id']]);
            $store->audit($row['user_id'],'item.delivered',$row['item_id'],['receipt'=>$receipt['receipt']]);
            return ['already'=>false,'receipt'=>$receipt['receipt']];
        });
    }
}
