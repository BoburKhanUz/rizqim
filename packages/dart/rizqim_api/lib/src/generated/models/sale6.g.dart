// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sale6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sale6 _$Sale6FromJson(Map<String, dynamic> json) => Sale6(
  tender: json['tender'] as String,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  reference: json['reference'] as String?,
  shiftId: (json['shift_id'] as num?)?.toInt(),
  soldAt: json['sold_at'] as String?,
  soldBy: json['sold_by'] == null
      ? null
      : SoldBy6.fromJson(json['sold_by'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Sale6ToJson(Sale6 instance) => <String, dynamic>{
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'reference': instance.reference,
  'shift_id': instance.shiftId,
  'sold_at': instance.soldAt,
  'sold_by': instance.soldBy,
};
