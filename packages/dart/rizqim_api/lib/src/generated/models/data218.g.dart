// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data218.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data218 _$Data218FromJson(Map<String, dynamic> json) => Data218(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data218ToJson(Data218 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
