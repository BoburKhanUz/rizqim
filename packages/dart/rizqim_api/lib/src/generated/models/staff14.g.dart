// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'staff14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Staff14 _$Staff14FromJson(Map<String, dynamic> json) => Staff14(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Staff14ToJson(Staff14 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'photo_url': instance.photoUrl,
};
