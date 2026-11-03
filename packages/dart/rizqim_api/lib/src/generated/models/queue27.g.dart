// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue27 _$Queue27FromJson(Map<String, dynamic> json) => Queue27(
  size: (json['size'] as num?)?.toInt(),
  oldestAt: json['oldest_at'] == null
      ? null
      : DateTime.parse(json['oldest_at'] as String),
);

Map<String, dynamic> _$Queue27ToJson(Queue27 instance) => <String, dynamic>{
  'size': instance.size,
  'oldest_at': instance.oldestAt?.toIso8601String(),
};
