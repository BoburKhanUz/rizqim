// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments _$PaymentsFromJson(Map<String, dynamic> json) => Payments(
  form: Form.fromJson(json['form'] as String),
  amount: json['amount'] as String,
  prepayment: json['prepayment'] as bool,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
);

Map<String, dynamic> _$PaymentsToJson(Payments instance) => <String, dynamic>{
  'form': instance.form,
  'amount': instance.amount,
  'prepayment': instance.prepayment,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
};
