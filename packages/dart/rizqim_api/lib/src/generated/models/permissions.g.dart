// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'permissions.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Permissions _$PermissionsFromJson(Map<String, dynamic> json) => Permissions(
  key: json['key'] as String,
  label: json['label'] as String,
  group: json['group'] as String,
);

Map<String, dynamic> _$PermissionsToJson(Permissions instance) =>
    <String, dynamic>{
      'key': instance.key,
      'label': instance.label,
      'group': instance.group,
    };
