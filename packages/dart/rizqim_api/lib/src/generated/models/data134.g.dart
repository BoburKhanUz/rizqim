// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data134.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data134 _$Data134FromJson(Map<String, dynamic> json) => Data134(
  role: Role3.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data134ToJson(Data134 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
