// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines4 _$Lines4FromJson(Map<String, dynamic> json) => Lines4(
  type: json['type'] as String,
  label: json['label'] as String,
  quantity: (json['quantity'] as num).toInt(),
  unitPrice: json['unit_price'] as num,
  amount: json['amount'] as num,
);

Map<String, dynamic> _$Lines4ToJson(Lines4 instance) => <String, dynamic>{
  'type': instance.type,
  'label': instance.label,
  'quantity': instance.quantity,
  'unit_price': instance.unitPrice,
  'amount': instance.amount,
};
