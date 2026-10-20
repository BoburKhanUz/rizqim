// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User7 _$User7FromJson(Map<String, dynamic> json) => User7(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$User7ToJson(User7 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
  'branch_id': instance.branchId,
};
