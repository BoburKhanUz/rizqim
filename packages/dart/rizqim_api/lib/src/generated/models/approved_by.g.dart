// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'approved_by.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ApprovedBy _$ApprovedByFromJson(Map<String, dynamic> json) => ApprovedBy(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
);

Map<String, dynamic> _$ApprovedByToJson(ApprovedBy instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'role': instance.role,
    };
