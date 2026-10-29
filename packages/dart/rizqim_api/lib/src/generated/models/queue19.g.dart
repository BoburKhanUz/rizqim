// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue19.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue19 _$Queue19FromJson(Map<String, dynamic> json) => Queue19(
  size: (json['size'] as num?)?.toInt(),
  oldestAt: json['oldest_at'] == null
      ? null
      : DateTime.parse(json['oldest_at'] as String),
);

Map<String, dynamic> _$Queue19ToJson(Queue19 instance) => <String, dynamic>{
  'size': instance.size,
  'oldest_at': instance.oldestAt?.toIso8601String(),
};
