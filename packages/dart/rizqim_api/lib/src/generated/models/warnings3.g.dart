// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings3 _$Warnings3FromJson(Map<String, dynamic> json) => Warnings3(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings3ToJson(Warnings3 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
