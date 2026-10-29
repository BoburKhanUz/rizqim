// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data115.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data115 _$Data115FromJson(Map<String, dynamic> json) => Data115(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  totals: Totals.fromJson(json['totals'] as Map<String, dynamic>),
  byDay: (json['by_day'] as List<dynamic>)
      .map((e) => ByDay.fromJson(e as Map<String, dynamic>))
      .toList(),
  byStaff: (json['by_staff'] as List<dynamic>)
      .map((e) => ByStaff2.fromJson(e as Map<String, dynamic>))
      .toList(),
  undistributed: json['undistributed'] as String,
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data115ToJson(Data115 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'totals': instance.totals,
  'by_day': instance.byDay,
  'by_staff': instance.byStaff,
  'undistributed': instance.undistributed,
  'staff': instance.staff,
};
