// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'report3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Report3 _$Report3FromJson(Map<String, dynamic> json) => Report3(
  shiftUuid: json['shift_uuid'] as String,
  generatedAt: json['generated_at'] as String,
  period: Period8.fromJson(json['period'] as Map<String, dynamic>),
  sales: Sales9.fromJson(json['sales'] as Map<String, dynamic>),
  byTender: (json['by_tender'] as List<dynamic>)
      .map((e) => ByTender5.fromJson(e as Map<String, dynamic>))
      .toList(),
  cards: (json['cards'] as List<dynamic>)
      .map((e) => Cards5.fromJson(e as Map<String, dynamic>))
      .toList(),
  byChannel: (json['by_channel'] as List<dynamic>)
      .map((e) => ByChannel7.fromJson(e as Map<String, dynamic>))
      .toList(),
  byWaiter: (json['by_waiter'] as List<dynamic>)
      .map((e) => ByWaiter5.fromJson(e as Map<String, dynamic>))
      .toList(),
  discounts: Discounts5.fromJson(json['discounts'] as Map<String, dynamic>),
  voids: Voids5.fromJson(json['voids'] as Map<String, dynamic>),
  batchWriteoffs: BatchWriteoffs5.fromJson(
    json['batch_writeoffs'] as Map<String, dynamic>,
  ),
  refunds: Refunds8.fromJson(json['refunds'] as Map<String, dynamic>),
  deposits: Deposits5.fromJson(json['deposits'] as Map<String, dynamic>),
  credit: Credit8.fromJson(json['credit'] as Map<String, dynamic>),
  cash: Cash6.fromJson(json['cash'] as Map<String, dynamic>),
  movements: (json['movements'] as List<dynamic>)
      .map((e) => Movements5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Report3ToJson(Report3 instance) => <String, dynamic>{
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
  'cash': instance.cash,
  'movements': instance.movements,
};
