// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data77.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data77 _$Data77FromJson(Map<String, dynamic> json) => Data77(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data77ToJson(Data77 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
