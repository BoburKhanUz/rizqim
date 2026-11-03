// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shifts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shifts _$ShiftsFromJson(Map<String, dynamic> json) => Shifts(
  uuid: json['uuid'] as String,
  businessDate: json['business_date'] as String,
  zNumber: (json['z_number'] as num?)?.toInt(),
  openedAt: json['opened_at'] as String?,
  closedAt: json['closed_at'] as String?,
  status: json['status'] as String,
);

Map<String, dynamic> _$ShiftsToJson(Shifts instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'business_date': instance.businessDate,
  'z_number': instance.zNumber,
  'opened_at': instance.openedAt,
  'closed_at': instance.closedAt,
  'status': instance.status,
};
