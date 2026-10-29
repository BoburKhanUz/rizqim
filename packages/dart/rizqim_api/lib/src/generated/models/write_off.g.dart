// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'write_off.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

WriteOff _$WriteOffFromJson(Map<String, dynamic> json) => WriteOff(
  uuid: json['uuid'] as String,
  quantity: json['quantity'] as num?,
  unit: json['unit'] as String,
  reason: json['reason'] as String,
  note: json['note'] as String?,
  businessDate: json['business_date'] as String,
  shiftId: (json['shift_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$WriteOffToJson(WriteOff instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'quantity': instance.quantity,
  'unit': instance.unit,
  'reason': instance.reason,
  'note': instance.note,
  'business_date': instance.businessDate,
  'shift_id': instance.shiftId,
};
