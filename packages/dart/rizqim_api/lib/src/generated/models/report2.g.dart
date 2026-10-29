// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'report2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Report2 _$Report2FromJson(Map<String, dynamic> json) => Report2(
  shiftUuid: json['shift_uuid'] as String,
  generatedAt: json['generated_at'] as String,
  period: Period5.fromJson(json['period'] as Map<String, dynamic>),
  sales: Sales3.fromJson(json['sales'] as Map<String, dynamic>),
  byTender: (json['by_tender'] as List<dynamic>)
      .map((e) => ByTender2.fromJson(e as Map<String, dynamic>))
      .toList(),
  cards: (json['cards'] as List<dynamic>)
      .map((e) => Cards2.fromJson(e as Map<String, dynamic>))
      .toList(),
  byChannel: (json['by_channel'] as List<dynamic>)
      .map((e) => ByChannel4.fromJson(e as Map<String, dynamic>))
      .toList(),
  byWaiter: (json['by_waiter'] as List<dynamic>)
      .map((e) => ByWaiter2.fromJson(e as Map<String, dynamic>))
      .toList(),
  discounts: Discounts2.fromJson(json['discounts'] as Map<String, dynamic>),
  voids: Voids2.fromJson(json['voids'] as Map<String, dynamic>),
  batchWriteoffs: BatchWriteoffs2.fromJson(
    json['batch_writeoffs'] as Map<String, dynamic>,
  ),
  refunds: Refunds5.fromJson(json['refunds'] as Map<String, dynamic>),
  deposits: Deposits2.fromJson(json['deposits'] as Map<String, dynamic>),
  credit: Credit5.fromJson(json['credit'] as Map<String, dynamic>),
  tips: Tips10.fromJson(json['tips'] as Map<String, dynamic>),
  cash: Cash3.fromJson(json['cash'] as Map<String, dynamic>),
  movements: (json['movements'] as List<dynamic>)
      .map((e) => Movements2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Report2ToJson(Report2 instance) => <String, dynamic>{
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
