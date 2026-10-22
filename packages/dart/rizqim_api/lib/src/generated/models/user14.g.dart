// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User14 _$User14FromJson(Map<String, dynamic> json) => User14(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$User14ToJson(User14 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'permissions': instance.permissions,
};
