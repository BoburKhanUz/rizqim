// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data110.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data110 _$Data110FromJson(Map<String, dynamic> json) => Data110(
  roles: (json['roles'] as List<dynamic>)
      .map((e) => Roles3.fromJson(e as Map<String, dynamic>))
      .toList(),
  permissions: (json['permissions'] as List<dynamic>)
      .map((e) => Permissions3.fromJson(e as Map<String, dynamic>))
      .toList(),
  matrix: (json['matrix'] as Map<String, dynamic>).map(
    (k, e) => MapEntry(k, Map<String, bool>.from(e as Map)),
  ),
  defaults: (json['defaults'] as Map<String, dynamic>).map(
    (k, e) => MapEntry(k, Map<String, bool>.from(e as Map)),
  ),
);

Map<String, dynamic> _$Data110ToJson(Data110 instance) => <String, dynamic>{
  'roles': instance.roles,
  'permissions': instance.permissions,
  'matrix': instance.matrix,
  'defaults': instance.defaults,
};
