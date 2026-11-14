// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls6 _$Halls6FromJson(Map<String, dynamic> json) => Halls6(
  hallId: (json['hall_id'] as num).toInt(),
  hallName: json['hall_name'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  isActive: json['is_active'] as bool,
  rates: Rates7.fromJson(json['rates'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Halls6ToJson(Halls6 instance) => <String, dynamic>{
  'hall_id': instance.hallId,
  'hall_name': instance.hallName,
  'branch_id': instance.branchId,
  'is_active': instance.isActive,
  'rates': instance.rates,
};
