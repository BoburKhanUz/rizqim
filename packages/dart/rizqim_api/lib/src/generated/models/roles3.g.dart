// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'roles3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Roles3 _$Roles3FromJson(Map<String, dynamic> json) => Roles3(
  role: Role2.fromJson(json['role'] as String),
  label: json['label'] as String,
  editable: json['editable'] as bool,
);

Map<String, dynamic> _$Roles3ToJson(Roles3 instance) => <String, dynamic>{
  'role': instance.role,
  'label': instance.label,
  'editable': instance.editable,
};
