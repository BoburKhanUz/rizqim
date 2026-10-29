// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tips8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tips8 _$Tips8FromJson(Map<String, dynamic> json) => Tips8(
  uuid: json['uuid'] as String,
  method: json['method'] as String,
  source: json['source'] as String?,
  amount: json['amount'] as String,
  originalAmount: json['original_amount'] as String,
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff19.fromJson(json['staff'] as Map<String, dynamic>),
  holders: (json['holders'] as List<dynamic>)
      .map((e) => Holders8.fromJson(e as Map<String, dynamic>))
      .toList(),
  businessDate: json['business_date'] as String,
  orderUuid: json['order_uuid'] as String?,
  billUuid: json['bill_uuid'] as String?,
  paymentUuid: json['payment_uuid'] as String?,
  shiftUuid: json['shift_uuid'] as String?,
  recordedBy: json['recorded_by'] == null
      ? null
      : RecordedBy8.fromJson(json['recorded_by'] as Map<String, dynamic>),
  closed: json['closed'] as bool,
  corrections: (json['corrections'] as List<dynamic>)
      .map((e) => Corrections9.fromJson(e as Map<String, dynamic>))
      .toList(),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Tips8ToJson(Tips8 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'method': instance.method,
  'source': instance.source,
  'amount': instance.amount,
  'original_amount': instance.originalAmount,
  'mode': instance.mode,
  'staff': instance.staff,
  'holders': instance.holders,
  'business_date': instance.businessDate,
  'order_uuid': instance.orderUuid,
  'bill_uuid': instance.billUuid,
  'payment_uuid': instance.paymentUuid,
  'shift_uuid': instance.shiftUuid,
  'recorded_by': instance.recordedBy,
  'closed': instance.closed,
  'corrections': instance.corrections,
  'created_at': instance.createdAt,
};
