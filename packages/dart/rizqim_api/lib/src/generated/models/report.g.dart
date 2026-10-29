// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'report.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Report _$ReportFromJson(Map<String, dynamic> json) => Report(
  shiftUuid: json['shift_uuid'] as String,
  generatedAt: json['generated_at'] as String,
  period: Period4.fromJson(json['period'] as Map<String, dynamic>),
  sales: Sales.fromJson(json['sales'] as Map<String, dynamic>),
  byTender: (json['by_tender'] as List<dynamic>)
      .map((e) => ByTender.fromJson(e as Map<String, dynamic>))
      .toList(),
  cards: (json['cards'] as List<dynamic>)
      .map((e) => Cards.fromJson(e as Map<String, dynamic>))
      .toList(),
  byChannel: (json['by_channel'] as List<dynamic>)
      .map((e) => ByChannel3.fromJson(e as Map<String, dynamic>))
      .toList(),
  byWaiter: (json['by_waiter'] as List<dynamic>)
      .map((e) => ByWaiter.fromJson(e as Map<String, dynamic>))
      .toList(),
  discounts: Discounts.fromJson(json['discounts'] as Map<String, dynamic>),
  voids: Voids.fromJson(json['voids'] as Map<String, dynamic>),
  batchWriteoffs: BatchWriteoffs.fromJson(
    json['batch_writeoffs'] as Map<String, dynamic>,
  ),
  refunds: Refunds4.fromJson(json['refunds'] as Map<String, dynamic>),
  deposits: Deposits.fromJson(json['deposits'] as Map<String, dynamic>),
  credit: Credit4.fromJson(json['credit'] as Map<String, dynamic>),
  tips: Tips9.fromJson(json['tips'] as Map<String, dynamic>),
  cash: Cash2.fromJson(json['cash'] as Map<String, dynamic>),
  movements: (json['movements'] as List<dynamic>)
      .map((e) => Movements.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$ReportToJson(Report instance) => <String, dynamic>{
  'shift_uuid': instance.shiftUuid,
  'generated_at': instance.generatedAt,
  'period': instance.period,
  'sales': instance.sales,
  'by_tender': instance.byTender,
  'cards': instance.cards,
  'by_channel': instance.byChannel,
  'by_waiter': instance.byWaiter,
  'discounts': instance.discounts,
  'voids': instance.voids,
  'batch_writeoffs': instance.batchWriteoffs,
  'refunds': instance.refunds,
  'deposits': instance.deposits,
  'credit': instance.credit,
  'tips': instance.tips,
  'cash': instance.cash,
  'movements': instance.movements,
};
