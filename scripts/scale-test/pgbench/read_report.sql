-- Owner top-products report over 30 days (ReportController@topProducts, approximated from pg_stat_statements)
\set sid random(:store_min, :store_max)
select order_items.product_name as name, sum(order_items.quantity) as quantity, sum(order_items.total_price) as revenue, count(distinct orders.id) as orders
from orders join order_items on order_items.order_id = orders.id
where orders.store_id = :sid and orders.deleted_at is null and orders.created_at >= now() - interval '30 days' and orders.status = 'delivered' and order_items.deleted_at is null
group by order_items.product_name order by quantity desc limit 10;
