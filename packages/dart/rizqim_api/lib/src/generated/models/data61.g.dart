// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data61.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data61 _$Data61FromJson(Map<String, dynamic> json) => Data61(
  role: json['role'] as String,
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data61ToJson(Data61 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
