// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User15 _$User15FromJson(Map<String, dynamic> json) => User15(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$User15ToJson(User15 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'permissions': instance.permissions,
};
