// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data123.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data123 _$Data123FromJson(Map<String, dynamic> json) => Data123(
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles.fromJson(e as String))
      .toList(),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => Permissions.fromJson(e as Map<String, dynamic>))
      .toList(),
  matrix: (json['matrix'] as Map<String, dynamic>).map(
    (k, e) => MapEntry(k, Map<String, bool>.from(e as Map)),
  ),
  defaults: (json['defaults'] as Map<String, dynamic>).map(
    (k, e) => MapEntry(k, Map<String, bool>.from(e as Map)),
  ),
);

Map<String, dynamic> _$Data123ToJson(Data123 instance) => <String, dynamic>{
  'roles': instance.roles,
  'permissions': instance.permissions,
  'matrix': instance.matrix,
  'defaults': instance.defaults,
};
