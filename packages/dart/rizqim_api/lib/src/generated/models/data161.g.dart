// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data161.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data161 _$Data161FromJson(Map<String, dynamic> json) => Data161(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data161ToJson(Data161 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
