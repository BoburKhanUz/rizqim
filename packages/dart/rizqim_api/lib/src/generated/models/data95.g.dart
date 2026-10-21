// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data95.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data95 _$Data95FromJson(Map<String, dynamic> json) => Data95(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data95ToJson(Data95 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
