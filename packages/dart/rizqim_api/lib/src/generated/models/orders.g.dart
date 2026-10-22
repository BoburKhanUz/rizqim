// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'orders.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Orders _$OrdersFromJson(Map<String, dynamic> json) => Orders(
  uuid: json['uuid'] as String,
  shortCode: json['short_code'] as String?,
  status: json['status'] as String,
  channel: json['channel'] as String,
  version: (json['version'] as num).toInt(),
  openedAt: json['opened_at'] as String?,
  openMinutes: (json['open_minutes'] as num).toInt(),
  overdue: json['overdue'] as bool,
  billPrinted: json['bill_printed'] as bool,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  waiter: json['waiter'] == null
      ? null
      : Waiter.fromJson(json['waiter'] as Map<String, dynamic>),
  totalPrice: json['total_price'] as String,
  itemsCount: (json['items_count'] as num).toInt(),
  unsentCount: (json['unsent_count'] as num).toInt(),
);

Map<String, dynamic> _$OrdersToJson(Orders instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'status': instance.status,
  'channel': instance.channel,
  'version': instance.version,
  'opened_at': instance.openedAt,
  'open_minutes': instance.openMinutes,
  'overdue': instance.overdue,
  'bill_printed': instance.billPrinted,
  'guests_count': instance.guestsCount,
  'waiter': instance.waiter,
  'total_price': instance.totalPrice,
  'items_count': instance.itemsCount,
  'unsent_count': instance.unsentCount,
};
