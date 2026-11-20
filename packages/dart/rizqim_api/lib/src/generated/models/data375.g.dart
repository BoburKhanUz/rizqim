// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data375.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data375 _$Data375FromJson(Map<String, dynamic> json) => Data375(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data375ToJson(Data375 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
