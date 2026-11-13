// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'lines27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Lines27 _$Lines27FromJson(Map<String, dynamic> json) => Lines27(
  name: json['name'] as String,
  qty: json['qty'] as String,
  unit: json['unit'] as String?,
  price: json['price'] as String,
  total: json['total'] as String,
  modifiers: (json['modifiers'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  note: json['note'] as String?,
);

Map<String, dynamic> _$Lines27ToJson(Lines27 instance) => <String, dynamic>{
  'name': instance.name,
  'qty': instance.qty,
  'unit': instance.unit,
  'price': instance.price,
  'total': instance.total,
  'modifiers': instance.modifiers,
  'note': instance.note,
};
