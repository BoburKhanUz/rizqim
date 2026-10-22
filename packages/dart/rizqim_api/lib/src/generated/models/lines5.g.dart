// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines5 _$Lines5FromJson(Map<String, dynamic> json) => Lines5(
  type: json['type'] as String,
  label: json['label'] as String,
  quantity: (json['quantity'] as num).toInt(),
  unitPrice: json['unit_price'] as num,
  amount: json['amount'] as num,
);

Map<String, dynamic> _$Lines5ToJson(Lines5 instance) => <String, dynamic>{
  'type': instance.type,
  'label': instance.label,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'amount': instance.amount,
};
