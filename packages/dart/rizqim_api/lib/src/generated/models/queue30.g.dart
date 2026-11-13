// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue30.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue30 _$Queue30FromJson(Map<String, dynamic> json) => Queue30(
  size: (json['size'] as num?)?.toInt(),
  oldestAt: json['oldest_at'] == null
      ? null
      : DateTime.parse(json['oldest_at'] as String),
);

Map<String, dynamic> _$Queue30ToJson(Queue30 instance) => <String, dynamic>{
  'size': instance.size,
  'oldest_at': instance.oldestAt?.toIso8601String(),
};
