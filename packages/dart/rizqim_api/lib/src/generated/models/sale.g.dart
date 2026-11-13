// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sale.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sale _$SaleFromJson(Map<String, dynamic> json) => Sale(
  tender: json['tender'] as String,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  reference: json['reference'] as String?,
  shiftId: (json['shift_id'] as num?)?.toInt(),
  soldAt: json['sold_at'] as String?,
  soldBy: json['sold_by'] == null
      ? null
      : SoldBy.fromJson(json['sold_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$SaleToJson(Sale instance) => <String, dynamic>{
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'reference': instance.reference,
  'shift_id': instance.shiftId,
  'sold_at': instance.soldAt,
  'sold_by': instance.soldBy,
};
