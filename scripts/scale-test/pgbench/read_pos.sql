-- Till/KDS poll: the branch's open POS orders (PosOrderController@index / floor), their lines
\set bid random(:branch_min, :branch_max)
select * from orders where branch_id = :bid and channel in ('pos_hall','pos_takeaway') and status not in ('delivered','canceled','rejected','refunded') and deleted_at is null order by created_at asc, id asc;
select i.* from order_items i join orders o on o.id = i.order_id where o.branch_id = :bid and o.status in ('accepted','preparing','ready') and o.deleted_at is null and i.deleted_at is null;
