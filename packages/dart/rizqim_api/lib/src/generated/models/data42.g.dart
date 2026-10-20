// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data42.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data42 _$Data42FromJson(Map<String, dynamic> json) => Data42(
  role: json['role'] as String,
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data42ToJson(Data42 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
