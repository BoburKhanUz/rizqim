// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'warnings2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Warnings2 _$Warnings2FromJson(Map<String, dynamic> json) => Warnings2(
  type: json['type'] as String,
  userId: (json['user_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  count: (json['count'] as num?)?.toInt(),
);

Map<String, dynamic> _$Warnings2ToJson(Warnings2 instance) => <String, dynamic>{
  'type': instance.type,
  'user_id': instance.userId,
  'name': instance.name,
  'count': instance.count,
};
