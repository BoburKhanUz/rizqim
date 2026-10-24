// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'operations.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Operations _$OperationsFromJson(Map<String, dynamic> json) => Operations(
  opId: json['op_id'] as String,
  seq: (json['seq'] as num).toInt(),
  type: Type9.fromJson(json['type'] as String),
  staffId: (json['staff_id'] as num).toInt(),
  payload: (json['payload'] as List<dynamic>).map((e) => e as String).toList(),
  occurredAt: json['occurred_at'] == null
      ? null
      : DateTime.parse(json['occurred_at'] as String),
);

Map<String, dynamic> _$OperationsToJson(Operations instance) =>
    <String, dynamic>{
      'op_id': instance.opId,
      'seq': instance.seq,
      'type': instance.type,
      'occurred_at': instance.occurredAt?.toIso8601String(),
      'staff_id': instance.staffId,
      'payload': instance.payload,
    };
