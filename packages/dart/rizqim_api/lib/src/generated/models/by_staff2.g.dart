// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_staff2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByStaff2 _$ByStaff2FromJson(Map<String, dynamic> json) => ByStaff2(
  staffId: (json['staff_id'] as num).toInt(),
  name: json['name'] as String?,
  role: json['role'] as String?,
  personal: json['personal'] as String,
  poolShare: json['pool_share'] as String,
  poolShareClosed: json['pool_share_closed'] as String,
  total: json['total'] as String,
);

Map<String, dynamic> _$ByStaff2ToJson(ByStaff2 instance) => <String, dynamic>{
  'staff_id': instance.staffId,
  'name': instance.name,
  'role': instance.role,
  'personal': instance.personal,
  'pool_share': instance.poolShare,
  'pool_share_closed': instance.poolShareClosed,
  'total': instance.total,
};
