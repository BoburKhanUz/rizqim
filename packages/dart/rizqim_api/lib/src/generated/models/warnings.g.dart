// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings _$WarningsFromJson(Map<String, dynamic> json) => Warnings(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$WarningsToJson(Warnings instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
