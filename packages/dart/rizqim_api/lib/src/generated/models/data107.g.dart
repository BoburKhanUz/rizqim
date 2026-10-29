// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data107.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data107 _$Data107FromJson(Map<String, dynamic> json) => Data107(
  role: Role.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data107ToJson(Data107 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
