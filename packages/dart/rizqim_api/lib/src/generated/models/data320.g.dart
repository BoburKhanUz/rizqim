// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data320.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data320 _$Data320FromJson(Map<String, dynamic> json) => Data320(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data320ToJson(Data320 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
