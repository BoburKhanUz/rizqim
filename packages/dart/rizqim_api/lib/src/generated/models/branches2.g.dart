// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branches2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Branches2 _$Branches2FromJson(Map<String, dynamic> json) => Branches2(
  branchId: (json['branch_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
);

Map<String, dynamic> _$Branches2ToJson(Branches2 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'is_active': instance.isActive,
};
