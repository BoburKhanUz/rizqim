// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data31.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data31 _$Data31FromJson(Map<String, dynamic> json) => Data31(
  branchId: (json['branch_id'] as num).toInt(),
  modules: Modules.fromJson(json['modules'] as Map<String, dynamic>),
  plans: (json['plans'] as List<dynamic>)
      .map((e) => e as List<dynamic>)
      .toList(),
);

Map<String, dynamic> _$Data31ToJson(Data31 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'modules': instance.modules,
  'plans': instance.plans,
};
