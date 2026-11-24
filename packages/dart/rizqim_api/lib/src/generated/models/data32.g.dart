// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data32.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data32 _$Data32FromJson(Map<String, dynamic> json) => Data32(
  branchId: (json['branch_id'] as num).toInt(),
  modules: Modules.fromJson(json['modules'] as Map<String, dynamic>),
  plans: (json['plans'] as List<dynamic>)
      .map((e) => e as List<dynamic>)
      .toList(),
);

Map<String, dynamic> _$Data32ToJson(Data32 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'modules': instance.modules,
  'plans': instance.plans,
};
