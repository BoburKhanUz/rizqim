// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings9 _$Warnings9FromJson(Map<String, dynamic> json) => Warnings9(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings9ToJson(Warnings9 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
