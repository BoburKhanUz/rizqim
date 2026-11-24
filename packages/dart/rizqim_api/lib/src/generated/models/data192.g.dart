// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data192.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data192 _$Data192FromJson(Map<String, dynamic> json) => Data192(
  branchId: (json['branch_id'] as num).toInt(),
  weekendDays: (json['weekend_days'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  holidays: (json['holidays'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data192ToJson(Data192 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'weekend_days': instance.weekendDays,
  'holidays': instance.holidays,
};
