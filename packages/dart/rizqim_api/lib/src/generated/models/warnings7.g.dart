// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings7 _$Warnings7FromJson(Map<String, dynamic> json) => Warnings7(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings7ToJson(Warnings7 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
