// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'member3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Member3 _$Member3FromJson(Map<String, dynamic> json) => Member3(
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  code: json['code'] as String,
  isActive: json['is_active'] as bool,
);

Map<String, dynamic> _$Member3ToJson(Member3 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'code': instance.code,
  'is_active': instance.isActive,
};
