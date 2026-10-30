// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines15 _$Lines15FromJson(Map<String, dynamic> json) => Lines15(
  type: json['type'] as String,
  label: json['label'] as String,
  quantity: (json['quantity'] as num).toInt(),
  unitPrice: json['unit_price'] as num,
  amount: json['amount'] as num,
);

Map<String, dynamic> _$Lines15ToJson(Lines15 instance) => <String, dynamic>{
  'type': instance.type,
  'label': instance.label,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'amount': instance.amount,
};
