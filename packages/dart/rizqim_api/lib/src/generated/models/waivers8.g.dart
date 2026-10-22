// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'waivers8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Waivers8 _$Waivers8FromJson(Map<String, dynamic> json) => Waivers8(
  charge: json['charge'] as String,
  reason: json['reason'] as String,
  waivedBy: (json['waived_by'] as num?)?.toInt(),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  waivedAt: json['waived_at'] as String?,
);

Map<String, dynamic> _$Waivers8ToJson(Waivers8 instance) => <String, dynamic>{
  'charge': instance.charge,
  'reason': instance.reason,
  'waived_by': instance.waivedBy,
  'approved_by': instance.approvedBy,
  'waived_at': instance.waivedAt,
};
