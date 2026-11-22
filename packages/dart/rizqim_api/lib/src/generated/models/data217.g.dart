// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data217.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data217 _$Data217FromJson(Map<String, dynamic> json) => Data217(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data217ToJson(Data217 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
