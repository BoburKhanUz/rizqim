// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data13 _$Data13FromJson(Map<String, dynamic> json) => Data13(
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles.fromJson(e as Map<String, dynamic>))
      .toList(),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => Permissions.fromJson(e as Map<String, dynamic>))
      .toList(),
  grants: (json['grants'] as Map<String, dynamic>).map(
    (k, e) =>
        MapEntry(k, (e as List<dynamic>).map((e) => e as String).toList()),
  ),
  defaults: (json['defaults'] as Map<String, dynamic>).map(
    (k, e) =>
        MapEntry(k, (e as List<dynamic>).map((e) => e as String).toList()),
  ),
);

Map<String, dynamic> _$Data13ToJson(Data13 instance) => <String, dynamic>{
  'roles': instance.roles,
  'permissions': instance.permissions,
  'grants': instance.grants,
  'defaults': instance.defaults,
};
