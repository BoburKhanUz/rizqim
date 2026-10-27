// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data134.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data134 _$Data134FromJson(Map<String, dynamic> json) => Data134(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data134ToJson(Data134 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
