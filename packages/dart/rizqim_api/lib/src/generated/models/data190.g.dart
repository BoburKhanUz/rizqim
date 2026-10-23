// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data190.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data190 _$Data190FromJson(Map<String, dynamic> json) => Data190(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  phone: json['phone'] as String?,
  slug: json['slug'] as String,
  isActive: json['is_active'] as bool,
  commissionRate: json['commission_rate'] as String,
  branchesCount: (json['branches_count'] as num?)?.toInt(),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  owner: json['owner'] == null
      ? null
      : Owner.fromJson(json['owner'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data190ToJson(Data190 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'phone': instance.phone,
  'slug': instance.slug,
  'is_active': instance.isActive,
  'commission_rate': instance.commissionRate,
  'branches_count': instance.branchesCount,
  'created_at': instance.createdAt?.toIso8601String(),
  'owner': instance.owner,
};
