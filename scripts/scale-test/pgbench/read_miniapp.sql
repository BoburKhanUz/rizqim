-- Mini App poll (every 10-15 s per open app): notification feed + unread badge (NotificationFeedService), customer order list
\set uid random(:cust_min, :cust_max)
select count(*) from notification_logs where user_id = :uid and is_read = false;
select * from notification_logs where user_id = :uid order by id desc limit 20 offset 0;
select * from orders where user_id = :uid and deleted_at is null order by created_at desc, id desc limit 20;
