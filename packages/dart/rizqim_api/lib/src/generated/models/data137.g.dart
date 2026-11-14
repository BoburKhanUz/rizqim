// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data137.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data137 _$Data137FromJson(Map<String, dynamic> json) => Data137(
  hallId: (json['hall_id'] as num).toInt(),
  hallName: json['hall_name'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
  rates: Rates5.fromJson(json['rates'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data137ToJson(Data137 instance) => <String, dynamic>{
  'hall_id': instance.hallId,
  'hall_name': instance.hallName,
  'branch_id': instance.branchId,
  'is_active': instance.isActive,
  'rates': instance.rates,
};
