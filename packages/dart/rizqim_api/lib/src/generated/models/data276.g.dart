// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data276.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data276 _$Data276FromJson(Map<String, dynamic> json) => Data276(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  businessDate: json['business_date'] as String,
  guestName: json['guest_name'] as String,
  guestPhone: json['guest_phone'] as String?,
  guestPhoneMasked: json['guest_phone_masked'] as String?,
  partySize: (json['party_size'] as num).toInt(),
  quotedWaitMinutes: (json['quoted_wait_minutes'] as num?)?.toInt(),
  note: json['note'] as String?,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  position: (json['position'] as num?)?.toInt(),
  waitedMinutes: (json['waited_minutes'] as num).toInt(),
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  tableName: json['table_name'] as String?,
  orderUuid: json['order_uuid'] as String?,
  notifiedAt: json['notified_at'] as String?,
  seatedAt: json['seated_at'] as String?,
  closedAt: json['closed_at'] as String?,
  version: (json['version'] as num).toInt(),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Data276ToJson(Data276 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'business_date': instance.businessDate,
  'guest_name': instance.guestName,
  'guest_phone': instance.guestPhone,
  'guest_phone_masked': instance.guestPhoneMasked,
  'party_size': instance.partySize,
  'quoted_wait_minutes': instance.quotedWaitMinutes,
  'note': instance.note,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'position': instance.position,
  'waited_minutes': instance.waitedMinutes,
  'dining_table_id': instance.diningTableId,
  'table_name': instance.tableName,
  'order_uuid': instance.orderUuid,
  'notified_at': instance.notifiedAt,
  'seated_at': instance.seatedAt,
  'closed_at': instance.closedAt,
  'version': instance.version,
  'created_at': instance.createdAt,
};
