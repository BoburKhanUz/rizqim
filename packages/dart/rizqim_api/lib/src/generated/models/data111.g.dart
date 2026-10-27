// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data111.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data111 _$Data111FromJson(Map<String, dynamic> json) => Data111(
  role: Role.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data111ToJson(Data111 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
