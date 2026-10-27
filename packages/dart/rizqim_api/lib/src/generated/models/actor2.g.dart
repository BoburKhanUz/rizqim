// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'actor2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Actor2 _$Actor2FromJson(Map<String, dynamic> json) => Actor2(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
);

Map<String, dynamic> _$Actor2ToJson(Actor2 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
};
