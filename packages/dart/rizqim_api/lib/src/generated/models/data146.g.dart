// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data146.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data146 _$Data146FromJson(Map<String, dynamic> json) => Data146(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  userId: (json['user_id'] as num?)?.toInt(),
  userName: json['user_name'] as String?,
  role: json['role'] as String?,
  roleLabel: json['role_label'] as String?,
  effectiveFrom: json['effective_from'] as String,
  components: (json['components'] as List<dynamic>)
      .map((e) => Components3.fromJson(e as Map<String, dynamic>))
      .toList(),
  note: json['note'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data146ToJson(Data146 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'user_id': instance.userId,
  'user_name': instance.userName,
  'role': instance.role,
  'role_label': instance.roleLabel,
  'effective_from': instance.effectiveFrom,
  'components': instance.components,
  'note': instance.note,
  'updated_at': instance.updatedAt,
};
