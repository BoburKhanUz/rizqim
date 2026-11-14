// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user23.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User23 _$User23FromJson(Map<String, dynamic> json) => User23(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$User23ToJson(User23 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'permissions': instance.permissions,
};
