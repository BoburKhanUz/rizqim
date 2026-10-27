// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data90.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data90 _$Data90FromJson(Map<String, dynamic> json) => Data90(
  role: Role.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data90ToJson(Data90 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
