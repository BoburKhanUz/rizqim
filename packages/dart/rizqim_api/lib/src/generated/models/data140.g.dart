// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data140.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data140 _$Data140FromJson(Map<String, dynamic> json) => Data140(
  hallId: (json['hall_id'] as num).toInt(),
  hallName: json['hall_name'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
  rates: Rates5.fromJson(json['rates'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data140ToJson(Data140 instance) => <String, dynamic>{
  'hall_id': instance.hallId,
  'hall_name': instance.hallName,
  'branch_id': instance.branchId,
  'is_active': instance.isActive,
  'rates': instance.rates,
};
