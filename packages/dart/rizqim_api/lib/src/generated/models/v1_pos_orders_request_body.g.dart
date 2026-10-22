// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersRequestBody _$V1PosOrdersRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersRequestBody(
  channel: Channel4.fromJson(json['channel'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
  tableId: (json['table_id'] as num?)?.toInt(),
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  waiterId: (json['waiter_id'] as num?)?.toInt(),
  externalId: json['external_id'] as String?,
  note: json['note'] as String?,
  send: json['send'] as bool?,
  items: (json['items'] as List<dynamic>?)
      ?.map((e) => Items21.fromJson(e as Map<String, dynamic>))
      .toList(),
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
  'items': instance.items,
};
