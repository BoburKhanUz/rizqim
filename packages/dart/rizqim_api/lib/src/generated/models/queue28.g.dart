// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue28.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue28 _$Queue28FromJson(Map<String, dynamic> json) => Queue28(
  size: (json['size'] as num?)?.toInt(),
  oldestAt: json['oldest_at'] == null
      ? null
      : DateTime.parse(json['oldest_at'] as String),
);

Map<String, dynamic> _$Queue28ToJson(Queue28 instance) => <String, dynamic>{
  'size': instance.size,
  'oldest_at': instance.oldestAt?.toIso8601String(),
};
