// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'requests.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Requests _$RequestsFromJson(Map<String, dynamic> json) => Requests(
  requestId: json['request_id'] as String,
  reprint: json['reprint'] as bool,
  printerId: (json['printer_id'] as num?)?.toInt(),
  targetDevice: json['target_device'] as String?,
  requestedBy: (json['requested_by'] as num?)?.toInt(),
  requestedAt: json['requested_at'] as String?,
  bill: Bill.fromJson(json['bill'] as Map<String, dynamic>),
  order: Order4.fromJson(json['order'] as Map<String, dynamic>),
  billing: Billing.fromJson(json['billing'] as Map<String, dynamic>),
);

Map<String, dynamic> _$RequestsToJson(Requests instance) => <String, dynamic>{
  'request_id': instance.requestId,
  'reprint': instance.reprint,
  'printer_id': instance.printerId,
  'target_device': instance.targetDevice,
  'requested_by': instance.requestedBy,
  'requested_at': instance.requestedAt,
  'bill': instance.bill,
  'order': instance.order,
  'billing': instance.billing,
};
