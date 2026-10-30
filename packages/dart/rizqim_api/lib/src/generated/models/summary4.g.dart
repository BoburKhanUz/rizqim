// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary4 _$Summary4FromJson(Map<String, dynamic> json) => Summary4(
  shiftUuid: json['shift_uuid'] as String,
  generatedAt: json['generated_at'] as String,
  period: Period6.fromJson(json['period'] as Map<String, dynamic>),
  sales: Sales5.fromJson(json['sales'] as Map<String, dynamic>),
  byTender: (json['by_tender'] as List<dynamic>)
      .map((e) => ByTender3.fromJson(e as Map<String, dynamic>))
      .toList(),
  cards: (json['cards'] as List<dynamic>)
      .map((e) => Cards3.fromJson(e as Map<String, dynamic>))
      .toList(),
  byChannel: (json['by_channel'] as List<dynamic>)
      .map((e) => ByChannel5.fromJson(e as Map<String, dynamic>))
      .toList(),
  byWaiter: (json['by_waiter'] as List<dynamic>)
      .map((e) => ByWaiter3.fromJson(e as Map<String, dynamic>))
      .toList(),
  discounts: Discounts3.fromJson(json['discounts'] as Map<String, dynamic>),
  voids: Voids3.fromJson(json['voids'] as Map<String, dynamic>),
  batchWriteoffs: BatchWriteoffs3.fromJson(
    json['batch_writeoffs'] as Map<String, dynamic>,
  ),
  refunds: Refunds6.fromJson(json['refunds'] as Map<String, dynamic>),
  deposits: Deposits3.fromJson(json['deposits'] as Map<String, dynamic>),
  credit: Credit6.fromJson(json['credit'] as Map<String, dynamic>),
  tips: Tips11.fromJson(json['tips'] as Map<String, dynamic>),
  cash: Cash4.fromJson(json['cash'] as Map<String, dynamic>),
  movements: (json['movements'] as List<dynamic>)
      .map((e) => Movements3.fromJson(e as Map<String, dynamic>))
      .toList(),
  openOrders: (json['open_orders'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  pendingReceipts: json['pending_receipts'] == null
      ? null
      : PendingReceipts.fromJson(
          json['pending_receipts'] as Map<String, dynamic>,
        ),
);

Map<String, dynamic> _$Summary4ToJson(Summary4 instance) => <String, dynamic>{
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
  'open_orders': instance.openOrders,
  'pending_receipts': instance.pendingReceipts,
};
