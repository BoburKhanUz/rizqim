// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settlement.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settlement _$SettlementFromJson(Map<String, dynamic> json) => Settlement(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  billUuid: json['bill_uuid'] as String?,
  appliedAmount: json['applied_amount'] as String,
  excessAmount: json['excess_amount'] as String,
  excessStatus: json['excess_status'] as String,
  settledAt: json['settled_at'] as String?,
  extraBills: (json['extra_bills'] as List<dynamic>)
      .map((e) => ExtraBills.fromJson(e as Map<String, dynamic>))
      .toList(),
  extraBillsNotice: json['extra_bills_notice'] as String?,
);

Map<String, dynamic> _$SettlementToJson(Settlement instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'bill_uuid': instance.billUuid,
      'applied_amount': instance.appliedAmount,
      'excess_amount': instance.excessAmount,
      'excess_status': instance.excessStatus,
      'settled_at': instance.settledAt,
      'extra_bills': instance.extraBills,
      'extra_bills_notice': instance.extraBillsNotice,
    };
