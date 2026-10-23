// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pending_receipts2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PendingReceipts2 _$PendingReceipts2FromJson(Map<String, dynamic> json) =>
    PendingReceipts2(
      bills: (json['bills'] as List<dynamic>).map((e) => e as String).toList(),
      refunds: (json['refunds'] as List<dynamic>)
          .map((e) => e as String)
          .toList(),
    );

Map<String, dynamic> _$PendingReceipts2ToJson(PendingReceipts2 instance) =>
    <String, dynamic>{'bills': instance.bills, 'refunds': instance.refunds};
