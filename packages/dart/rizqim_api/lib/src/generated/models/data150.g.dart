// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data150.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data150 _$Data150FromJson(Map<String, dynamic> json) => Data150(
  branchId: (json['branch_id'] as num).toInt(),
  weekendDays: (json['weekend_days'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  holidays: (json['holidays'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data150ToJson(Data150 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'weekend_days': instance.weekendDays,
  'holidays': instance.holidays,
};
