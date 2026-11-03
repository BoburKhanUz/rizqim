// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data178.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data178 _$Data178FromJson(Map<String, dynamic> json) => Data178(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data178ToJson(Data178 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
