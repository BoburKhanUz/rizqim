// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'devices2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Devices2 _$Devices2FromJson(Map<String, dynamic> json) => Devices2(
  uuid: json['uuid'] as String,
  name: json['name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  smartKassa: json['smart_kassa'] as bool,
  fiscalKassaId: json['fiscal_kassa_id'] as String?,
  kassaStatus: json['kassa_status'] == null
      ? null
      : KassaStatus2.fromJson(json['kassa_status'] as Map<String, dynamic>),
  lastSeenAt: json['last_seen_at'] as String?,
);

Map<String, dynamic> _$Devices2ToJson(Devices2 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'branch_id': instance.branchId,
  'smart_kassa': instance.smartKassa,
  'fiscal_kassa_id': instance.fiscalKassaId,
  'kassa_status': instance.kassaStatus,
  'last_seen_at': instance.lastSeenAt,
};
