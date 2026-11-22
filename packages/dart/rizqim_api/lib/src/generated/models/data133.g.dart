// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data133.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data133 _$Data133FromJson(Map<String, dynamic> json) => Data133(
  role: Role3.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data133ToJson(Data133 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
