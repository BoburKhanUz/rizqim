// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'staff.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Staff _$StaffFromJson(Map<String, dynamic> json) => Staff(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
);

Map<String, dynamic> _$StaffToJson(Staff instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
};
