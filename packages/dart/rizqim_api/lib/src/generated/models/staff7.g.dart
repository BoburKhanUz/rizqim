// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'staff7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Staff7 _$Staff7FromJson(Map<String, dynamic> json) => Staff7(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  roleLabel: json['role_label'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Staff7ToJson(Staff7 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'role_label': instance.roleLabel,
  'branch_id': instance.branchId,
};
