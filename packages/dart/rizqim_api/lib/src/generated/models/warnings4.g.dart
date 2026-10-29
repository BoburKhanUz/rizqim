// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings4 _$Warnings4FromJson(Map<String, dynamic> json) => Warnings4(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings4ToJson(Warnings4 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
