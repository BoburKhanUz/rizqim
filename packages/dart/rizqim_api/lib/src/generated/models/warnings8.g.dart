// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings8 _$Warnings8FromJson(Map<String, dynamic> json) => Warnings8(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings8ToJson(Warnings8 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
