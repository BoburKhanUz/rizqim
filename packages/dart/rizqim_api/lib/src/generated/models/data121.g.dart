// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data121.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data121 _$Data121FromJson(Map<String, dynamic> json) => Data121(
  branchId: (json['branch_id'] as num).toInt(),
  weekendDays: (json['weekend_days'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  holidays: (json['holidays'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data121ToJson(Data121 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'weekend_days': instance.weekendDays,
  'holidays': instance.holidays,
};
