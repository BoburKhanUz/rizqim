// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue _$QueueFromJson(Map<String, dynamic> json) => Queue(
  size: (json['size'] as num?)?.toInt(),
  oldestAt: json['oldest_at'] == null
      ? null
      : DateTime.parse(json['oldest_at'] as String),
);

Map<String, dynamic> _$QueueToJson(Queue instance) => <String, dynamic>{
  'size': instance.size,
  'oldest_at': instance.oldestAt?.toIso8601String(),
};
