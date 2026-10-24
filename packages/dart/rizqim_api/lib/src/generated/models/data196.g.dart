// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data196.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data196 _$Data196FromJson(Map<String, dynamic> json) => Data196(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data196ToJson(Data196 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
