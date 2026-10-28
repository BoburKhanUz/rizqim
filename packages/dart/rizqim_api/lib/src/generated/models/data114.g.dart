// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data114.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data114 _$Data114FromJson(Map<String, dynamic> json) => Data114(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: UserRole.fromJson(json['role'] as String),
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data114ToJson(Data114 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
