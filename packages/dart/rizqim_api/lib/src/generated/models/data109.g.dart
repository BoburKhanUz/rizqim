// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data109.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data109 _$Data109FromJson(Map<String, dynamic> json) => Data109(
  role: Role3.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data109ToJson(Data109 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
