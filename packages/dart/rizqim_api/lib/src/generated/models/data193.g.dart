// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data193.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data193 _$Data193FromJson(Map<String, dynamic> json) => Data193(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data193ToJson(Data193 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
