// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data62.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data62 _$Data62FromJson(Map<String, dynamic> json) => Data62(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  basePrice: json['base_price'] as String,
  delta: json['delta'] as String,
  price: json['price'] as String,
  parts: (json['parts'] as List<dynamic>)
      .map((e) => Parts.fromJson(e as Map<String, dynamic>))
      .toList(),
  fiscalMode: ComboFiscalMode.fromJson(json['fiscal_mode'] as String),
  fiscal: Fiscal.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data62ToJson(Data62 instance) => <String, dynamic>{
  'name': instance.name,
  'quantity': instance.quantity,
  'base_price': instance.basePrice,
  'delta': instance.delta,
  'price': instance.price,
  'parts': instance.parts,
  'fiscal_mode': instance.fiscalMode,
  'fiscal': instance.fiscal,
};
