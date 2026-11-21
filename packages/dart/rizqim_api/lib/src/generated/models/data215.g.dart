// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data215.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data215 _$Data215FromJson(Map<String, dynamic> json) => Data215(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data215ToJson(Data215 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
