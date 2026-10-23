// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pending_receipts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PendingReceipts _$PendingReceiptsFromJson(Map<String, dynamic> json) =>
    PendingReceipts(
      bills: (json['bills'] as List<dynamic>).map((e) => e as String).toList(),
      refunds: (json['refunds'] as List<dynamic>)
          .map((e) => e as String)
          .toList(),
    );

Map<String, dynamic> _$PendingReceiptsToJson(PendingReceipts instance) =>
    <String, dynamic>{'bills': instance.bills, 'refunds': instance.refunds};
