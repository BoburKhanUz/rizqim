// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'operations.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Operations _$OperationsFromJson(Map<String, dynamic> json) => Operations(
  opId: json['op_id'] as String?,
  seq: (json['seq'] as num?)?.toInt(),
  type: json['type'] == null ? null : Type9.fromJson(json['type'] as String),
  occurredAt: json['occurred_at'] == null
      ? null
      : DateTime.parse(json['occurred_at'] as String),
  staffId: (json['staff_id'] as num?)?.toInt(),
  payload: (json['payload'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  originDevice: json['origin_device'] as String?,
  signed: json['signed'] as String?,
  mac: json['mac'] as String?,
  prev: json['prev'] as String?,
  kid: (json['kid'] as num?)?.toInt(),
);

Map<String, dynamic> _$OperationsToJson(Operations instance) =>
    <String, dynamic>{
      'op_id': instance.opId,
      'seq': instance.seq,
      'type': instance.type,
      'occurred_at': instance.occurredAt?.toIso8601String(),
      'staff_id': instance.staffId,
      'payload': instance.payload,
      'origin_device': instance.originDevice,
      'signed': instance.signed,
      'mac': instance.mac,
      'prev': instance.prev,
      'kid': instance.kid,
    };
