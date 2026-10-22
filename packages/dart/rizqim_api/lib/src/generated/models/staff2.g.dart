// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'staff2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Staff2 _$Staff2FromJson(Map<String, dynamic> json) => Staff2(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Staff2ToJson(Staff2 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'photo_url': instance.photoUrl,
};
