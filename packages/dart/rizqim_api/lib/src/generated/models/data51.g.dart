// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data51.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data51 _$Data51FromJson(Map<String, dynamic> json) => Data51(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data51ToJson(Data51 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
