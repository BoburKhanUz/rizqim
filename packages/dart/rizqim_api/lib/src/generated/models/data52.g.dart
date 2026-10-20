// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data52.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data52 _$Data52FromJson(Map<String, dynamic> json) => Data52(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data52ToJson(Data52 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
