// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments3 _$Payments3FromJson(Map<String, dynamic> json) => Payments3(
  form: Form.fromJson(json['form'] as String),
  amount: json['amount'] as String,
  prepayment: json['prepayment'] as bool,
  credit: json['credit'] as bool,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
);

Map<String, dynamic> _$Payments3ToJson(Payments3 instance) => <String, dynamic>{
  'form': instance.form,
  'amount': instance.amount,
  'prepayment': instance.prepayment,
  'credit': instance.credit,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
};
