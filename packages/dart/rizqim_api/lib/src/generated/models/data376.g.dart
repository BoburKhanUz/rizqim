// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data376.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data376 _$Data376FromJson(Map<String, dynamic> json) => Data376(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data376ToJson(Data376 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
