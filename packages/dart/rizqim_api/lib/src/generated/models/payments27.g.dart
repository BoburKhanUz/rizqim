// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments27 _$Payments27FromJson(Map<String, dynamic> json) => Payments27(
  form: Form.fromJson(json['form'] as String),
  amount: json['amount'] as String,
  prepayment: json['prepayment'] as bool,
  credit: json['credit'] as bool,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  cardLast4: json['card_last4'] as String?,
);

Map<String, dynamic> _$Payments27ToJson(Payments27 instance) =>
    <String, dynamic>{
      'form': instance.form,
      'amount': instance.amount,
      'prepayment': instance.prepayment,
      'credit': instance.credit,
      'card_type': instance.cardType,
      'transaction_id': instance.transactionId,
      'card_last4': instance.cardLast4,
    };
