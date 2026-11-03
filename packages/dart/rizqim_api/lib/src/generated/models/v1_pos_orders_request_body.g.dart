// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersRequestBody _$V1PosOrdersRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersRequestBody(
  channel: Channel6.fromJson(json['channel'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
  tableId: (json['table_id'] as num?)?.toInt(),
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  waiterId: (json['waiter_id'] as num?)?.toInt(),
  externalId: json['external_id'] as String?,
  note: json['note'] as String?,
  send: json['send'] as bool?,
  queueNumber: (json['queue_number'] as num?)?.toInt(),
  items: (json['items'] as List<dynamic>?)
      ?.map((e) => Items22.fromJson(e as Map<String, dynamic>))
      .toList(),
  tableLabel: json['table_label'] as String?,
  pickupAt: json['pickup_at'] == null
      ? null
      : DateTime.parse(json['pickup_at'] as String),
);

Map<String, dynamic> _$V1PosOrdersRequestBodyToJson(
  V1PosOrdersRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'channel': instance.channel,
  'table_id': instance.tableId,
  'guests_count': instance.guestsCount,
  'waiter_id': instance.waiterId,
  'external_id': instance.externalId,
  'note': instance.note,
  'send': instance.send,
  'queue_number': instance.queueNumber,
  'items': instance.items,
  'table_label': instance.tableLabel,
  'pickup_at': instance.pickupAt?.toIso8601String(),
};
