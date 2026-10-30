// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings6 _$Warnings6FromJson(Map<String, dynamic> json) => Warnings6(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings6ToJson(Warnings6 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
