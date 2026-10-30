// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data124.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data124 _$Data124FromJson(Map<String, dynamic> json) => Data124(
  role: Role3.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data124ToJson(Data124 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
