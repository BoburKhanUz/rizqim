// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'roles.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Roles _$RolesFromJson(Map<String, dynamic> json) => Roles(
  role: Role2.fromJson(json['role'] as String),
  label: json['label'] as String,
  editable: json['editable'] as bool,
);

Map<String, dynamic> _$RolesToJson(Roles instance) => <String, dynamic>{
  'role': instance.role,
  'label': instance.label,
  'editable': instance.editable,
};
