// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data125.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data125 _$Data125FromJson(Map<String, dynamic> json) => Data125(
  role: Role3.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data125ToJson(Data125 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
