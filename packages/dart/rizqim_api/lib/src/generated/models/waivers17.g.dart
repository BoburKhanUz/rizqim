// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'waivers17.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Waivers17 _$Waivers17FromJson(Map<String, dynamic> json) => Waivers17(
  charge: json['charge'] as String,
  reason: json['reason'] as String,
  waivedBy: (json['waived_by'] as num?)?.toInt(),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  waivedAt: json['waived_at'] as String?,
);

Map<String, dynamic> _$Waivers17ToJson(Waivers17 instance) => <String, dynamic>{
  'charge': instance.charge,
  'reason': instance.reason,
  'waived_by': instance.waivedBy,
  'approved_by': instance.approvedBy,
  'waived_at': instance.waivedAt,
};
