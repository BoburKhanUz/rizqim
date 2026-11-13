// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data188.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data188 _$Data188FromJson(Map<String, dynamic> json) => Data188(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data188ToJson(Data188 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
