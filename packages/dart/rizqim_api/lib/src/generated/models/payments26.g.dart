// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments26 _$Payments26FromJson(Map<String, dynamic> json) => Payments26(
  form: Form.fromJson(json['form'] as String),
  amount: json['amount'] as String,
  prepayment: json['prepayment'] as bool,
  credit: json['credit'] as bool,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
);

Map<String, dynamic> _$Payments26ToJson(Payments26 instance) =>
    <String, dynamic>{
      'form': instance.form,
      'amount': instance.amount,
      'prepayment': instance.prepayment,
      'credit': instance.credit,
      'card_type': instance.cardType,
      'transaction_id': instance.transactionId,
    };
