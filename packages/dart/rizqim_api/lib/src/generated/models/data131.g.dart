// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data131.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data131 _$Data131FromJson(Map<String, dynamic> json) => Data131(
  role: Role3.fromJson(json['role'] as String),
  permission: json['permission'] as String,
  allowed: json['allowed'] as bool,
);

Map<String, dynamic> _$Data131ToJson(Data131 instance) => <String, dynamic>{
  'role': instance.role,
  'permission': instance.permission,
  'allowed': instance.allowed,
};
