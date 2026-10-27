// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data122.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data122 _$Data122FromJson(Map<String, dynamic> json) => Data122(
  branchId: (json['branch_id'] as num).toInt(),
  weekendDays: (json['weekend_days'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  holidays: (json['holidays'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data122ToJson(Data122 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'weekend_days': instance.weekendDays,
  'holidays': instance.holidays,
};
