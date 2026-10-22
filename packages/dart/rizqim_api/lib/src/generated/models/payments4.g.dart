// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments4 _$Payments4FromJson(Map<String, dynamic> json) => Payments4(
  form: Form.fromJson(json['form'] as String),
  amount: json['amount'] as String,
  prepayment: json['prepayment'] as bool,
  credit: json['credit'] as bool,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
);

Map<String, dynamic> _$Payments4ToJson(Payments4 instance) => <String, dynamic>{
  'form': instance.form,
  'amount': instance.amount,
  'prepayment': instance.prepayment,
  'credit': instance.credit,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
};
