// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings5 _$Warnings5FromJson(Map<String, dynamic> json) => Warnings5(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings5ToJson(Warnings5 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
