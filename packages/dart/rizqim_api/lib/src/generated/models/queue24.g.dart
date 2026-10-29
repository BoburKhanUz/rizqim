// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue24 _$Queue24FromJson(Map<String, dynamic> json) => Queue24(
  size: (json['size'] as num?)?.toInt(),
  oldestAt: json['oldest_at'] == null
      ? null
      : DateTime.parse(json['oldest_at'] as String),
);

Map<String, dynamic> _$Queue24ToJson(Queue24 instance) => <String, dynamic>{
  'size': instance.size,
  'oldest_at': instance.oldestAt?.toIso8601String(),
};
