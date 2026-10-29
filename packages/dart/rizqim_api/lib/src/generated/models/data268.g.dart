// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data268.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data268 _$Data268FromJson(Map<String, dynamic> json) => Data268(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  staffCode: json['staff_code'] as String?,
  photoUrl: json['photo_url'] as String?,
);

Map<String, dynamic> _$Data268ToJson(Data268 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'staff_code': instance.staffCode,
  'photo_url': instance.photoUrl,
};
