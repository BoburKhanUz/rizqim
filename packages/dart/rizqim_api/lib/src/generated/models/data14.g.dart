// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data14 _$Data14FromJson(Map<String, dynamic> json) => Data14(
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles2.fromJson(e as Map<String, dynamic>))
      .toList(),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => Permissions2.fromJson(e as Map<String, dynamic>))
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

Map<String, dynamic> _$Data14ToJson(Data14 instance) => <String, dynamic>{
  'roles': instance.roles,
  'permissions': instance.permissions,
  'grants': instance.grants,
  'defaults': instance.defaults,
};
